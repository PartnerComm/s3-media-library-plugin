<?php
/*
Plugin Name: S3/Media Library Syncer
Plugin URI: http://www.pcommsites.com
Description: Plugin for syncing the Media Library to match files in S3.
Version: 0.0.2
Author: PartnerComm
*/

register_setting( 'S3/Media Library Syncer', 's3_media_library_syncer_options_page' );
// Add the admin page
add_action('admin_menu', 's3_media_library_syncer_add_page', 11);

// Adding the admin page
function s3_media_library_syncer_add_page() {
    add_options_page('S3/Media Library Syncer', 'S3/Media Library Syncer', 'upload_files', __FILE__, 's3_media_library_syncer_options_page');
}

function s3_media_library_syncer_options_page() {
    $display = "Click run!";
    if($_POST) {
        if(!empty($_POST['runS3MediaSyncer'])) {
            if(defined("AS3CF_SETTINGS")) {
                $settings = unserialize(AS3CF_SETTINGS);
                if(!empty($settings['provider']) && $settings['provider'] == 'aws') {
                    if(
                        !empty($settings['access-key-id']) &&
                        !empty($settings['secret-access-key'])
                    ) {
                        $controller = new \PComm\S3MediaLibrary\Controller();
                        $display = $controller->run($settings);
                    } else {
                        $display = "Key ID and Access Key not provided";
                    }
                } else {
                    $display = "Need to use AWS as provider";
                }
            } else {
                $display = "Need to add settings for AWS/S3 Plugin!!!";
            }
        }
    }
    ?>

    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>S3/Media Library Syncer</h2>
        <div>
            <form method="post">
                <input name="runS3MediaSyncer" class="button-primary" type="submit" value="Run Syncer"/>
            </form>
        </div>
        <textarea rows="4" cols="100" disabled><?=$display?></textarea>
    </div>

    <?php
}