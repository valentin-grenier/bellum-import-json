<?php

/**
 * Import process class
 */

class Import_Process
{
    # JSON file import process
    public function import_single_json_file($file_path)
    {
        if (is_file($file_path)) {
            $json_data = file_get_contents($file_path);
            $data = json_decode($json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                sv_plugin_log('❌ Erreur de décodage JSON dans ' . basename($file_path) . ': ' . json_last_error_msg());
                return false;
            }

            foreach ($data as $entry) {
                $person = $entry['person'];

                # Check if the profile already exists
                $existing_profile = $this->find_existing_profile($person);

                if ($existing_profile) {
                    sv_plugin_log('⏭️ Profil déjà existant pour ' . $person['name'] . ' avec l\'ID ' . $existing_profile);
                    continue;
                }

                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($person['name']),
                    'post_content' => sanitize_textarea_field($person['description']),
                    'post_status' => 'publish',
                    'post_type' => 'freelance'
                ));

                if (is_wp_error($post_id)) {
                    sv_plugin_log('❌ Erreur lors de la création du post pour ' . basename($file_path) . ': ' . $post_id->get_error_message());
                    continue;
                }

                sv_plugin_log('✅ Profil créé pour ' . $person['name'] . ' avec l\'ID ' . $post_id);

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
                                sv_plugin_log('❌ Erreur de date pour ' . $started_on);
                            }
                        }
                        if ($ended_on) {
                            try {
                                $end_date = new DateTime($ended_on);
                                if (!$latest_date || $end_date > $latest_date) {
                                    $latest_date = $end_date;
                                }
                            } catch (Exception $e) {
                                sv_plugin_log('❌ Erreur de date pour ' . $ended_on);
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
                    $attach_id = download_and_attach_image($person['image'], $post_id, $image_name);
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
            }

            return true;
        } else {
            sv_plugin_log('❌ Fichier introuvable: ' . basename($file_path));
        }
        return false;
    }

    # Duplicate profile check
    private function find_existing_profile($person)
    {
        $meta_query = ['relation' => 'OR'];

        if (!empty($person['emails'])) {
            foreach ($person['emails'] as $email) {
                $meta_query[] = [
                    'key'     => 'emails',
                    'value'   => sanitize_text_field($email['email']),
                    'compare' => 'LIKE'
                ];
            }
        }

        if (!empty($person['phones'])) {
            foreach ($person['phones'] as $phone) {
                $meta_query[] = [
                    'key'     => 'phones',
                    'value'   => sanitize_text_field($phone['phone']),
                    'compare' => 'LIKE'
                ];
            }
        }

        if (!empty($person['name']) && !empty($person['city'])) {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'key'     => 'freelancer_name',
                    'value'   => sanitize_text_field($person['name']),
                    'compare' => '='
                ],
                [
                    'key'     => 'city',
                    'value'   => sanitize_text_field($person['city']),
                    'compare' => '='
                ]
            ];
        }

        $existing_profiles = get_posts([
            'post_type'      => 'freelance',
            'posts_per_page' => 1,
            'meta_query'     => $meta_query,
            'fields'         => 'ids'
        ]);

        return !empty($existing_profiles) ? $existing_profiles[0] : false;
    }
}
