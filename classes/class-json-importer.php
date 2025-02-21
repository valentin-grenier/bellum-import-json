<?php
class JSON_Importer
{
    private $lock_file;
    private $upload_dir;
    private $queue_dir;
    private $processing_dir;
    private $imported_dir;

    public function __construct()
    {
        $this->lock_file = WP_CONTENT_DIR . '/json-files/import.lock';
        $this->upload_dir = WP_CONTENT_DIR . '/json-files';
        $this->queue_dir = $this->upload_dir . '/queue';
        $this->processing_dir = $this->upload_dir . '/processing';
        $this->imported_dir = $this->upload_dir . '/imported';

        add_action('init', [$this, 'schedule_cron']);
        add_action('json_import_cron', [$this, 'process_json_files']);
    }

    public function check_directories()
    {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        if (!file_exists($this->queue_dir)) {
            mkdir($this->queue_dir, 0755, true);
        }

        if (!file_exists($this->processing_dir)) {
            mkdir($this->processing_dir, 0755, true);
        }

        if (!file_exists($this->imported_dir)) {
            mkdir($this->imported_dir, 0755, true);
        }
    }

    public function schedule_cron()
    {
        if (!wp_next_scheduled('json_import_cron')) {
            wp_schedule_event(time(), 'every_five_minutes', 'json_import_cron');
        }
    }

    public function process_json_files()
    {
        if ($this->is_locked()) {
            sv_plugin_log('Process already running, exiting.');
            return;
        }

        $this->create_lock();

        $files = glob($this->queue_dir . '/*.json');

        if (empty($files)) {
            sv_plugin_log('No files to process.');
            $this->remove_lock();
            return;
        }

        # Flag start time of the import
        $start_time = microtime();

        foreach ($files as $file) {
            $file_start_time = microtime();

            $filename = basename($file);
            $new_path = $this->processing_dir . '/' . $filename;

            # Move to /processing
            rename($file, $new_path);
            sv_plugin_log("Moved $filename to /processing/");

            $this->process_file($new_path);

            $file_end_time = microtime();
            $file_time_taken = round($file_end_time - $file_start_time, 2);

            rename($new_path, $this->imported_dir . '/' . $filename);
            sv_plugin_log("Moved $filename to /imported/");

            # Count how many entries were imported
            $entries_count = count(json_decode(file_get_contents($new_path), true));

            sv_plugin_log("✅ $filename processed in $file_time_taken seconds. Imported $entries_count entries.");
        }

        # Flag end time of the import
        $end_time = microtime();

        # Calculate the time taken to import all profiles
        $time_taken = round($end_time - $start_time, 2);

        sv_plugin_log('✅ All files processed in ' . $time_taken . ' seconds');

        $this->remove_lock();
    }

    private function process_file($file_path)
    {
        # JSON file name
        $file_name = basename($file_path);

        # Your JSON processing logic here
        sv_plugin_log("Processing: $file_name");

        $json_data = file_get_contents($file_path);
        $entries = json_decode($json_data, true);

        # Flag the begin time of the import
        $start_time = microtime();

        foreach ($entries as $entry) {
            $person = $entry['person'];
            $name = sanitize_text_field($person['name']);
            $email = sanitize_email($entry['emails'][0]['email'] ?? '');
            $phone = sanitize_text_field($entry['phones'][0]['phone'] ?? '');

            # Check if the profile has a name
            if (empty($name)) {
                sv_plugin_log('Skipping profile without a name.');
                return;
            }

            # Check if the profile already exists
            if ($this->is_duplicate_entry($person)) {
                sv_plugin_log("Skipping duplicate: $name");
                return;
            }

            # Insert new post
            $post_id = wp_insert_post(array(
                'post_title' => sanitize_text_field($person['name']),
                'post_content' => sanitize_textarea_field($person['description']),
                'post_status' => 'publish',
                'post_type' => 'freelance'
            ));

            if (is_wp_error($post_id)) {
                sv_plugin_log('Erreur lors de la création du post pour ' . basename($file_path) . ': ' . $post_id->get_error_message());
                return false;
            }

            // Mise à jour des informations de la personne avec les nouveaux champs ACF
            update_field('coordonnees_longitude', sanitize_text_field($person['longitude']), $post_id);
            update_field('coordonnees_latitude', sanitize_text_field($person['latitude']), $post_id);
            update_field('initiale_nom', sanitize_text_field($person['anonymousLastName']), $post_id);
            update_field('initiale_prenom', sanitize_text_field($person['anonymousFirstName']), $post_id);
            update_field('tjm_min', sanitize_text_field($person['minAdr']), $post_id);
            update_field('tjm_max', sanitize_text_field($person['maxAdr']), $post_id);
            update_field('freelancer_name', sanitize_text_field($person['name']), $post_id);

            // Calcul des années d'expérience basées sur le champ `job_experience`
            if (isset($entry['job_experience']) && is_array($entry['job_experience'])) {
                $earliest_date = null;
                $latest_date = null;

                foreach ($entry['job_experience'] as $job) {
                    $started_on = $job['started_on'];
                    $ended_on = $job['ended_on'] ?? ($job['is_current_position'] ? date('Y-m-d') : null);

                    if ($started_on) {
                        try {
                            $start_date = new DateTime($started_on);
                            if (!$earliest_date || $start_date < $earliest_date) {
                                $earliest_date = $start_date;
                            }
                        } catch (Exception $e) {
                            error_log('Erreur de date pour ' . $started_on);
                        }
                    }
                    if ($ended_on) {
                        try {
                            $end_date = new DateTime($ended_on);
                            if (!$latest_date || $end_date > $latest_date) {
                                $latest_date = $end_date;
                            }
                        } catch (Exception $e) {
                            error_log('Erreur de date pour ' . $ended_on);
                        }
                    }
                }

                // Calcul des années d'expérience
                if ($earliest_date && $latest_date) {
                    $interval = $earliest_date->diff($latest_date);
                    $years_of_experience = $interval->y;
                    update_field('annees_experience', $years_of_experience, $post_id);
                }
            }

            // Mise à jour des informations existantes dans les champs ACF
            update_field('name', sanitize_text_field($person['frelancer_name']), $post_id);
            update_field('description', sanitize_textarea_field($person['description']), $post_id);
            update_field('primary_role', sanitize_text_field($person['primary_role']), $post_id);
            update_field('linkedin_url', esc_url($person['linkedin_url']), $post_id);
            update_field('facebook_url', esc_url($person['facebook_url']), $post_id);
            update_field('twitter_url', esc_url($person['twitter_url']), $post_id);
            update_field('crunchbase_url', esc_url($person['crunchbase_url']), $post_id);
            update_field('angellist_url', esc_url($person['angellist_url']), $post_id);
            update_field('city', sanitize_text_field($person['city']), $post_id);
            update_field('state', sanitize_text_field($person['state']), $post_id);
            update_field('country', sanitize_text_field($person['country']), $post_id);

            // Téléchargement et attachement de l'image de profil
            if (!empty($person['image'])) {
                $image_name = str_replace(' ', '-', sanitize_text_field($person['name']));
                $attach_id = $this->download_and_attach_image($person['image'], $post_id, $image_name);
                if ($attach_id) {
                    update_field('image_profil', $attach_id, $post_id);
                }
            }

            // Met à jour les champs ACF pour les emails
            if (isset($entry['emails']) && is_array($entry['emails'])) {
                $emails = array();
                foreach ($entry['emails'] as $email) {
                    $emails[] = array(
                        'email' => sanitize_text_field($email['email']),
                        'email_type' => sanitize_text_field($email['type']),
                    );
                }
                update_field('emails', $emails, $post_id);
            }

            // Met à jour les champs ACF pour les téléphones
            if (isset($entry['phones']) && is_array($entry['phones'])) {
                $phones = array();
                foreach ($entry['phones'] as $phone) {
                    $phones[] = array(
                        'phone' => sanitize_text_field($phone['phone']),
                        'phone_type' => sanitize_text_field($phone['phone_type']),
                    );
                }
                update_field('phones', $phones, $post_id);
            }

            // Met à jour les champs répéteurs pour l'expérience professionnelle
            if (isset($entry['job_experience']) && is_array($entry['job_experience'])) {
                $job_experiences = array();
                foreach ($entry['job_experience'] as $job) {
                    $company_name = isset($job['startup']) ? $job['startup']['name'] : $job['company_name'];
                    $logo_name = str_replace(' ', '-', sanitize_text_field($company_name));
                    $job_experiences[] = array(
                        'entreprise_name' => sanitize_text_field($company_name),
                        'entreprise_city' => sanitize_text_field($job['startup']['city'] ?? ''),
                        'entreprise_country' => sanitize_text_field($job['startup']['country'] ?? ''),
                        'entreprise_role' => sanitize_text_field($job['role']),
                        'entreprise_started_on' => sanitize_text_field($job['started_on']),
                        'is_current_position' => $job['is_current_position'] ? 1 : 0,
                        'entreprise_ended_on' => sanitize_text_field($job['ended_on']),
                        'entreprise_description' => sanitize_textarea_field($job['description']),
                        'entreprise_logo' => !empty($job['startup']['logo']) ? download_and_attach_image($job['startup']['logo'], $post_id, $logo_name) : '',
                    );
                }
                update_field('job_experience', $job_experiences, $post_id);
            }

            // Met à jour les champs répéteurs pour l'expérience éducative
            if (isset($entry['education_experience']) && is_array($entry['education_experience'])) {
                $education_experiences = array();
                foreach ($entry['education_experience'] as $edu) {
                    $education_experiences[] = array(
                        'school_name' => sanitize_text_field($edu['school_name']),
                        'school_grade' => sanitize_text_field($edu['grade']),
                        'school_subject' => sanitize_text_field($edu['subject']),
                        'school_subject_type' => sanitize_text_field($edu['subject_type']),
                        'school_started_on' => sanitize_text_field($edu['started_on']),
                        'school_completed_on' => sanitize_text_field($edu['completed_on']),
                    );
                }
                update_field('education_experience', $education_experiences, $post_id);
            }

            // Met à jour les champs répéteurs pour les compétences
            if (isset($entry['skills']) && is_array($entry['skills'])) {
                $skills = array();
                foreach ($entry['skills'] as $skill) {
                    $skills[] = array(
                        'skill' => sanitize_text_field($skill),
                    );
                }
                update_field('skills', $skills, $post_id);
            }

            # Flag the end time of the import
            $end_time = microtime();

            # Calculate the time taken to import the profile
            $time_taken = round($end_time - $start_time, 2);

            sv_plugin_log("✔️Imported: " . $person['name'] . " in $time_taken seconds");
        }

        return true;
    }

    private function download_and_attach_image($image_url, $post_id, $custom_name = '')
    {
        // Générer le nom de fichier basé sur le nom personnalisé
        if (!empty($custom_name)) {
            $image_name = strtolower(sanitize_file_name($custom_name)) . '.' . pathinfo($image_url, PATHINFO_EXTENSION);
        } else {
            $image_name = strtolower(basename($image_url));
        }

        // Vérifier si une pièce jointe avec ce nom de fichier et la même URL existe déjà
        $existing_image_id = $this->attachment_exists_by_url($image_url, $image_name);
        if ($existing_image_id) {
            return $existing_image_id;
        }

        // Si l'image n'existe pas, la télécharger
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        if (!$image_data) {
            error_log("Failed to download image from URL: $image_url");
            return null;
        }

        $unique_file_name = wp_unique_filename($upload_dir['path'], $image_name);
        $filename = basename($unique_file_name);
        $file = wp_mkdir_p($upload_dir['path']) ? $upload_dir['path'] . '/' . $filename : $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        if (!$wp_filetype['type']) {
            error_log("Invalid file type for image: $filename");
            return null;
        }

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        if (is_wp_error($attach_id)) {
            error_log("Failed to insert attachment for image: $filename");
            return null;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    private function attachment_exists_by_url($image_url, $image_name)
    {
        global $wpdb;

        // Rechercher une pièce jointe avec le même nom de fichier et le même GUID (URL)
        $query = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID FROM $wpdb->posts p
        INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'attachment' 
        AND p.post_title = %s
        AND pm.meta_key = '_wp_attached_file'
        AND pm.meta_value LIKE %s
        LIMIT 1", sanitize_title_with_dashes(pathinfo($image_name, PATHINFO_FILENAME)), '%' . $wpdb->esc_like($image_name) . '%'));

        if ($query) {
            $file_guid = wp_get_attachment_url($query);
            if ($file_guid === $image_url) {
                return $query;
            }
        }

        return false;
    }

    private function is_duplicate_entry($data)
    {
        global $wpdb;

        // Generate a unique hash for the entry based on important fields
        $hash = md5($data['email'] . $data['name']);

        // Check if this hash already exists in post meta
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_import_entry_hash' AND meta_value = %s",
                $hash
            )
        );

        return ($existing > 0);
    }


    private function is_locked()
    {
        if (!file_exists($this->lock_file)) {
            return false;
        }

        $lock_time = filemtime($this->lock_file);
        $timeout = 30 * 60; # 30 minutes

        if (time() - $lock_time > $timeout) {
            sv_plugin_log('JSON Import: Lock file expired, removing it.');
            unlink($this->lock_file);
            return false;
        }

        return true;
    }

    private function create_lock()
    {
        file_put_contents($this->lock_file, time());
    }

    private function remove_lock()
    {
        if (file_exists($this->lock_file)) {
            unlink($this->lock_file);
        }
    }
}
