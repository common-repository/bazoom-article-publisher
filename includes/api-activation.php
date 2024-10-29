<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function bazoom_article_is_api_activated() {
    $bazoom_article_api_key = get_option('bazoom_article_api_key');
    return ! empty($bazoom_article_api_key);
}


function bazoom_article_render_api_activation_page($bazoom_article_activation_message = '') {
    $bazoom_article_status = bazoom_article_is_api_activated() ? 'Active' : 'Inactive';
    $bazoom_article_status_color = bazoom_article_is_api_activated() ? 'green' : 'red';

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You are not allowed to access this page.', 'bazoom-article-publisher') );
    }

    ?>
    <div>
        <h1>Welcome to Article Publisher!</h1>
        <hr>
        <h3>Status: <span style="color: <?php echo esc_attr($bazoom_article_status_color); ?>"><?php echo esc_html($bazoom_article_status); ?></span></h3>
        <div class="api-activation-page">
            <h2>Enter Your Bazoom Content Publication Key here</h2>
            <p>
                For an effortless and seamless experience, please enter your unique Bazoom Content Publication Key, which is instrumental in activating our self-publication feature. 
            </p>
            <p>
                You might wonder, "Where do I find this key?" It's simple! Your unique key can be found in the Media Portal under your profile. Remember, this key is exclusive to you and only operational on this website
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=api-activation')); ?>">
                <?php wp_nonce_field( 'bazoom_article_api_key', 'bazoom_article_api_key_nonce' ); ?>
                <input type="hidden" name="action" value="activate_api">
                <label for="bazoom_article_api_key">API Key:</label>
                <input type="text" name="bazoom_article_api_key" id="bazoom_article_api_key" placeholder="Enter your API key" required>
                <table>
                    <tbody>
                        <tr>
                        <td>
                            <input type="submit" id="bazoom_article_activate_button" name="bazoom_article_activate_button" value="Activate">
                        </td>
                        <td width="20px"></td>
                        <td>
                        <a class="personal_key" href="https://media.bazoom.com/?wordpressplugin=${websiteDomain}" target="_blank">Get your personal key</a>
                        </td>
                    </tr>
                    </tbody>
                </table>
                    <p>By entering your exclusive Bazoom Content Publication Key, you'll enable the automatic publication of content from Bazoom to your website. You are in complete control - you can activate or deactivate this feature at any time.</p>
                    <p>

                    You have the power to decide whether we publish immediately, or if we should create a draft for your review and approval first. 
                    </p> 
            </form>
            <div id="bazoom_article_activation_message"></div>
            <?php echo esc_html($bazoom_article_activation_message); ?>
            <div class="setting">
                <h2>General Settings</h2>
                <hr>
                <!-- <div class="form-group">
                
                </div> -->
                <!--  Default Categories -->
                <div class="form-group">
                    <label for="category">Please choose a default category under which Bazoom will publish the content. This helps you maintain a consistent theme and format on your website</label>
                    <?php
                    $category = get_option('bazoom_article_category');
                    $categories = get_terms( 'category');
                    ?>
                    <select class="form-select" name="category" id="category">
                        <option value="">-- Select a category --</option>
                        <?php
                        foreach ($categories as $cat) {
                            $selected = $category === $cat->name ? 'selected' : '';
                            echo '<option value="' . esc_attr($cat->name) . '" ' . esc_attr($selected) . '>' . esc_html($cat->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <!-- For API -->
                <button type="button" id="bazoom_article_save_settings"> Save Settings </button>
                <div id="save_message"></div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var apiKeyInput = document.getElementById('bazoom_article_api_key');
            var activateBtn = document.getElementById('bazoom_article_activate_button');
            var isApiActivated = <?php echo bazoom_article_is_api_activated() ? 'true' : 'false'; ?>;
            if (isApiActivated) {
                apiKeyInput.disabled = true;
                activateBtn.disabled = true;
                activateBtn.value = "Activated"
                apiKeyInput.value = '************************';
            }
        });


        document.addEventListener('DOMContentLoaded', function() {
    // Key Activation
            document.getElementById('bazoom_article_activate_button').addEventListener('click', function(event) {
                event.preventDefault();
                var activationUrl = "https://article-plugin.bazoom.net/v1/status?action=activate";
                var apiKey = document.getElementById('bazoom_article_api_key').value;
                var activationMessage = document.getElementById('bazoom_article_activation_message');
                if (apiKey.trim() === '') {
                    return;
                }
                jQuery.ajax({
                    url: activationUrl,
                    headers: {
                        "x-api-key": apiKey
                    },
                    success: function(response, textStatus, jqXHR) {
                        if (jqXHR.status === 200) {
                            activationMessage.innerHTML = '<p class="activation-message success">API activated successfully!</p>';
                            updateApiKeyInDatabase(apiKey);
                            sendCategoriesList(apiKey);
                        }
                    },
                    error: function(error) {
                        activationMessage.innerHTML = '<p class="activation-message error">API activation failed. Please check your API key and try again.</p>';
                        document.getElementById('bazoom_article_api_key').value = ''
                    }
                });
            });
        });

        function updateApiKeyInDatabase(apiKey) {
            jQuery.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                method: 'POST',
                data: {
                    action: 'bazoom_article_update_api_key',
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('bazoom_article_update_api_key')); ?>',
                    api_key: apiKey
                },
                success: function(response) {
                    location.reload(true);
                },
                error: function(error) {
                }
            });
        }


        function sendCategoriesList(apiKey) {
            var apiActivationUrl = "https://article-plugin.bazoom.net/v1/categories";
            var categoriesList = <?php echo wp_json_encode(get_terms( 'category')); ?>;
            categoriesList = categoriesList.map(function(category) {
            return category.name});
            jQuery.ajax({
                url: apiActivationUrl,
                headers: {
                    "x-api-key": apiKey,
                    "Content-Type": "application/json"
                },
                method: "POST",
                data: JSON.stringify({
                    "categories_list": categoriesList
                }),
                success: function(response) {
                    document.getElementById('bazoom_article_api_key').value = ''
                    location.reload();
                },
                error: function(error) {
                    console.log("somthing wrong", apiKey, categoriesList);
                }
            });
        }
    </script>
    <?php
}

function bazoom_article_handle_api_activation() {
    $bazoom_article_activation_message = '';
    bazoom_article_render_api_activation_page($bazoom_article_activation_message);
}

function bazoom_article_save_settings() {
    check_ajax_referer( 'bazoom_article_save_settings', '_wpnonce' );
    if (!current_user_can('edit_posts')) {
        wp_send_json_error();
    }
    if (isset($_POST['bazoom_category'])) {
        $bazoom_article_category = sanitize_text_field($_POST['bazoom_category']);
        update_option('bazoom_article_category', $bazoom_article_category);
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

// saving settings
add_action('admin_footer', 'bazoom_article_publisher_save_settings_script');
add_action('admin_enqueue_scripts', 'bazoom_article_enqueue_custom_styles');


function bazoom_article_publisher_save_settings_script() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('bazoom_article_save_settings').addEventListener('click', function () {
                var settingMessage = document.getElementById('save_message');
                var category = document.getElementById('category').value;
                var settingUrl = "https://article-plugin.bazoom.net/v1/default_settings"

                // sending setting requst
                jQuery.ajax({
                url: settingUrl,
                headers: {
                    "x-api-key": "<?php echo esc_js(get_option('bazoom_article_api_key')) ?>",
                    "Content-Type": "application/json"
                },
                method: "POST",
                data: JSON.stringify({
                    "default_category": category,
                    "is_public_publish": '1'
                }),
                success: function(response) {
                    
                },
                error: function(error) {
                    console.log("somthing wrong");
                }
            });

            // saving default setting
                jQuery.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    method: 'POST',
                    data: {
                        action: 'bazoom_article_save_settings',
                        _wpnonce: '<?php echo esc_js(wp_create_nonce('bazoom_article_save_settings')); ?>',
                        bazoom_category: category,
                    },
                    success: function(response) {
                        if (response.success) {
                            settingMessage.innerHTML = '<p class="activation-message success">Settings saved successfully!</p>';
                        } else {
                            settingMessage.innerHTML = '<p class="activation-message error">Failed to save settings.</p>';
                        }
                    },
                    error: function(error) {
                        settingMessage.innerHTML = '<p class="activation-message error">Failed to save settings.</p>';
                    }
                })
            });
        });
    </script>
    <?php
}

add_action('wp_ajax_bazoom_article_update_api_key', 'bazoom_article_update_api_key');
add_action('wp_ajax_nopriv_bazoom_article_update_api_key', 'bazoom_article_update_api_key');

function bazoom_article_update_api_key() {
    check_ajax_referer( 'bazoom_article_update_api_key', '_wpnonce' );
    if (!current_user_can('edit_posts')) {
        wp_send_json_error();
    }
    if (isset($_POST['api_key'])) {
        $bazoom_article_api_key = sanitize_text_field($_POST['api_key']);
        update_option('bazoom_article_api_key', $bazoom_article_api_key);
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
