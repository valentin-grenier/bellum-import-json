<?php

# Get notices from transient
$notices = get_transient('sv_import_json_notices');

# Get directories
$uploads_dir = WP_CONTENT_DIR . '/json-files/';
$queue_dir = $uploads_dir . 'queue/';
$processing_dir = $uploads_dir . 'processing/';
$imported_dir = $uploads_dir . 'imported/';
$test_dir = $uploads_dir . 'test/';

# Get files per directory
$queue_files = array_filter(array_diff(scandir($queue_dir), array('..', '.')), function ($file) use ($queue_dir) {
    return is_file($queue_dir . $file);
});

$processing_files = array_filter(array_diff(scandir($processing_dir), array('..', '.')), function ($file) use ($processing_dir) {
    return is_file($processing_dir . $file);
});

$imported_files = array_filter(array_diff(scandir($imported_dir), array('..', '.')), function ($file) use ($imported_dir) {
    return is_file($imported_dir . $file);
});

$test_files = array_filter(array_diff(scandir($test_dir), array('..', '.')), function ($file) use ($test_dir) {
    return is_file($test_dir . $file);
});

# TESTS
$test_file = $test_files[2];
$all_data = json_decode(file_get_contents($test_dir . $test_file), true);
$data = $all_data[0];
$json_importer = new JSON_Importer();
$is_duplicate = $json_importer->is_duplicate_entry($data) ? 'Not duplicate' : 'Duplicate';

?>

<div class="sv-container wrap" id="sv-import-json">
    <?php if ($notices): ?>
        <?php foreach ($notices as $notice): ?>
            <div class="notice notice-<?php echo $notice['type']; ?> is-dismissible">
                <p><?php echo $notice['message']; ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h1><?php esc_html_e('Importer les données JSON', 'bellum'); ?></h1>

    <div class="sv-container__box">
        <form method="post" action="<?php echo admin_url('admin-post.php?action=sv_import_json'); ?>" enctype="multipart/form-data" id="sv_upload_json_form">
            <h2><?php _e('Importer un fichier', 'bellum'); ?></h2>
            <?php wp_nonce_field('sv_upload_json_action', 'sv_upload_json_nonce'); ?>
            <input type="file" name="json_file" accept=".json">
            <input type="submit" name="sv_upload_json_submit" value="Upload JSON" class="button button-primary">
        </form>
    </div>

    <div class="sv-container__columns">
        <?php if ($queue_files): ?>
            <div class="sv-container__box sv-files-list">
                <?php if (is_array($queue_files) && !empty($queue_files)) : ?>
                    <h2>🕒 <?php _e(count($queue_files) . " fichiers à importer", "bellum"); ?></h2>
                    <ul>
                        <?php foreach (array_slice($queue_files, 0, 10) as $file) : ?>
                            <li><?php echo esc_html($file); ?></li>
                        <?php endforeach; ?>

                        <?php if (count($queue_files) > 10): $remaining_files = count($queue_files) - 10; ?>
                            <?php if ($remaining_files > 1): ?>
                                <strong>+<?php esc_html_e($remaining_files . ' autres fichiers', 'bellum'); ?></strong>
                            <?php else: ?>
                                <strong>+<?php esc_html_e($remaining_files . ' autre fichier', 'bellum'); ?></strong>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e('Aucun fichier JSON disponible pour l\'importation.', 'bellum'); ?></p>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php?action=sv_delete_queue_files'); ?>">
                    <button class="button" id="sv_delete_queue_files" onclick="return confirm('Are you sure you want to delete queue files?');">
                        <?php esc_html_e('🗑️ Supprimer les fichiers en attente', 'bellum'); ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($processing_files): ?>
            <div class="sv-container__box sv-files-list">
                <?php if (is_array($processing_files) && !empty($processing_files)) : ?>
                    <h2>⌛ <?php _e("En cours d'import", "bellum"); ?></h2>
                    <ul>
                        <?php foreach (array_slice($processing_files, 0, 10) as $file) : ?>
                            <li><?php echo esc_html($file); ?></li>
                        <?php endforeach; ?>

                        <?php if (count($processing_files) > 10): $other_files = count($files) - 10; ?>
                            <?php if ($other_files > 1): ?>
                                <strong>+<?php esc_html_e($other_files . ' autres fichiers', 'bellum'); ?></strong>
                            <?php else: ?>
                                <strong>+<?php esc_html_e($other_files . ' autre fichier', 'bellum'); ?></strong>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e('Aucun fichier JSON disponible pour l\'importation.', 'bellum'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($imported_files): ?>
            <div class="sv-container__box sv-files-list">
                <h2>✅ <?php _e(count($imported_files) . " fichiers importés", "bellum"); ?></h2>

                <?php if (is_array($imported_files) && !empty($imported_files)) : ?>
                    <ul>
                        <?php foreach (array_slice($imported_files, 0, 10) as $file) : ?>
                            <li><?php echo esc_html($file); ?></li>
                        <?php endforeach; ?>

                        <?php if (count($imported_files) > 10): $other_imported_files = count($imported_files) - 10; ?>
                            <li>
                                <?php if ($other_imported_files > 1): ?>
                                    <strong>+<?php esc_html_e($other_imported_files . ' autres fichiers', 'bellum'); ?></strong>
                                <?php else: ?>
                                    <strong>+<?php esc_html_e($other_imported_files . ' autre fichier', 'bellum'); ?></strong>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php?action=sv_delete_imported_files'); ?>">
                    <button class="button" id="sv_delete_imported_files" onclick="return confirm('Are you sure you want to delete this item?');">
                        <?php esc_html_e('🗑️ Supprimer les fichiers importés', 'bellum'); ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($test_files): ?>
            <div class="sv-container__box sv-files-list">
                <h2>🧪 <?php _e(count($test_files) . " fichiers de test", "bellum"); ?></h2>

                <?php echo $data['person']['name']; ?>
                <br />
                <?php echo $data['emails'][0]['email']; ?>
                <br />
                <?php echo $data['person']['linkedin_url']; ?>
                <br />
                <?php echo isset($data['emails'][0]) ? "emails is set" : "not set" ?>

                <strong><?php echo $is_duplicate; ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>
