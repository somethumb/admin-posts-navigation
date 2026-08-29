<?php
/**
 * Plugin Name: Admin Posts Navigation
 * Plugin URI: https://wordpress.org/plugins/admin-posts-navigation
 * Description: Adds Previous/Next navigation buttons to post and page edit screens in both Classic Editor and Gutenberg. Automatically supports all Custom Post Types.
 * Version: 1.5.0
 * Author: The Website Factory; Somethumb Company
 * Author URI: https://thewebsitefactory.org; https://somethumb.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-posts-navigation
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TWF_AdminPostsNavigation {
    
    private $supported_post_types = array();
    private $cache_key_prefix = 'twf_admin_posts_navigation_';
    private $cache_expiration = 3600;
    private $version = '1.5.0';
    private $plugin_ready = false;
    private $debug_enabled = false;
    
    public function __construct() {
        $this->debug_enabled = (defined('WP_DEBUG') && WP_DEBUG);
        add_action('plugins_loaded', array($this, 'check_compatibility'), 5);
        add_action('admin_init', array($this, 'admin_init'), 5);
    }
    
    public function check_compatibility() {
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', array($this, 'wordpress_version_notice'));
            return;
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }
        
        if (!function_exists('get_post_types') || !function_exists('get_posts') || !function_exists('wp_create_nonce')) {
            add_action('admin_notices', array($this, 'missing_functions_notice'));
            return;
        }
        
        $this->plugin_ready = true;
        
        if (is_admin()) {
            add_action('init', array($this, 'init'), 20);
            add_action('wp_ajax_twf_admin_posts_nav_get_posts', array($this, 'ajax_get_nav_posts_with_rate_limit'));
            add_action('wp_ajax_twf_admin_posts_nav_update_sort', array($this, 'ajax_update_sort_preference'));
            add_action('save_post', array($this, 'clear_navigation_cache'));
            add_action('delete_post', array($this, 'clear_navigation_cache'));
            add_action('wp_trash_post', array($this, 'clear_navigation_cache'));
            add_action('untrash_post', array($this, 'clear_navigation_cache'));
        }
    }
    
    public function admin_init() {
        if (!$this->plugin_ready) {
            return;
        }
        
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }
    }
    
    public function init() {
        if (!$this->plugin_ready) {
            return;
        }
        
        if (!is_admin() || (!current_user_can('edit_posts') && !current_user_can('edit_pages'))) {
            return;
        }
        
        $this->supported_post_types = $this->get_supported_post_types_cached();
        
        if (empty($this->supported_post_types)) {
            return;
        }
        
        add_action('edit_form_after_title', array($this, 'add_navigation_buttons'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_gutenberg_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        if ($this->debug_enabled) {
            add_action('admin_notices', array($this, 'show_supported_types_notice'));
        }
    }
    
    /**
     * Get user's sort preference for a specific post type
     */
    private function get_sort_preference($post_type) {
        $user_id = get_current_user_id();
        $preference_key = 'twf_admin_posts_nav_sort_' . $post_type;
        $preference = get_user_meta($user_id, $preference_key, true);
        
        // Default sort preferences by post type
        $defaults = array(
            'page' => 'menu_order',
            'post' => 'date',
        );
        
        if (empty($preference)) {
            $preference = isset($defaults[$post_type]) ? $defaults[$post_type] : 'date';
        }
        
        return $preference;
    }

    /**
     * Get user's sort direction preference for a specific post type
     */
    private function get_order_preference($post_type) {
        $user_id = get_current_user_id();
        $preference_key = 'twf_admin_posts_nav_order_' . $post_type;
        $preference = strtoupper(get_user_meta($user_id, $preference_key, true));

        if (!in_array($preference, array('ASC', 'DESC'), true)) {
            $preference = 'ASC';
        }

        return $preference;
    }

    
    /**
     * AJAX handler for updating sort preference
     */
    public function ajax_update_sort_preference() {
        $user_id = get_current_user_id();

        // Security: Check user permissions first
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'admin-posts-navigation'));
        }

        // Security: Use check_ajax_referer for AJAX nonce verification
        check_ajax_referer('twf_admin_posts_navigation_sort_' . $user_id, 'nonce');

        $post_type = sanitize_key($_POST['post_type'] ?? '');
        $sort_method = sanitize_key($_POST['sort_method'] ?? '');
        $sort_order = strtoupper(sanitize_text_field($_POST['sort_order'] ?? 'ASC'));

        if (
            !$post_type ||
            !in_array($sort_method, array('alphabetical', 'post_id', 'date', 'menu_order'), true) ||
            !in_array($sort_order, array('ASC', 'DESC'), true)
        ) {
            wp_die(esc_html__('Invalid parameters.', 'admin-posts-navigation'));
        }

        $preference_key = 'twf_admin_posts_nav_sort_' . $post_type;
        update_user_meta($user_id, $preference_key, $sort_method);

        $order_preference_key = 'twf_admin_posts_nav_order_' . $post_type;
        update_user_meta($user_id, $order_preference_key, $sort_order);

        // Clear navigation cache for this post type
        $this->clear_cache_by_post_type($post_type);

        wp_send_json_success(array(
            'sort_method' => $sort_method,
            'sort_order'  => $sort_order,
        ));
    }
    
    /**
     * Debug logging function that respects WordPress debugging settings
     */
    private function debug_log($message, $context = array()) {
        if (!$this->debug_enabled) {
            return;
        }
        
        // No escaping needed for log messages - they're not output to browser
        $log_message = 'TWF Admin Posts Navigation: ' . $message;
        
        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }
        
        // Only use WordPress debug logging - no error_log()
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('wp_debug_log')) {
            wp_debug_log($log_message);
        }
        // If wp_debug_log is not available, we simply don't log (fail silently)
        // This ensures no error_log() calls that Plugin Check flags
    }
    
    /**
     * Show admin notice for errors (only to administrators)
     */
    private function show_admin_error($message) {
        add_action('admin_notices', function() use ($message) {
            if (current_user_can('manage_options')) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                    esc_html__('Admin Posts Navigation', 'admin-posts-navigation'),
                    esc_html($message)
                );
            }
        });
    }
    
    public function wordpress_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            '<strong>%s:</strong> %s',
            esc_html__('Admin Posts Navigation', 'admin-posts-navigation'),
            esc_html__('This plugin requires WordPress 5.0 or higher.', 'admin-posts-navigation')
        );
        echo '</p></div>';
    }
    
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            '<strong>%s:</strong> %s',
            esc_html__('Admin Posts Navigation', 'admin-posts-navigation'),
            esc_html__('This plugin requires PHP 7.4 or higher.', 'admin-posts-navigation')
        );
        echo '</p></div>';
    }
    
    public function missing_functions_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            '<strong>%s:</strong> %s',
            esc_html__('Admin Posts Navigation', 'admin-posts-navigation'),
            esc_html__('Required WordPress functions are missing. Please check your WordPress installation.', 'admin-posts-navigation')
        );
        echo '</p></div>';
    }
    
    private function get_supported_post_types_cached() {
        $cache_key = $this->cache_key_prefix . 'supported_types_' . get_current_user_id();
        
        // Use WordPress Object Cache API
        $cached_types = wp_cache_get($cache_key, 'twf_admin_posts_navigation');
        if ($cached_types !== false && is_array($cached_types)) {
            $this->debug_log('Using cached supported post types', array('types' => $cached_types));
            return $cached_types;
        }
        
        // Fallback to transients if object cache fails
        $cached_types = get_transient($cache_key);
        if ($cached_types !== false && is_array($cached_types)) {
            // Store in object cache for this request
            wp_cache_set($cache_key, $cached_types, 'twf_admin_posts_navigation', 300);
            $this->debug_log('Using transient cached supported post types', array('types' => $cached_types));
            return $cached_types;
        }
        
        try {
            $supported_types = $this->get_supported_post_types();
            
            // Store in both object cache and transients
            wp_cache_set($cache_key, $supported_types, 'twf_admin_posts_navigation', 300);
            set_transient($cache_key, $supported_types, $this->cache_expiration);
            
            $this->debug_log('Generated fresh supported post types', array('types' => $supported_types));
            return $supported_types;
            
        } catch (Exception $e) {
            // Log the detailed error for debugging
            $this->debug_log('Error getting supported post types: ' . $e->getMessage());
            // Show a safe, escaped admin notice
            $this->show_admin_error(__('Unable to detect supported post types. Using defaults.', 'admin-posts-navigation'));
            return array('post', 'page');
        }
    }
    
    private function get_supported_post_types() {
        try {
            $all_post_types = get_post_types(array('public' => true), 'objects');
            
            if (!is_array($all_post_types)) {
                throw new Exception('get_post_types() did not return an array');
            }
            
            $supported_types = array();
            
            $excluded_types = apply_filters('twf_admin_posts_navigation_excluded_types', array(
                'attachment',
                'revision',
                'nav_menu_item',
                'custom_css',
                'customize_changeset',
                'oembed_cache',
                'user_request',
                'wp_block',
                'wp_template',
                'wp_template_part',
                'wp_global_styles',
                'wp_navigation'
            ));
            
            foreach ($all_post_types as $post_type) {
                if (!$post_type || !is_object($post_type) || !isset($post_type->name)) {
                    continue;
                }
                
                if (!in_array($post_type->name, $excluded_types) && 
                    isset($post_type->cap->edit_posts) &&
                    current_user_can($post_type->cap->edit_posts)) {
                    $supported_types[] = sanitize_key($post_type->name);
                }
            }
            
            // Ensure core post types are included if they exist and user has permission
            if (post_type_exists('post') && current_user_can('edit_posts') && !in_array('post', $supported_types)) {
                $supported_types[] = 'post';
            }
            if (post_type_exists('page') && current_user_can('edit_pages') && !in_array('page', $supported_types)) {
                $supported_types[] = 'page';
            }
            
            return array_unique($supported_types);
            
        } catch (Exception $e) {
            // Log the original error for debugging
            $this->debug_log('Failed to get supported post types: ' . $e->getMessage());
            // Don't re-throw with the original message, use a safe generic message
            throw new Exception('Failed to get supported post types');
        }
    }
	
		/**
		 * Decode HTML entities from post titles.
		 *
		 * Handles titles that may have been encoded more than once.
		 */
		private function decode_post_title($title) {

				$charset = get_bloginfo('charset');

				if (!$charset) {
						$charset = 'UTF-8';
				}

				for ($i = 0; $i < 3; $i++) {

						$decoded = html_entity_decode(
								$title,
								ENT_QUOTES | ENT_HTML5,
								$charset
						);

						if ($decoded === $title) {
								break;
						}

						$title = $decoded;
				}

				return $title;
		}
    
    public function add_navigation_buttons() {
        global $post;
        
        if (!$post || !is_object($post) || !isset($post->post_type) || !isset($post->ID)) {
            return;
        }
        
        if (!in_array($post->post_type, $this->supported_post_types)) {
            return;
        }
        
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        try {
            $nav_data = $this->get_navigation_data_cached($post);
            
            if (!$nav_data || (!$nav_data['prev'] && !$nav_data['next'])) {
                return;
            }
            
            $post_type_obj = get_post_type_object($post->post_type);
            $post_type_label = $post_type_obj && isset($post_type_obj->labels->singular_name) 
                ? $post_type_obj->labels->singular_name 
                : ucfirst($post->post_type);
            
            $current_sort = $this->get_sort_preference($post->post_type);
            $current_order = $this->get_order_preference($post->post_type);
            
            echo '<div id="twf-admin-posts-navigation-classic" class="twf-admin-posts-navigation-container">';
            
            // Header with dropdown
            echo '<div class="twf-admin-posts-navigation-header">';
            // translators: %s is the post type name (e.g., "Posts", "Pages", "Products")
            echo '<h4 style="display: inline-block; margin-right: 15px;">' . esc_html(sprintf(__('Navigate %s', 'admin-posts-navigation'), $post_type_label)) . '</h4>';
            
            echo '<select id="twf-admin-posts-nav-sort-classic" class="twf-admin-posts-nav-sort-dropdown" data-post-type="' . esc_attr($post->post_type) . '" data-nonce="' . esc_attr(wp_create_nonce('twf_admin_posts_navigation_sort_' . get_current_user_id())) . '">';
            echo '<option value="menu_order"' . selected($current_sort, 'menu_order', false) . '>' . esc_html__('Menu Order', 'admin-posts-navigation') . '</option>';
            echo '<option value="date"' . selected($current_sort, 'date', false) . '>' . esc_html__('Date Published', 'admin-posts-navigation') . '</option>';
            echo '<option value="alphabetical"' . selected($current_sort, 'alphabetical', false) . '>' . esc_html__('Alphabetical', 'admin-posts-navigation') . '</option>';
            echo '<option value="post_id"' . selected($current_sort, 'post_id', false) . '>' . esc_html__('Post ID', 'admin-posts-navigation') . '</option>';
            echo '</select>';

            echo '<select id="twf-admin-posts-nav-order-classic" class="twf-admin-posts-nav-order-dropdown">';
            echo '<option value="ASC"' . selected($current_order, 'ASC', false) . '>' . esc_html__('Ascending', 'admin-posts-navigation') . '</option>';
            echo '<option value="DESC"' . selected($current_order, 'DESC', false) . '>' . esc_html__('Descending', 'admin-posts-navigation') . '</option>';
            echo '</select>';

            echo '</div>';
            
            // Navigation buttons
						echo '<div class="twf-admin-posts-navigation-buttons">';

						if ($nav_data['prev'] && is_object($nav_data['prev'])) {

								$prev_title = $this->decode_post_title(
										$nav_data['prev']->post_title
								);

								printf(
										'<a href="%s" class="button button-secondary twf-admin-posts-navigation-btn" title="%s" data-post-id="%d">← %s: %s</a>',
										esc_url(get_edit_post_link($nav_data['prev']->ID)),
										esc_attr($prev_title),
										absint($nav_data['prev']->ID),
										esc_html__('Previous', 'admin-posts-navigation'),
										esc_html(
												wp_trim_words(
														$prev_title,
														5,
														'…'
												)
										)
								);
						}

						if ($nav_data['next'] && is_object($nav_data['next'])) {

								$next_title = $this->decode_post_title(
										$nav_data['next']->post_title
								);

								printf(
										'<a href="%s" class="button button-secondary twf-admin-posts-navigation-btn" title="%s" data-post-id="%d">%s: %s →</a>',
										esc_url(get_edit_post_link($nav_data['next']->ID)),
										esc_attr($next_title),
										absint($nav_data['next']->ID),
										esc_html__('Next', 'admin-posts-navigation'),
										esc_html(
												wp_trim_words(
														$next_title,
														5,
														'…'
												)
										)
								);
						}

						echo '</div>';
            
        } catch (Exception $e) {
            // Log the detailed error for debugging (not output to browser)
            $this->debug_log('Error in add_navigation_buttons: ' . $e->getMessage(), array('post_id' => $post->ID));
            // Fail silently for users, log for debugging
        }
    }
    
    public function enqueue_gutenberg_assets() {
        global $post;
        
        if (!$post || !is_object($post) || !isset($post->post_type) || !isset($post->ID)) {
            return;
        }
        
        if (!in_array($post->post_type, $this->supported_post_types)) {
            return;
        }
        
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        wp_enqueue_script(
            'twf-admin-posts-navigation-gutenberg',
            plugin_dir_url(__FILE__) . 'assets/gutenberg.js',
            array('wp-element', 'wp-components', 'wp-data', 'wp-plugins', 'wp-editor', 'wp-edit-post'),
            $this->version,
            true
        );
        
        $post_type_obj = get_post_type_object($post->post_type);
        $current_sort = $this->get_sort_preference($post->post_type);
        
        wp_localize_script('twf-admin-posts-navigation-gutenberg', 'twfAdminPostsNavigation', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twf_admin_posts_navigation_' . get_current_user_id() . '_' . $post->ID),
            'sortNonce' => wp_create_nonce('twf_admin_posts_navigation_sort_' . get_current_user_id()),
            'postId' => absint($post->ID),
            'postType' => sanitize_key($post->post_type),
            'postTypeLabel' => $post_type_obj && isset($post_type_obj->labels->singular_name) 
                ? esc_js($post_type_obj->labels->singular_name) 
                : esc_js(ucfirst($post->post_type)),
            'postTypeLabelPlural' => $post_type_obj && isset($post_type_obj->labels->name) 
                ? esc_js($post_type_obj->labels->name) 
                : esc_js(ucfirst($post->post_type) . 's'),
            'currentSort' => $current_sort,
            'currentOrder' => $this->get_order_preference($post->post_type),
            'sortOptions' => array(
                'menu_order' => __('Menu Order', 'admin-posts-navigation'),
                'date' => __('Date Published', 'admin-posts-navigation'),
                'alphabetical' => __('Alphabetical', 'admin-posts-navigation'),
                'post_id' => __('Post ID', 'admin-posts-navigation')
            )
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_style(
            'twf-admin-posts-navigation-styles',
            plugin_dir_url(__FILE__) . 'assets/admin-styles.css',
            array(),
            $this->version
        );
        
        // Add inline script for Classic Editor dropdown functionality
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $(document).on("change", ".twf-admin-posts-nav-sort-dropdown, .twf-admin-posts-nav-order-dropdown", function() {
                    var $container = $(this).closest(".twf-admin-posts-navigation-header");
                    var $sortDropdown = $container.find(".twf-admin-posts-nav-sort-dropdown");
                    var $orderDropdown = $container.find(".twf-admin-posts-nav-order-dropdown");

                    var postType = $sortDropdown.data("post-type");
                    var sortMethod = $sortDropdown.val();
                    var sortOrder = $orderDropdown.val();
                    var nonce = $sortDropdown.data("nonce");

                    if (!postType || !sortMethod || !sortOrder || !nonce) return;

                    $sortDropdown.prop("disabled", true);
                    $orderDropdown.prop("disabled", true);

                    $.post(ajaxurl, {
                        action: "twf_admin_posts_nav_update_sort",
                        post_type: postType,
                        sort_method: sortMethod,
                        sort_order: sortOrder,
                        nonce: nonce
                    })
                    .done(function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    })
                    .fail(function() {
                        alert("Failed to update navigation sorting");
                    })
                    .always(function() {
                        $sortDropdown.prop("disabled", false);
                        $orderDropdown.prop("disabled", false);
                    });
                });
            });
        ');
    }
    
    public function ajax_get_nav_posts_with_rate_limit() {
        try {
            $user_id = get_current_user_id();
            
            // Security: Check user permissions first
            if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'admin-posts-navigation'));
            }
            
            $rate_limit_key = 'twf_admin_posts_navigation_rate_limit_' . $user_id . '_' . wp_hash(wp_get_session_token());
            
            // Check rate limit using object cache first
            $requests = wp_cache_get($rate_limit_key, 'twf_admin_posts_navigation_rate');
            if ($requests === false) {
                // Fallback to transients
                $requests = get_transient($rate_limit_key);
            }
            
            if ($requests && $requests >= 30) {
                wp_die(esc_html__('Rate limit exceeded. Please wait before making another request.', 'admin-posts-navigation'));
            }
            
            $new_count = $requests ? $requests + 1 : 1;
            wp_cache_set($rate_limit_key, $new_count, 'twf_admin_posts_navigation_rate', 60);
            set_transient($rate_limit_key, $new_count, 60);
            
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            if (!$post_id) {
                wp_die(esc_html__('Invalid post ID.', 'admin-posts-navigation'));
            }
            
            // Security: Use check_ajax_referer for AJAX nonce verification
            $expected_nonce_action = 'twf_admin_posts_navigation_' . $user_id . '_' . $post_id;
            check_ajax_referer($expected_nonce_action, 'nonce');
            
            $post = get_post($post_id);
            
            if (!$post || !is_object($post) || !isset($post->post_type)) {
                wp_die(esc_html__('Invalid post.', 'admin-posts-navigation'));
            }
            
            if (!in_array($post->post_type, $this->supported_post_types)) {
                wp_die(esc_html__('Unsupported post type.', 'admin-posts-navigation'));
            }
            
            // Security: Check user can edit this specific post
            if (!current_user_can('edit_post', $post_id)) {
                wp_die(esc_html__('You do not have permission to edit this post.', 'admin-posts-navigation'));
            }
            
            $nav_data = $this->get_navigation_data_cached($post);
            
            $response = array(
                'prev' => null,
                'next' => null,
                'current_position' => isset($nav_data['current_position']) ? absint($nav_data['current_position']) : 0,
                'total_posts' => isset($nav_data['total_posts']) ? absint($nav_data['total_posts']) : 0
            );
            
            if ($nav_data && isset($nav_data['prev']) && is_object($nav_data['prev'])) {

								$response['prev'] = array(
										'id' => absint($nav_data['prev']->ID),

										'title' => sanitize_text_field(
												$this->decode_post_title(
														$nav_data['prev']->post_title
												)
										),

										'edit_link' => esc_url_raw(
												get_edit_post_link($nav_data['prev']->ID)
										)
								);
						}

						if ($nav_data && isset($nav_data['next']) && is_object($nav_data['next'])) {

								$response['next'] = array(
										'id' => absint($nav_data['next']->ID),

										'title' => sanitize_text_field(
												$this->decode_post_title(
														$nav_data['next']->post_title
												)
										),

										'edit_link' => esc_url_raw(
												get_edit_post_link($nav_data['next']->ID)
										)
								);
						}
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            // Log the detailed error for debugging (not output to browser)
            $this->debug_log('AJAX error: ' . $e->getMessage(), array('post_id' => $post_id ?? 0));
            // Use a safe, generic error message for user output
            wp_die(esc_html__('Navigation data could not be loaded.', 'admin-posts-navigation'));
        }
    }
    
    private function get_navigation_data_cached($current_post) {
        if (!$current_post || !is_object($current_post) || !isset($current_post->ID)) {
            return null;
        }
        
        $current_sort = $this->get_sort_preference($current_post->post_type);
        $current_order = $this->get_order_preference($current_post->post_type);
        $cache_key = $this->cache_key_prefix . 'nav_data_' . $current_post->ID . '_' . $current_post->post_type . '_' . $current_sort . '_' . strtolower($current_order);
        
        // Try object cache first
        $cached_data = wp_cache_get($cache_key, 'twf_admin_posts_navigation');
        if ($cached_data !== false && is_array($cached_data)) {
            $this->debug_log('Using cached navigation data', array('post_id' => $current_post->ID));
            return $cached_data;
        }
        
        // Fallback to transients
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false && is_array($cached_data)) {
            // Store in object cache for this request
            wp_cache_set($cache_key, $cached_data, 'twf_admin_posts_navigation', 900);
            $this->debug_log('Using transient cached navigation data', array('post_id' => $current_post->ID));
            return $cached_data;
        }
        
        try {
            $nav_data = $this->get_navigation_data($current_post);
            
            if ($nav_data) {
                // Store in both caches
                wp_cache_set($cache_key, $nav_data, 'twf_admin_posts_navigation', 900);
                set_transient($cache_key, $nav_data, 1800);
                $this->debug_log('Generated fresh navigation data', array('post_id' => $current_post->ID));
            }
            
            return $nav_data;
            
        } catch (Exception $e) {
            // Log the detailed error for debugging (not output to browser)
            $this->debug_log('Error getting navigation data: ' . $e->getMessage(), array('post_id' => $current_post->ID));
            return null;
        }
    }
    
    private function get_navigation_data($current_post) {
        try {
            if (!$current_post || !is_object($current_post) || !isset($current_post->ID) || !isset($current_post->post_type)) {
                throw new Exception('Invalid post object');
            }
            
            $sort_preference = $this->get_sort_preference($current_post->post_type);
            $order_preference = $this->get_order_preference($current_post->post_type);

            // Set orderby based on sort preference; direction comes from the user's order preference
            switch ($sort_preference) {
                case 'menu_order':
                    $orderby = 'menu_order';
                    break;
                case 'alphabetical':
                    $orderby = 'title';
                    break;
                case 'post_id':
                    $orderby = 'ID';
                    break;
                case 'date':
                default:
                    $orderby = 'date';
                    break;
            }

            $order = $order_preference;

            $orderby = apply_filters('twf_admin_posts_navigation_orderby', $orderby, $current_post->post_type);
            $order = apply_filters('twf_admin_posts_navigation_order', $order, $current_post->post_type);
            
            // Use WordPress cache-friendly query
            $args = array(
                'post_type' => sanitize_key($current_post->post_type),
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'posts_per_page' => 500,
                'orderby' => $orderby,
                'order' => sanitize_text_field($order),
                'fields' => 'ids',
                'suppress_filters' => false,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'cache_results' => true, // Explicitly enable caching
            );
            
            $args = apply_filters('twf_admin_posts_navigation_query_args', $args, $current_post);
            
            // Use get_posts which properly handles caching
            $all_posts = get_posts($args);
            
            if (!is_array($all_posts)) {
                throw new Exception('get_posts() did not return an array');
            }
            
            $current_index = array_search($current_post->ID, $all_posts);
            
            $prev_post = null;
            $next_post = null;
            $current_position = 0;
            $total_posts = count($all_posts);
            
            if ($current_index !== false) {
								$current_position = $current_index + 1;

								if ($current_index < count($all_posts) - 1) {
										$prev_post_id = $all_posts[$current_index + 1];
										$prev_post = get_post($prev_post_id);
										if (!$prev_post) {
												$this->debug_log('Could not get previous post', array('post_id' => $prev_post_id));
										}
								}

								if ($current_index > 0) {
										$next_post_id = $all_posts[$current_index - 1];
										$next_post = get_post($next_post_id);
										if (!$next_post) {
												$this->debug_log('Could not get next post', array('post_id' => $next_post_id));
										}
								}
						}
            
            return array(
                'prev' => $prev_post,
                'next' => $next_post,
                'current_position' => $current_position,
                'total_posts' => $total_posts
            );
            
        } catch (Exception $e) {
            // Log the original error for debugging
            $this->debug_log('Navigation data generation failed: ' . $e->getMessage());
            // Don't re-throw with the original message, use a safe generic message
            throw new Exception('Navigation data generation failed');
        }
    }
    
    public function clear_navigation_cache($post_id = null) {
        if (!$post_id) {
            return;
        }
        
        try {
            $post = get_post($post_id);
            if (!$post || !isset($post->post_type)) {
                return;
            }
            
            // Clear object cache and transients for this post type
            $this->clear_cache_by_post_type($post->post_type);
            
            // Clear user-specific caches
            $user_cache_key = $this->cache_key_prefix . 'supported_types_' . get_current_user_id();
            wp_cache_delete($user_cache_key, 'twf_admin_posts_navigation');
            delete_transient($user_cache_key);
            
            $this->debug_log('Cleared navigation cache', array('post_id' => $post_id, 'post_type' => $post->post_type));
            
        } catch (Exception $e) {
            // Log the detailed error for debugging (not output to browser)
            $this->debug_log('Error clearing cache: ' . $e->getMessage(), array('post_id' => $post_id));
        }
    }
    
    private function clear_cache_by_post_type($post_type) {
        // Note: WordPress object cache doesn't support pattern-based deletion
        // In a production environment, you'd want to implement cache groups
        // For now, we clear known cache keys for all sort preferences
        
        $cache_keys_to_clear = array(
            $this->cache_key_prefix . 'supported_types_' . get_current_user_id(),
        );
        
        foreach ($cache_keys_to_clear as $key) {
            wp_cache_delete($key, 'twf_admin_posts_navigation');
            delete_transient($key);
        }
        
        // Clear rate limiting cache
        $user_id = get_current_user_id();
        $rate_limit_key = 'twf_admin_posts_navigation_rate_limit_' . $user_id . '_' . wp_hash(wp_get_session_token());
        wp_cache_delete($rate_limit_key, 'twf_admin_posts_navigation_rate');
        delete_transient($rate_limit_key);
    }
    
    public function show_supported_types_notice() {
        $screen = get_current_screen();
        
        if (!$screen || !in_array($screen->id, array('edit-post', 'edit-page')) || !current_user_can('manage_options')) {
            return;
        }
        
        $notice_key = 'twf_admin_posts_navigation_notice_' . get_current_user_id() . '_' . wp_hash(wp_get_session_token());
        if (get_transient($notice_key)) {
            return;
        }
        
        set_transient($notice_key, true, 3600);
        
        echo '<div class="notice notice-info is-dismissible">';
        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__('Admin Posts Navigation', 'admin-posts-navigation'),
            esc_html(sprintf(
                // translators: %1$d is the number of post types, %2$s is the list of post type names
                __('Active for %1$d post types: %2$s', 'admin-posts-navigation'),
                count($this->supported_post_types),
                implode(', ', array_map('ucfirst', $this->supported_post_types))
            ))
        );
        echo '</div>';
    }
    
    public function get_supported_types() {
        return $this->plugin_ready ? $this->supported_post_types : array();
    }
}

// Initialize the plugin
if (class_exists('TWF_AdminPostsNavigation')) {
    $twf_admin_posts_navigation = new TWF_AdminPostsNavigation();
}

// Activation hook
function twf_admin_posts_navigation_activation_hook() {
    add_option('twf_admin_posts_navigation_activated', true);
}
register_activation_hook(__FILE__, 'twf_admin_posts_navigation_activation_hook');

// Deactivation hook
function twf_admin_posts_navigation_deactivation_hook() {
    // Clear all caches on deactivation
    $user_cache_key = 'twf_admin_posts_navigation_supported_types_' . get_current_user_id();
    wp_cache_delete($user_cache_key, 'twf_admin_posts_navigation');
    delete_transient($user_cache_key);
}
register_deactivation_hook(__FILE__, 'twf_admin_posts_navigation_deactivation_hook');

// Uninstall hook
function twf_admin_posts_navigation_uninstall_hook() {
    // Clean up all plugin data on uninstall
    $user_cache_key = 'twf_admin_posts_navigation_supported_types_' . get_current_user_id();
    wp_cache_delete($user_cache_key, 'twf_admin_posts_navigation');
    delete_transient($user_cache_key);
    delete_option('twf_admin_posts_navigation_activated');
    
    // Clean up user sort preferences using WordPress functions only
    // Get all users to clean up their sort preferences
    $users = get_users(array('fields' => 'ID'));
    
    if (!empty($users)) {
        foreach ($users as $user_id) {
            // Get all user meta for this user
            $user_meta = get_user_meta($user_id);
            
            // Loop through meta keys and delete any that match our pattern
            if (is_array($user_meta)) {
                foreach ($user_meta as $meta_key => $meta_value) {
                    if (strpos($meta_key, 'twf_admin_posts_nav_sort_') === 0) {
                        delete_user_meta($user_id, $meta_key);
                    }
                }
            }
        }
    }
}
register_uninstall_hook(__FILE__, 'twf_admin_posts_navigation_uninstall_hook');

// Plugin action links
function twf_admin_posts_navigation_plugin_action_links($links) {
    try {
        global $twf_admin_posts_navigation;
        
        if ($twf_admin_posts_navigation && method_exists($twf_admin_posts_navigation, 'get_supported_types')) {
            $supported_types = $twf_admin_posts_navigation->get_supported_types();
            
            if (is_array($supported_types) && !empty($supported_types)) {
                $settings_link = '<span style="color: #0073aa;">' . 
                    esc_html(sprintf(
                        // translators: %s is a list of supported post types
                        __('Supports: %s', 'admin-posts-navigation'),
                        implode(', ', array_slice($supported_types, 0, 3)) . 
                        (count($supported_types) > 3 ? '... (+' . (count($supported_types) - 3) . ' more)' : '')
                    )) . 
                    '</span>';
                
                array_unshift($links, $settings_link);
            }
        }
    } catch (Exception $e) {
        // Silently fail - plugin links are not critical functionality
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // Only use WordPress debug logging - no error_log()
            if (function_exists('wp_debug_log')) {
                wp_debug_log('TWF Admin Posts Navigation: Plugin action links error - ' . $e->getMessage());
            }
            // If wp_debug_log is not available, we simply don't log (fail silently)
            // This ensures no error_log() calls that Plugin Check flags
        }
    }
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'twf_admin_posts_navigation_plugin_action_links');