<?php
/*
Plugin Name: S3/Media Library Syncer
Plugin URI: http://www.pcommsites.com
Description: Plugin for syncing the Media Library to match files in S3.
Version: 0.0.1
Author: PartnerComm
*/

// Add the admin page
add_action('admin_menu', 's3_media_library_syncer_add_page', 11);

// Adding the admin page
function s3_media_library_syncer_add_page() {
    add_options_page('S3/Media Library Syncer', 'S3/Media Library Syncer', 'upload_files', __FILE__, 's3_media_library_syncer_options_page');
}

function s3_media_library_syncer_options_page() {
    ?>

    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>S3/Media Library Syncer</h2>
        Testing this
    </div>

    <?php
}
