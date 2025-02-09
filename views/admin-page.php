<?php
# Get list of JSON files inside themes/bellum/json-files directory
$json_dir = get_stylesheet_directory() . '/json-files/';

$files = array_filter(array_diff(scandir($json_dir), array('..', '.')), function ($file) use ($json_dir) {
    return is_file($json_dir . $file);
});

# Get list of imported JSON files
$imported_files_dir = get_stylesheet_directory() . '/json-files/imported/';

?>

<div class="sv-container wrap" id="sv-import-json">
    <h1><?php esc_html_e('Importer les données JSON', 'bellum'); ?></h1>

    <div class="sv-container__box">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <h2><?php _e('Importer un fichier', 'bellum'); ?></h2>
            <input type="file" name="json-file" id="json-file" accept=".json">
            <?php wp_nonce_field('import_json_nonce', 'import_json_nonce_field'); ?>
            <input type="hidden" name="action" value="sv_import_json">
            <input type="submit" name="submit" value="Importer" class="button button-primary">
        </form>
    </div>

    <div class="sv-container__columns">
        <div class="sv-container__box sv-files-list">
            <?php if (is_array($files) && !empty($files)) : ?>
                <h2>⌛ <?php _e(count($files) . " imports de fichiers en cours", "bellum"); ?></h2>

                <ul>
                    <?php foreach (array_slice($files, 0, 10) as $file) : ?>
                        <li><?php echo esc_html($file); ?></li>
                    <?php endforeach; ?>

                    <?php if (count($files) > 10): $other_files = count($files) - 10; ?>
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

        <?php if ($imported_files_dir): ?>
            <?php $imported_files = array_filter(array_diff(scandir($imported_files_dir), array('..', '.')), function ($file) use ($imported_files_dir) {
                return is_file($imported_files_dir . $file);
            }); ?>
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

                    <!-- Delete All Button -->
                    <!-- <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('delete_all_imported_files_nonce', 'delete_all_imported_files_nonce_field'); ?>
                        <input type="hidden" name="action" value="delete_all_imported_files">
                        <input type="submit" class="button button-secondary" value="<?php esc_html_e('🗑️ Supprimer tous les fichiers importés', 'bellum'); ?>"
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer tous les fichiers importés ? Cette action est irréversible.');">
                    </form> -->
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>