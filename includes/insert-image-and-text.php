<?php
/**
 * Inserts an image and HTML-formatted text into the WordPress database via a REST API endpoint.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return array|WP_Error The response data or WP_Error object on failure.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function get_data($url) {
    $response = wp_remote_get($url, array('timeout' => 30));
    if (is_wp_error($response)) {
        return false;
    }
    return wp_remote_retrieve_body($response);
}

function bazoom_article_insert_image_and_text(WP_REST_Request $request) {
    if (bazoom_article_is_api_activated()) {
        $image_url = $request->get_param('image_url');
        $text_html = $request->get_param('text_html');
        $post_title = $request->get_param('title');
        $category = get_option('bazoom_article_category');

        // Verify that both image URL and text HTML are provided.
        if (empty($image_url) || empty($text_html)) {
            return new WP_Error(
                '400',
                __('Parameters Missing. Both image URL and text HTML are required.', 'bazoom-article-publisher'),
                array('status' => 400)
            );
        }

        // Insert the post with the HTML-formatted text.
        $post_status = 'publish';
        $category_id = get_cat_ID( $category );
        $post_id = wp_insert_post(array(
            'post_type' => 'post',
            'post_title' => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($text_html),
            'post_status' => $post_status,
            'post_category' => array($category_id)
        ));

        // Check if the image URL is valid and get the image data.
        // $image_data = file_get_contents($image_url);
        
        $file_type = wp_check_filetype(basename($image_url), null);

        // Verify that the file type is allowed.
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($file_type['ext'], $allowed_types)) {
            return new WP_Error(
                '400',
                __('Invalid file type. Allowed file types: jpg, jpeg, png, gif.', 'bazoom-article-publisher'),
                array('status' => 400)
            );
        }
        $image_data = get_data($image_url);

        if ($image_data !== false) {
        // Upload the image and create the attachment.
        $upload = wp_upload_bits(basename($image_url), null, $image_data);

        if (!$upload['error']) {
            $filename = $upload['file'];
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $post_id,
            );

            $attachment_id = wp_insert_attachment($attachment, $filename, $post_id);

            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $attachment_data = wp_generate_attachment_metadata($attachment_id, $filename);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
        }

        if (is_wp_error($attachment_id)) {
            return new WP_Error(
                '500',
                __('Failed to upload media file.', 'bazoom-article-publisher'),
                array('status' => 500)
            );
        }

        if (is_wp_error($post_id)) {
            wp_delete_attachment($attachment_id);
            return new WP_Error(
                '500',
                __('Failed to insert post.', 'bazoom-article-publisher'),
                array('status' => 500)
            );
        }

        // Associate the image attachment ID with the post.
        add_post_meta($post_id, '_thumbnail_id', $attachment_id);

        // And finally assign featured image to post
        set_post_thumbnail( $post_id, $attachment_id );

        // Retrieve the post URL
        $post_link = get_permalink($post_id);

        // Return the success response.
        return array(
            'status' => 'success',
            'message' => 'Post successfully saved as ' . $post_status . '.',
            'post_link' => $post_link,
            'link_status' => $post_status == 'publish' ? 1 : 0,
        );
    } else {
        // API is not activated, return an error response or take alternative actions
        return new WP_Error(
            '403',
            __('API is not activated.', 'bazoom-article-publisher'),
            array('status' => 403)
        );
    }
}

add_action('rest_api_init', function () {
    register_rest_route('plugin/v1', '/insert', array(
        'methods' => 'POST',
        'callback' => 'bazoom_article_insert_image_and_text',
        'permission_callback' => 'bazoom_article_api_authentication_callback',
    ));
});

function bazoom_article_api_authentication_callback()
{
    // authorization
    $bazoom_article_api_key = sanitize_text_field($_SERVER['HTTP_AUTHORIZATION']);
    
    // host
    $host = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    
    // whitelist check
    $source_response = bazoom_article_call_source_endpoint($bazoom_article_api_key, $host);

    if (!$source_response || $source_response['success'] !== true || !bazoom_article_is_valid_api_key($bazoom_article_api_key)) {
        return new WP_Error(
            '401',
            __('Unauthorized', 'bazoom-article-publisher'),
            array('status' => 401)
        );
    }

    return true;
}

// validation 
function bazoom_article_is_valid_api_key($server_key) {
    $bazoom_article_api_key = get_option('bazoom_article_api_key');
    return $bazoom_article_api_key === $server_key ? true : false;
}

function bazoom_article_call_source_endpoint($bazoom_article_api_key, $host) {
    
    $source_endpoint = 'https://article-plugin.bazoom.net/v1/source?ip_address=' . $host;

    $response = wp_remote_get($source_endpoint, array(
        'headers' => array(
            'x-api-key' => $bazoom_article_api_key,
        ),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}