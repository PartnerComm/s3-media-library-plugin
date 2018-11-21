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
            }
        } catch (\Exception $e) {
            $this->result .= "Encountered unhandled error: ". $e->getMessage() ."\n";
        }
        return $this->result;
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
            // Check to make sure that the file exists
            if($modifiedFile != $file) {
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
