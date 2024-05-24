<?php
/*
Plugin Name: WooCommerce Social Share
Description: Adds social share buttons to WooCommerce product pages.
Version: 1.0.0
Author: Mahedi Hasan
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WCSocialShare {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('woocommerce_single_product_summary', array($this, 'display_share_buttons'), 50);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'WC Social Share',
            'Social Share',
            'manage_options',
            'wc-social-share',
            array($this, 'create_admin_page')
        );
    }

    public function register_settings() {
        register_setting('wc_social_share_options_group', 'wc_social_share_options', array($this, 'sanitize_settings'));
        add_settings_section('wc_social_share_main_section', 'Main Settings', null, 'wc-social-share');
        add_settings_field('platforms', 'Select Platforms', array($this, 'platforms_callback'), 'wc-social-share', 'wc_social_share_main_section');
        add_settings_field('custom_links_logos', 'Custom Links and Logos', array($this, 'custom_links_logos_callback'), 'wc-social-share', 'wc_social_share_main_section');
        add_settings_field('custom_css', 'Custom CSS', array($this, 'custom_css_callback'), 'wc-social-share', 'wc_social_share_main_section');
    }

    public function sanitize_settings($input) {
        $input['platforms'] = isset($input['platforms']) ? array_map('sanitize_text_field', $input['platforms']) : [];
        $input['custom_links_logos'] = isset($input['custom_links_logos']) ? array_map(function($item) {
            return [
                'link' => sanitize_text_field($item['link']),
                'logo' => esc_url_raw($item['logo']),
            ];
        }, $input['custom_links_logos']) : [];
        $input['custom_css'] = isset($input['custom_css']) ? sanitize_text_field($input['custom_css']) : '';
        return $input;
    }

    public function platforms_callback() {
        $options = get_option('wc_social_share_options');
        $platforms = isset($options['platforms']) ? $options['platforms'] : [];
        $all_platforms = ['Facebook', 'Twitter', 'Instagram', 'WhatsApp', 'Pinterest', 'Share Link', 'Custom'];

        foreach ($all_platforms as $platform) {
            $checked = in_array($platform, $platforms) ? 'checked' : '';
            echo "<label><input type='checkbox' name='wc_social_share_options[platforms][]' value='$platform' $checked> $platform</label><br>";
        }
    }

    public function custom_links_logos_callback() {
        $options = get_option('wc_social_share_options');
        $custom_links_logos = isset($options['custom_links_logos']) ? $options['custom_links_logos'] : [['link' => '', 'logo' => '']];
        foreach ($custom_links_logos as $index => $item) {
            echo "<div class='custom-link-logo-section'>";
            echo "<input type='url' name='wc_social_share_options[custom_links_logos][$index][link]' value='" . esc_url($item['link']) . "' placeholder='Custom Link' style='width: 60%; margin-right: 10px;'>";
            echo "<input type='hidden' name='wc_social_share_options[custom_links_logos][$index][logo]' value='" . esc_attr($item['logo']) . "'>";
            echo "<button type='button' class='upload_logo_button button' data-index='$index'>Upload Logo</button>";
            echo "<img src='" . esc_url($item['logo']) . "' class='custom-logo-preview' style='width: 24px; height: 24px; vertical-align: middle; margin-left: 10px;'>";
            echo "<button type='button' class='remove_custom_link_logo button' style='margin-left: 10px;'>Remove</button>";
            echo "</div><br>";
        }
        echo "<button type='button' id='add_custom_link_logo' class='button'>Add New Link</button>";
    }

    public function custom_css_callback() {
        $options = get_option('wc_social_share_options');
        $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';
        echo "<textarea name='wc_social_share_options[custom_css]' rows='5' cols='50'>$custom_css</textarea>";
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce Social Share Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_social_share_options_group');
                do_settings_sections('wc-social-share');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function add_meta_box() {
        add_meta_box('wc_social_share_meta', 'Social Share Settings', array($this, 'meta_box_callback'), 'product', 'side', 'high');
    }

    public function meta_box_callback($post) {
        wp_nonce_field('wc_social_share_save_meta_box_data', 'wc_social_share_meta_box_nonce');

        $value = get_post_meta($post->ID, '_wc_social_share_enabled', true);
        echo '<label for="wc_social_share_enabled">';
        echo '<input type="checkbox" id="wc_social_share_enabled" name="wc_social_share_enabled" value="1" ' . checked(1, $value, false) . '> Enable social share buttons';
        echo '</label>';
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['wc_social_share_meta_box_nonce']) || !wp_verify_nonce($_POST['wc_social_share_meta_box_nonce'], 'wc_social_share_save_meta_box_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['wc_social_share_enabled']) ? 1 : 0;
        update_post_meta($post_id, '_wc_social_share_enabled', $enabled);
    }

    public function enqueue_styles() {
        wp_enqueue_style('wc-social-share', plugin_dir_url(__FILE__) . 'css/wc-social-share.css');
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_wc-social-share') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('wc-social-share-admin', plugin_dir_url(__FILE__) . 'js/wc-social-share-admin.js', array('jquery'), null, true);
    }

    public function display_share_buttons() {
        global $post;
        $enabled = get_post_meta($post->ID, '_wc_social_share_enabled', true);
        if ($enabled) {
            $options = get_option('wc_social_share_options');
            $platforms = isset($options['platforms']) ? $options['platforms'] : [];
            $custom_links_logos = isset($options['custom_links_logos']) ? $options['custom_links_logos'] : [];
            $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';

            echo '<div class="wc-social-share" style="' . esc_attr($custom_css) . '">';
            echo '<p>Share this product:</p>';
            foreach ($platforms as $platform) {
                $url = get_permalink($post->ID);
                $title = get_the_title($post->ID);
                $share_url = '';
                $icon = '';

                switch ($platform) {
                    case 'Facebook':
                        $share_url = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url);
                        $icon = plugin_dir_url(__FILE__) . 'images/facebook.svg';
                        break;
                    case 'Twitter':
                        $share_url = 'https://twitter.com/intent/tweet?text=' . urlencode($title) . '&url=' . urlencode($url);
                        $icon = plugin_dir_url(__FILE__) . 'images/twitter.svg';
                        break;
                    case 'Instagram':
                        $share_url = 'https://www.instagram.com/?url=' . urlencode($url);
                        $icon = plugin_dir_url(__FILE__) . 'images/instagram.svg';
                        break;
                    case 'WhatsApp':
                        $share_url = 'https://api.whatsapp.com/send?text=' . urlencode($title) . ' ' . urlencode($url);
                        $icon = plugin_dir_url(__FILE__) . 'images/whatsapp.svg';
                        break;
                    case 'Pinterest':
                        $share_url = 'https://pinterest.com/pin/create/button/?url=' . urlencode($url) . '&description=' . urlencode($title);
                        $icon = plugin_dir_url(__FILE__) . 'images/pinterest.svg';
                        break;
                    case 'Share Link':
                        $share_url = 'mailto:?subject=' . urlencode($title) . '&body=' . urlencode($url);
                        $icon = plugin_dir_url(__FILE__) . 'images/share.svg';
                        break;
                    case 'Custom':
                        foreach ($custom_links_logos as $custom) {
                            $share_url = esc_url($custom['link']);
                            $icon = esc_url($custom['logo']);
                            echo '<a href="' . esc_url($share_url) . '" target="_blank" class="wc-social-share-button wc-social-share-custom">';
                            echo '<img src="' . esc_url($icon) . '" alt="Custom">';
                            echo '</a>';
                        }
                        break;
                }

                if ($platform !== 'Custom') {
                    $class = strtolower(str_replace(' ', '-', $platform));
                    echo '<a href="' . esc_url($share_url) . '" target="_blank" class="wc-social-share-button wc-social-share-' . esc_attr($class) . '">';
                    echo '<img src="' . esc_url($icon) . '" alt="' . esc_attr($platform) . '">';
                    echo '</a>';
                }
            }
            echo '</div>';
        }
    }
}

new WCSocialShare();
?>
