<?php namespace Grrr\SimplyStaticDeploy\Aws;

use Aws\Sdk;
use Aws\S3\S3Client;
use Grrr\SimplyStaticDeploy\Config;
use Aws\CloudFront\CloudFrontClient;

class ClientProvider {

    protected $_sdk;

    public function __construct(Config $config) {
        $credentials = (new CredentialsProvider($config->key, $config->secret))->getCredentials();
        $this->_sdk = new Sdk([
            'credentials' => $credentials,
            'region' => $config->region,
            'version' => 'latest',
            'http' => [
                'timeout' => 30,
            ],
        ]);
    }

    public function getS3Client(): S3Client {
        return $this->_sdk->createS3();
    }

    public function getCloudFrontClient(): CloudFrontClient {
        return $this->_sdk->createCloudFront();
    }

}
