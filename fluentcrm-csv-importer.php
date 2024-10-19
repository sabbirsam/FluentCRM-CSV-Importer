<?php
/*
Plugin Name: FluentCRM CSV Importer
Description: A simple plugin to import contacts from a CSV file into FluentCRM and assign them to a specific tag and list.
Version: 1.1
Author: sabbirsam
*/

if (!class_exists('FluentCRM_CSV_Importer')) {

    class FluentCRM_CSV_Importer {

        public function __construct() {
            ob_start(); // Start output buffering
            // Hook to add the admin menu
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }

        /**
         * Adds the admin menu page for importing and exporting contacts.
         */
        public function add_admin_menu() {
            add_menu_page(
                'FluentCRM CSV Importer',   
                'CSV Import to FluentCRM',  
                'manage_options',            
                'fluentcrm-csv-importer',   
                [$this, 'display_import_page']
            );

            add_submenu_page(
                'fluentcrm-csv-importer',
                'Export Contacts',
                'Export Contacts',
                'manage_options',
                'fluentcrm-csv-exporter',
                [$this, 'display_export_page']
            );
        }

        /**
         * Displays the import page for uploading a CSV file.
         * Checks if FluentCRM is active before showing the upload form.
         */
        public function display_import_page() {
            ?>
            <div class="wrap">
                <h1>Import Contacts into FluentCRM</h1>

                <?php if (!$this->is_fluentcrm_active()) : ?>
                    <div class="notice notice-error">
                        <p>FluentCRM is not installed or activated. Please install and activate FluentCRM to use this plugin.</p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success">
                        <p>FluentCRM is detected and active. You can proceed with importing contacts.</p>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <input type="file" name="csv_file" accept=".csv" required>
                        <br><br>
                        <input type="submit" name="import_csv" value="Import CSV" class="button button-primary">
                    </form>
                <?php endif; ?>
            </div>
            <?php

            // Process the CSV import if the form is submitted
            if (isset($_POST['import_csv'])) {
                $this->process_csv_import();
            }
        }

        /**
         * Displays the export page for exporting FluentCRM contacts as a CSV file.
         * Checks if FluentCRM is active before showing the export button.
         */
        public function display_export_page() {
            ?>
            <div class="wrap">
                <h1>Export FluentCRM Contacts</h1>
        
                <?php if (!$this->is_fluentcrm_active()) : ?>
                    <div class="notice notice-error">
                        <p>FluentCRM is not installed or activated. Please install and activate FluentCRM to use this plugin.</p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success">
                        <p>FluentCRM is detected and active. You can proceed with exporting contacts.</p>
                    </div>
        
                    <form method="post">
                        <input type="submit" name="export_csv" value="Export Contacts as CSV" class="button button-primary">
                    </form>
                <?php endif; ?>
        
            </div>
            <?php
        
            // Process the CSV export if the form is submitted
            if (isset($_POST['export_csv'])) {
                $this->process_csv_export();
            }
        }

        /**
         * Handles the CSV export of FluentCRM contacts.
         * Sends a CSV file for download containing the subscribers' details.
         */
        private function process_csv_export() {
            // Check if FluentCRM is active
            if (!$this->is_fluentcrm_active()) {
                echo '<div class="notice notice-error">FluentCRM is not installed or activated.</div>';
                return;
            }

            // Clear any previous output
            if (ob_get_length()) ob_end_clean();

            // Set headers for the CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="fluentcrm-contacts.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Open output stream for writing CSV
            $output = fopen('php://output', 'w');

            // Add the header row
            fputcsv($output, ['First Name', 'Last Name', 'Email', 'Phone']);

            // Fetch all subscribers from FluentCRM
            $subscribers = FluentCrm\App\Models\Subscriber::all();

            // Loop through subscribers and write to the CSV
            foreach ($subscribers as $subscriber) {
                fputcsv($output, [
                    sanitize_text_field($subscriber->first_name),
                    sanitize_text_field($subscriber->last_name),
                    sanitize_email($subscriber->email),
                    sanitize_text_field($subscriber->phone),
                ]);
            }

            fclose($output);

            // Exit after sending the CSV
            exit; 
        }

        /**
         * Checks if FluentCRM is installed and active.
         * 
         * @return bool True if FluentCRM is active, false otherwise.
         */
        private function is_fluentcrm_active() {
            // Check if FluentCRM's Subscriber class exists
            return class_exists('FluentCrm\App\Models\Subscriber');
        }

        /**
         * Handles the import of contacts from a CSV file into FluentCRM.
         * It creates or updates contacts and assigns them to a specific tag and list.
         */
        private function process_csv_import() {
            if (!$this->is_fluentcrm_active()) {
                echo '<div class="notice notice-error">FluentCRM is not installed or activated.</div>';
                return;
            }

            // Get tag and list IDs by slug
            $tag = FluentCrm\App\Models\Tag::firstOrCreate(['slug' => 'random-user'], ['title' => 'Random user']);
            $list = FluentCrm\App\Models\Lists::firstOrCreate(['slug' => 'random-user-list'], ['title' => 'Random user list']);

            if (!$tag || !$list) {
                echo '<div class="notice notice-error">Failed to retrieve or create tag/list in FluentCRM.</div>';
                return;
            }

            $tag_id = $tag->id;
            $list_id = $list->id;

            // Check if a file was uploaded
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $csv_file = $_FILES['csv_file']['tmp_name'];

                // Open the CSV file and read its contents
                if (($handle = fopen($csv_file, "r")) !== FALSE) {
                    // Skip the first row (header)
                    fgetcsv($handle);

                    // Loop through each row of the CSV file
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $full_name = $data[0];
                        $email = $data[2];  // CSV's email column is the third field (index 2)
                        $work_phone = $data[3];
                        $mobile_phone = $data[4];

                        // Skip rows with empty email
                        if (empty($email)) {
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
                            $existing_contact->phone = $mobile_phone ?: $work_phone; // Set either work or mobile phone
                            $existing_contact->save();

                            // Attach to tag and list if not already attached
                            $existing_contact->attachTags([$tag_id]);
                            $existing_contact->attachLists([$list_id]);

                            echo '<div class="notice notice-success">Contact with email ' . esc_html($email) . ' updated successfully.</div>';
                        } else {
                            // Prepare new contact data
                            $contact_data = [
                                'first_name' => $first_name,
                                'last_name'  => $last_name,
                                'email'      => sanitize_email($email),
                                'phone'      => $mobile_phone ?: $work_phone, // Use either mobile or work phone
                                'status'     => 'subscribed', // Set status to subscribed
                            ];

                            // Create a new contact
                            $new_contact = FluentCrm\App\Models\Subscriber::create($contact_data);

                            // If contact creation is successful, add them to the tag and list
                            if ($new_contact) {
                                $new_contact->attachTags([$tag_id]);
                                $new_contact->attachLists([$list_id]);
                                echo '<div class="notice notice-success">Contact with email ' . esc_html($email) . ' imported and added to tag and list successfully.</div>';
                            } else {
                                echo '<div class="notice notice-error">Failed to import contact with email ' . esc_html($email) . '.</div>';
                            }
                        }
                    }
                    fclose($handle); // Close the CSV file
                } else {
                    echo '<div class="notice notice-error">Failed to open the CSV file.</div>';
                }
            } else {
                echo '<div class="notice notice-error">No CSV file uploaded.</div>';
            }
        }
    }

    // Initialize the plugin
    new FluentCRM_CSV_Importer();
}
