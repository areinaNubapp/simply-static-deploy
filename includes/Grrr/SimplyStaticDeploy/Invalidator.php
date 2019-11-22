<?php namespace Grrr\SimplyStaticDeploy;

use WP_Error;
use Garp\Functional as f;

class Invalidator {

    const OPTION_TIMESTAMP_KEY = 'grrr_simply_static_deploy_invalidated_at';

    private $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * Invalidate full CloudFront distribution.
     *
     * @return WP_Error|bool
     */
    public function invalidate() {
        $clientProvider = new Aws\ClientProvider($this->config);
        $invalidation = new Aws\CloudFront\Invalidation(
            $clientProvider->getCloudFrontClient(),
            $this->config->distribution
        );
        $result = $invalidation->invalidate(['/*']);

        if (!$result instanceof WP_Error) {
            update_option(self::OPTION_TIMESTAMP_KEY, time());
        }

        return $result;
    }

    /**
     * Return the last invalidation time.
     *
     * @return string
     */
    public static function get_last_time(): string {
        return get_option(self::OPTION_TIMESTAMP_KEY) ?: '';
    }

}
