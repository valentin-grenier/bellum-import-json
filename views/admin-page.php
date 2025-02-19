<div class="wrap">
    <h1>Import JSON File</h1>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="updated notice">
            <p>File uploaded successfully! The import process has started.</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="error notice">
            <p>There was an error uploading the file.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sv_upload_json')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('sv_upload_json_action', 'sv_upload_json_nonce'); ?>
        <input type="file" name="json_file" required>
        <input type="submit" name="sv_upload_json_submit" value="Upload JSON" class="button button-primary">
    </form>
</div>