<?php

namespace PComm\S3MediaLibrary;

class Controller {

    protected $result = "Starting...\n";
    protected $settings;
    protected $bucketData;

    public function run($settings) {
        try {
            $this->settings = $settings;
            $this->bucketData = $this->getBucketData();
            $this->getFiles();
        } catch (\Exception $e) {
            $this->result .= "Encountered unhandled error: ". $e->getMessage();
        }
        return $this->result;
    }

    protected function getFiles() {
        $command = $this->getCredentialPrefix();
        $command .= "aws s3 ls";
        var_dump(shell_exec($command));
    }

    protected function getCredentialPrefix() {
        $command = "AWS_ACCESS_KEY_ID={$this->settings['access-key-id']} ";
        $command .= "AWS_SECRET_ACCESS_KEY={$this->settings['secret-access-key']} ";
        return $command;
    }

    protected function getBucketData() {
        $settings = get_option('_site_transient_as3cf_regions_cache');
        if(empty($settings)) {
            throw new \Exception("Bucket name not set");
        }
        var_dump($settings);
        $bucketData = new \stdClass();
        foreach($settings as $name => $region) {
            $bucketData->name = $name;
            $bucketData->region = $region;
            // There should only be one result
            break;
        }
        return $bucketData;
    }
}
