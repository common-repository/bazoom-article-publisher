<?php
/**
 * Plugin Name: Bazoom Article Publisher
 * Plugin URI: https://bazoom.com/
 * Author: Bazoom Group
 * Author URI: https://bazoom.com/
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bazoom-article-publisher
 * Description: Article Publisher API Plugin is a WordPress plugin that allows developers to insert images and HTML-formatted text into the WordPress database via a REST API endpoint.
 */

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Insert image and text API endpoint
require_once plugin_dir_path(__FILE__) . 'includes/insert-image-and-text.php';

// API activation page
require_once plugin_dir_path(__FILE__) . 'includes/api-activation.php';

// Enqueue the CSS & JS file

if ( ! function_exists( 'bazoom_article_enqueue_custom_styles' ) ) {
    function bazoom_article_enqueue_custom_styles() {
        wp_enqueue_style(
            'bazoom-article-publisher-styles',
            plugins_url('css/style.css', __FILE__),
        );
        wp_enqueue_script(
            'bazoom-article-publisher-script',
            plugins_url('js/script.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );
        wp_localize_script(
            'bazoom-article-publisher-script', 
            'websiteDomain', 
            esc_js(parse_url(get_site_url(), PHP_URL_HOST))
        );
    }
}

add_action('admin_enqueue_scripts', 'bazoom_article_enqueue_custom_styles');

function bazoom_article_publisher_action_links($links) {
    // Add the "Settings" link
    $settings_link = '<a href="' . admin_url('admin.php?page=bazoom-api-activation') . '">Settings</a>';
    array_push($links, $settings_link);

    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bazoom_article_publisher_action_links');

// Register custom API activation page
function bazoom_article_publisher_register_api_activation_page() {
    add_menu_page(
        'Article Publisher',
        'Article Publisher',
        'manage_options',
        'bazoom-api-activation',
        'bazoom_article_handle_api_activation',
        'https://ai.bazoom.com/favicon/favicon.ico',
        80
    );
}

// Hook into admin actions
add_action( 'admin_menu', 'bazoom_article_publisher_register_api_activation_page');

add_action('wp_ajax_bazoom_article_save_settings', 'bazoom_article_save_settings');
add_action('wp_ajax_nopriv_bazoom_article_save_settings', 'bazoom_article_save_settings');

// Register activation hook
register_activation_hook(__FILE__, 'bazoom_article_publisher_plugin_activation');

// Register deactivation hook
register_deactivation_hook(__FILE__, 'bazoom_article_publisher_plugin_deactivation');

// Plugin activation hook
function bazoom_article_publisher_plugin_activation()
{
    $bazoom_article_api_key = get_option('bazoom_article_api_key');
    if (!empty($bazoom_article_api_key)) {
        bazoom_article_publisher_call_api_endpoint('plugin-installed-activated', $bazoom_article_api_key);
    }
}

// Plugin deactivation hook
function bazoom_article_publisher_plugin_deactivation()
{
    $bazoom_article_api_key = get_option('bazoom_article_api_key');
    if (!empty($bazoom_article_api_key)) {
        bazoom_article_publisher_call_api_endpoint('plugin-uninstalled-deactivated', $bazoom_article_api_key);
        delete_option('bazoom_article_api_key');
        delete_option('bazoom_article_can_publish');
        delete_option('bazoom_article_category');
    }
}

// API call
function bazoom_article_publisher_call_api_endpoint($action, $bazoom_article_api_key)
{
    $url = 'https://article-plugin.bazoom.net/v1/status?action=' . $action;

    $args = array(
        'headers' => array(
            'x-api-key' => $bazoom_article_api_key
        )
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        return;
    }
}