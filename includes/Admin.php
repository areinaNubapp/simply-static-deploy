<?php namespace Grrr\SimplyStaticDeploy;

use Grrr\SimplyStaticDeploy\Utils\Renderer;
use WP_Post;

class Admin
{
    const SLUG = 'simply-static-deploy';
    const JS_GLOBAL = 'SIMPLY_STATIC_DEPLOY';

    const DEPLOY_FORM_ID = 'ssd-single-deploy-form';

    private $basePath;
    private $baseUrl;
    private $version;
    private $config;

    public function __construct(
        Config $config,
        string $basePath,
        string $baseUrl,
        string $version
    ) {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->version = $version;
    }

    public function register()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_before_admin_bar_render', [$this, 'admin_bar']);
    }

    public function admin_bar()
    {
        global $wp_admin_bar;
        $wp_admin_bar->add_node([
            'id' => static::SLUG,
            'title' => 'Deploy',
            'href' => admin_url() . 'admin.php?page=' . static::SLUG,
        ]);
    }

    public function admin_menu()
    {
        add_menu_page(
            'Deploy Website',
            'Deploy',
            'edit_posts',
            static::SLUG,
            [$this, 'render_admin'],
            $this->get_icon('rocket.svg')
        );
    }

    public function register_assets()
    {
        wp_register_style(
            static::SLUG,
            $this->get_asset_url('admin.css'),
            [],
            $this->version
        );
        wp_register_script(
            static::SLUG,
            $this->get_asset_url('admin.js'),
            ['jquery'],
            $this->version
        );
        wp_localize_script(static::SLUG, static::JS_GLOBAL, [
            'api' => [
                'nonce' => wp_create_nonce('wp_rest'),
                'endpoints' => $this->get_endpoints(),
            ],
            'tasks' => $this->get_tasks(),
            'website' => trim($this->config->url, '/') . '/',
        ]);

        // Use style on every admin page so we can overwrite the css of Simply Static
        wp_enqueue_style(static::SLUG);
    }

    public function render_admin()
    {
        wp_enqueue_script(static::SLUG);

        $deployForm = (object) [
            'action' => $this->get_endpoints()['simply_static_deploy'],
            'method' => 'post',
        ];
        $invalidateForm = (object) [
            'action' => $this->get_endpoints()['invalidate_cloudfront'],
            'method' => 'post',
        ];

        $renderer = new Renderer($this->basePath . 'views/admin-page.php', [
            'forms' => [
                'deploy' => $deployForm,
                'invalidate' => $invalidateForm,
            ],
            'in_progress' => !StaticDeployJob::is_job_done(),
            'last_end_time' => StaticDeployJob::last_end_time(),
        ]);
        $renderer->render();
    }

    private function get_tasks(): array
    {
        $tasks = ['generate', 'sync'];
        if ($this->config->aws->distribution) {
            $tasks[] = 'invalidate';
        }
        return $tasks;
    }

    private function get_last_time($timestamp): string
    {
        $tz = get_option('timezone_string') ?: date_default_timezone_get();
        date_default_timezone_set($tz);
        return $timestamp ? date_i18n('j F H:i', $timestamp) : '';
    }

    private function get_asset_url(string $filename): string
    {
        return rtrim($this->baseUrl, '/') . '/assets/' . $filename;
    }

    private function get_icon(string $filename): string
    {
        $icon = $this->basePath . 'assets/' . $filename;
        return 'data:image/svg+xml;base64,' .
            base64_encode(file_get_contents($icon));
    }

    private function get_endpoints()
    {
        $out = [];
        foreach (Api::ENDPOINT_MAPPER as $endpoint => $callback) {
            $out[$endpoint] = RestRoutes::url($endpoint);
        }
        return $out;
    }
}
