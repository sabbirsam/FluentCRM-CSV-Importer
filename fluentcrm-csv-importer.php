<?php
/*
Plugin Name: FluentCRM CSV Importer
Description: A simple plugin to import contacts from a CSV file into FluentCRM and assign them to a specific tag and list.
Version: 1.0
Author: sabbirsam
*/

if (!class_exists('FluentCRM_CSV_Importer')) {

    class FluentCRM_CSV_Importer {

        public function __construct() {
            // Hook to add the admin menu
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }

        // Function to add the admin menu page
        public function add_admin_menu() {
            add_menu_page(
                'FluentCRM CSV Importer',
                'CSV Import to FluentCRM',
                'manage_options',
                'fluentcrm-csv-importer',
                [$this, 'display_import_page']
            );
        }

        // Function to display the CSV upload form and FluentCRM detection
        public function display_import_page() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Import Contacts into FluentCRM', 'fluentcrm-csv-importer'); ?></h1>

                <?php if (!$this->is_fluentcrm_active()) : ?>
                    <div class="notice notice-error">
                        <p><?php esc_html_e('FluentCRM is not installed or activated. Please install and activate FluentCRM to use this plugin.', 'fluentcrm-csv-importer'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success">
                        <p><?php esc_html_e('FluentCRM is detected and active. You can proceed with importing contacts.', 'fluentcrm-csv-importer'); ?></p>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <input type="file" name="csv_file" accept=".csv" required>
                        <br><br>
                        <input type="submit" name="import_csv" value="<?php esc_attr_e('Import CSV', 'fluentcrm-csv-importer'); ?>" class="button button-primary">
                    </form>
                <?php endif; ?>
            </div>
            <?php

            // Process the CSV import if the form is submitted
            if (isset($_POST['import_csv'])) {
                $this->process_csv_import();
            }
        }

        // Function to check if FluentCRM is installed and active
        private function is_fluentcrm_active() {
            return class_exists('FluentCrm\App\Models\Subscriber');
        }

        // Function to handle the CSV file import and contact insertion
        private function process_csv_import() {
            if (!$this->is_fluentcrm_active()) {
                echo '<div class="notice notice-error">' . esc_html__('FluentCRM is not installed or activated.', 'fluentcrm-csv-importer') . '</div>';
                return;
            }

            // Get tag and list IDs by slug
            $tag = FluentCrm\App\Models\Tag::firstOrCreate(['slug' => 'random-user'], ['title' => 'Random user']);
            $list = FluentCrm\App\Models\Lists::firstOrCreate(['slug' => 'random-user-list'], ['title' => 'Random user list']);

            if (!$tag || !$list) {
                echo '<div class="notice notice-error">' . esc_html__('Failed to retrieve or create tag/list in FluentCRM.', 'fluentcrm-csv-importer') . '</div>';
                return;
            }

            $tag_id = (int) $tag->id;
            $list_id = (int) $list->id;

            // Check if a file was uploaded
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $csv_file = $_FILES['csv_file']['tmp_name'];

                // Open the CSV file and read its contents
                if (($handle = fopen($csv_file, "r")) !== FALSE) {
                    // Skip the first row (header)
                    fgetcsv($handle);

                    // Loop through each row of the CSV file
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $full_name = sanitize_text_field($data[0]);
                        $email = sanitize_email($data[2]);  // CSV's email column is the third field (index 2)
                        $work_phone = sanitize_text_field($data[3]);
                        $mobile_phone = sanitize_text_field($data[4]);

                        // Skip rows with empty email
                        if (empty($email) || !is_email($email)) {
                            continue;
                        }

                        // Split full name into first and last name
                        $name_parts = explode(' ', $full_name, 2);
                        $first_name = isset($name_parts[0]) ? sanitize_text_field($name_parts[0]) : '';
                        $last_name = isset($name_parts[1]) ? sanitize_text_field($name_parts[1]) : '';

                        // Check if the subscriber already exists by email
                        $existing_contact = FluentCrm\App\Models\Subscriber::where('email', $email)->first();

                        if ($existing_contact) {
                            // If contact exists, update its information
                            $existing_contact->first_name = $first_name;
                            $existing_contact->last_name = $last_name;
                            $existing_contact->phone = !empty($mobile_phone) ? $mobile_phone : $work_phone; // Set either work or mobile phone
                            $existing_contact->save();

                            // Attach to tag and list if not already attached
                            $existing_contact->attachTags([$tag_id]);
                            $existing_contact->attachLists([$list_id]);

                            echo '<div class="notice notice-success">' . sprintf(esc_html__('Contact with email %s updated successfully.', 'fluentcrm-csv-importer'), esc_html($email)) . '</div>';
                        } else {
                            // Prepare new contact data
                            $contact_data = [
                                'first_name' => $first_name,
                                'last_name'  => $last_name,
                                'email'      => $email,
                                'phone'      => !empty($mobile_phone) ? $mobile_phone : $work_phone, // Use either mobile or work phone
                                'status'     => 'subscribed', // Set status to subscribed
                            ];

                            // Create a new contact
                            $new_contact = FluentCrm\App\Models\Subscriber::create($contact_data);

                            // If contact creation is successful, add them to the tag and list
                            if ($new_contact) {
                                $new_contact->attachTags([$tag_id]);
                                $new_contact->attachLists([$list_id]);
                                echo '<div class="notice notice-success">' . sprintf(esc_html__('Contact with email %s imported and added to tag and list successfully.', 'fluentcrm-csv-importer'), esc_html($email)) . '</div>';
                            } else {
                                echo '<div class="notice notice-error">' . sprintf(esc_html__('Failed to import contact with email %s.', 'fluentcrm-csv-importer'), esc_html($email)) . '</div>';
                            }
                        }
                    }

                    // Close the file
                    fclose($handle);
                } else {
                    echo '<div class="notice notice-error">' . esc_html__('Failed to open the CSV file.', 'fluentcrm-csv-importer') . '</div>';
                }
            } else {
                echo '<div class="notice notice-error">' . esc_html__('Please upload a valid CSV file.', 'fluentcrm-csv-importer') . '</div>';
            }
        }
    }

    // Initialize the plugin class
    new FluentCRM_CSV_Importer();
}
