<?php

namespace PComm\S3MediaLibrary;

class Controller {

    protected $result = "Starting...\n";
    protected $settings;
    protected $bucketData;

    public function run($settings, $live) {
        try {
            $this->settings = $settings;
            $this->bucketData = $this->getBucketData();
            $files = $this->getFiles();
            $mediaLibrary = $this->getMediaLibraryFiles();
            $filesToAdd = array_diff($files, $mediaLibrary);
            $filesToRemove = array_diff($mediaLibrary, $files);
            if(empty($filesToAdd)) {
                $this->result .= "No Files Will be Added To Media Library\n";
            } else {
                $this->result .= "Files That Will Be Added To Media Library:\n";
                foreach($filesToAdd as $file) {
                    $this->result .= $file ."\n";
                }
            }
            $this->result .= "\n";
            if(empty($filesToRemove)) {
                $this->result .= "No Files Will be Removed From Media Library\n";
            } else {
                $this->result .= "Files That Will Be Removed From Media Library:\n";
                foreach($filesToAdd as $file) {
                    $this->result .= $file ."\n";
                }
            }
            $this->result .= "\n";
            if($live) {
                $this->result .= "Adding/deleting...\n";
                foreach($filesToAdd as $fileToAdd) {
                    $this->addFile($fileToAdd);
                }
                foreach($filesToRemove as $fileToRemove) {
                    $this->removeFile($fileToRemove);
                }
            } else {
                $this->result .= "Click 'Run Syncer' to apply changes\n";
            }
        } catch (\Exception $e) {
            $this->result .= "Encountered unhandled error: ". $e->getMessage() ."\n";
        }
        return $this->result;
    }

    protected function addFile($file) {
        global $wpdb;

        $file_path = $this->generateFilePath($file);
        $title = pathinfo($file, PATHINFO_FILENAME);

        $file_type = wp_check_filetype($file, null);
        $attachment = [
            'guid' => $this->getUploadDirectory() ."/{$file}",
            'post_mime_type' => $file_type['type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment(
            $attachment,
            $file,
            0
        );

        $wpdb->insert($GLOBALS['table_prefix'] .'postmeta', [
            'meta_id' => NULL,
            'post_id' => $attach_id,
            'meta_key' => 'amazonS3_info',
            'meta_value' => $this->getS3Info($file)
        ]);

        apply_filters('wp_handle_upload', array('file' => $file_path, 'url' => $this->getS3Url($file), 'type' => $file_type), 'upload');
        if ($attach_data = wp_generate_attachment_metadata($attach_id, $file_path)) {
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
        /*
        $title = pathinfo($file, PATHINFO_FILENAME);
        $fileType = $this->getFileType($file);

        $wpdb->insert($GLOBALS['table_prefix'] .'posts', [
            'post_author' => get_current_user_id(),
            'post_date' => date('Y-m-d H:i:s'),
            'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            'post_title' => $title,
            'post_status' => 'inherit',
            'ping_status' => 'closed',
            'post_name' => strtolower($title),
            'post_modified' => date('Y-m-d H:i:s'),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            'guid' => $this->getUploadDirectory() ."/{$file}",
            'post_type' => 'attachment',
            'post_mime_type' => $fileType,
            'post_content' => '',
            'post_excerpt' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => ''
        ]);

        $imageId = $wpdb->insert_id;

        $wpdb->insert($GLOBALS['table_prefix'] .'postmeta', [
            'meta_id' => NULL,
            'post_id' => $imageId,
            'meta_key' => 'wp_attached_file',
            'meta_value' => $file
        ]);
        /*
        $sql = "INSERT INTO {$GLOBALS['table_prefix']}posts ".
            "(post_author, post_date, post_date_gmt, post_title, post_status, ".
            "ping_status, post_name, post_modified, post_modified_gmt, ".
            "guid, post_type, post_mime_type, post_content, post_excerpt, to_ping, ".
            "pinged, post_content_filtered) ".
            "VALUES (" .
            get_current_user_id() .", '". date('Y-m-d H:i:s') ."', '".
            gmdate('Y-m-d H:i:s') ."', '". $title ."', 'inherit', 'closed', '".
            strtolower($title) ."', '". date('Y-m-d H:i:s') ."', '". gmdate('Y-m-d H:i:s')
            ."', '". $this->getUploadDirectory() ."/{$file}" ."', 'attachment', '".
            $fileType ."', '', '', '', '', ''"
            .");";

        $this->result .= "$sql\n\n";
        $sql = "INSERT INTO {$wpdb->prefix}postmeta VALUES (".
            "NULL, LAST_INSERT_ID(), '_wp_attached_file', '{$file}'".
        "), (".
            "NULL, LAST_INSERT_ID(), 'amazonS3_info', '". $this->getS3Info($file) ."'".
        ");";
        $this->result .= "$sql\n\n";
         */
        //$this->getAttachmentMetaData($imageId, $file);
    }

    // @TODO
    protected function removeFile($file) {
        global $wpdb;
    }

    protected function generateFilePath($file) {
        $location = "/tmp/$file";
        $ch = curl_init($this->getS3Url($file));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        $raw=curl_exec($ch);
        curl_close ($ch);
        if(file_exists($location)){
            unlink($location);
        }
        $fp = fopen($location,'x');
        fwrite($fp, $raw);
        fclose($fp);
        return $location;
    }

    protected function getS3Info($file) {
        $info = [
            'provider' => 'aws',
            'region' => $this->bucketData->region,
            'bucket' => $this->bucketData->name,
            'key' => $file
        ];
        return serialize($info);
    }

    protected function getS3Url($file) {
        return "https://s3-{$this->bucketData->region}.amazonaws.com/{$this->bucketData->name}/{$file}";
    }

    protected function getUploadDirectory() {
        $uploadData = wp_upload_dir();
        return $uploadData['url'];
    }

    // @TODO Combine with saveFileLocally
    protected function getFile($file) {
        $url = $this->getS3Url($file);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if(!empty(curl_error($ch))) {
            throw new \Exception("Curl error: ". curl_error($ch));
        }
        return $ch;
    }

    // @TODO Combine with saveFileLocally
    protected function getFileType($file) {
        return curl_getinfo($this->getFile($file), CURLINFO_CONTENT_TYPE);
    }

    protected function getMediaLibraryFiles() {
        $media_query = new \WP_Query(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            )
        );
        $list = [];
        foreach ($media_query->posts as $post) {
            $list[] = basename(get_attached_file($post->ID));
        }
        return $list;
    }

    protected function getFiles() {
        $command = $this->getCredentialPrefix();
        $command .= "aws s3 ls {$this->bucketData->name} --region {$this->bucketData->region} | awk '{\$1=\$2=\$3=\"\"; print $0}' | sed 's/^[ \\t]*//'";
        $files = shell_exec($command);
        $files = explode("\n", $files);
        return $this->removeDimensionFiles($files);
    }

    /**
     * Remove files that are copies of other files, except are a different size
     */
    protected function removeDimensionFiles($files) {
        $return = [];
        foreach($files as $file) {
            $modifiedFile = preg_replace(
                '/(.*)(-(\d*)x(\d*))(.*)/',
                '${1}${5}',
                $file
            );
            // If there isn't a file name, we don't need it
            if(empty($modifiedFile)) {
                continue;
            }
            // Check to make sure that the file exists
            if($modifiedFile != $file) {
                /**
                 * @TODO Fix this after resolving issue with not deleting dimension
                 * files (they need to be inserted in _wp_attachment_metadata database
                 */
                continue;
                foreach($files as $checking) {
                    if($modifiedFile == $checking) {
                        // Continue to the next parent loop
                        continue 2;
                    }
                }
            }
            $return[] = $file;
        }
        return $return;
    }

    protected function getCredentialPrefix() {
        $command = "AWS_ACCESS_KEY_ID={$this->settings['access-key-id']} ";
        $command .= "AWS_SECRET_ACCESS_KEY={$this->settings['secret-access-key']} ";
        return $command;
    }

    protected function getBucketData() {
        $settings = get_option('tantan_wordpress_s3');
        if(empty($settings)) {
            throw new \Exception("Bucket name not set");
        }
        $bucketData = new \stdClass();
        $bucketData->name = $settings['bucket'];
        $bucketData->region = $settings['region'];
        return $bucketData;
    }
}
