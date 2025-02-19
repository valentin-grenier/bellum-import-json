<?php

# Get notices from transient
$notices = get_transient('sv_import_json_notices');
?>

<div class="wrap">
    <h1>Import JSON File</h1>

    <?php if ($notices): ?>
        <?php foreach ($notices as $notice): ?>
            <div class="notice notice-<?php echo $notice['type']; ?> is-dismissible">
                <p><?php echo $notice['message']; ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url('admin-post.php?action=sv_import_json'); ?>" enctype="multipart/form-data" id="sv_upload_json_form">
        <?php wp_nonce_field('sv_upload_json_action', 'sv_upload_json_nonce'); ?>
        <input type="file" name="json_file" accept=".json">
        <input type="submit" name="sv_upload_json_submit" value="Upload JSON" class="button button-primary">
    </form>
</div>