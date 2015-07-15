<?php
/*
* Plugin Name: WP eCommerce Retargeting
* Plugin URI: https://retargeting.biz/wp-e-commerce-documentation
* Description: Adds Retargeting Tracking code to WP e Commerce.
* Version: 1.0.0
* Author: Retargeting Team
* Author URI: http://retargeting.biz
* License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPEC_Retargeting')) :
    session_start();

    class WPEC_Retargeting
    {
        const VERSION = '1.0.0';
        const REQ_WP_VER = '3.5';
        const REQ_WPSC_VER = '3.8.9.1';

        protected static $instance = null;
        protected $plugin_dir = '';
        protected $plugin_url = '';
        protected $plugin_name = '';

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        private function __construct()
        {
            $this->plugin_dir = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);
            $this->plugin_name = plugin_basename(__FILE__);

            register_activation_hook($this->plugin_name, array($this, 'activate'));
        }

        public function init()
        {
            if (is_admin()) {
                $this->load_class('WPEC_Retargeting_Admin');
                $admin = new WPEC_Retargeting_Admin();
                add_action('wpsc_register_settings_tabs', array($admin, 'register_tab'));
                add_action('wpsc_load_settings_tab_class', array($admin, 'register_tab'));
                add_action('admin_init', array($admin, 'register_settings'));
                add_filter('plugin_action_links', array($admin, 'register_action_links'), 10, 2);
            } else {
                add_action('wp_head', array($this, 'register_scripts'));

                add_action('wpsc_top_of_products_page', array($this, 'send_product'));
                add_action('wpsc_top_of_products_page', array($this, 'send_category'));
                add_action('wp_footer', array($this, 'set_email'));
                add_action('wp_footer', array($this, 'checkout_ids'));
                add_action('wpsc_transaction_results_shutdown', array($this, 'save_order'));
                add_action('wp_footer', array($this, 'help_pages'), 999, 0);

                if ($this->use_default_elements()) {
                    add_action('wpsc_theme_footer', array($this, 'product_page_bottom'));
                    add_action('wpsc_top_of_products_page', array($this, 'category_top'));
                    add_action('wpsc_theme_footer', array($this, 'add_category_page_bottom_elements'));
                    add_action('wpsc_bottom_of_shopping_cart', array($this, 'cart_bottom'));
                    add_action('wpecnt_top_of_search_results', array($this, 'add_search_page_top_elements'));
                    add_action('wpecnt_bottom_of_search_results', array($this, 'add_search_page_bottom_elements'));
                    add_action('wpecnt_top_of_pages', array($this, 'add_page_top_elements'));
                    add_action('wpecnt_bottom_of_pages', array($this, 'add_page_bottom_elements'));
                }
            }

            add_filter('query_vars', array($this, 'retargeting_api_add_query_vars'));
            add_action('template_redirect', array($this, 'discount_api_template'));
        }

        public function activate()
        {
            if ($this->check_dependencies()) {
                add_option('retargeting_tagging_account_id', '');
                add_option('retargeting_tagging_use_default_elements', 1);

                $this->load_class('WPEC_Retargeting_Top_Sellers_Page');
                $page_id = get_option('retargeting_tagging_top_sellers_page_id', null);
                $page = new WPEC_Retargeting_Top_Sellers_Page($page_id);
                $page->publish();
                if (null === $page_id) {
                    add_option('retargeting_tagging_top_sellers_page_id', $page->get_id());
                } else {
                    update_option('retargeting_tagging_top_sellers_page_id', $page->get_id());
                }
            }
        }

        public function get_plugin_name()
        {
            return $this->plugin_name;
        }

        public function load_class($class_name)
        {
            $file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
            require_once($this->plugin_dir . 'classes/' . $file);
        }

        public function render($template, $data = array())
        {
            extract($data);
            $file = $template . '.php';
            require($this->plugin_dir . 'templates/' . $file);
        }

        public function register_scripts()
        {
            $account_id = get_option('retargeting_domain_api');

            if (!empty($account_id)) {
                /*
                 * Retargeting V3
                 *
                 * */
                echo '<script type="text/javascript">
					(function(){
					var ra_key = "' . $account_id . '";
					var ra = document.createElement("script"); ra.type ="text/javascript"; ra.async = true; ra.src = ("https:" ==
					document.location.protocol ? "https://" : "http://") + "tracking.retargeting.biz/rajs/" + ra_key + ".js";
					var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ra,s);})();</script>';
            } else {
                /*
                 * Retargeting V2
                 *
                 * */
                echo '<script type="text/javascript">
					(function(){
					var ra = document.createElement("script"); ra.type ="text/javascript"; ra.async = true; ra.src = ("https:" ==
					document.location.protocol ? "https://" : "http://") + "retargeting-data.eu/" +
					document.location.hostname.replace("www.","") + "/ra.js"; var s =
					document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ra,s);})();</script>';
            }
        }

        public function send_product()
        {
            if (wpsc_is_single_product()) {
                $product = array();

                while (wpsc_have_products()) {
                    wpsc_the_product();

                    $product_id = (int)wpsc_the_product_id();

                    $product['url'] = (string)wpsc_the_product_permalink();
                    $product['product_id'] = $product_id;
                    $product['name'] = (string)wpsc_the_product_title();
                    $product['image_url'] = (string)wpsc_the_product_image('', '', $product_id);

                    if (wpsc_product_has_variations($product_id)) {
                        $price = $this->get_lowest_product_variation_price($product_id);
                    } else {
                        $price = wpsc_calculate_price($product_id, false, true);
                    }
                    $product['price'] = $this->format_price($price);


                    if (wpsc_product_has_stock($product_id)) {
                        $product['stock'] = 1;
                    } else {
                        $product['stock'] = 0;
                    }

                    $product['categories'] = array();
                    $category_terms = wp_get_product_categories($product_id);

                    foreach ($category_terms as $category_term) {
                        $category_path = $category_term;
                        if (!empty($category_path)) {
                            $product['category_name'] = $category_term->name;
                            $product['category_id'] = $category_term->term_id;
                        }
                    }

                    if (wpsc_product_has_variations($product_id)) {
                        $list_price = $this->get_lowest_product_variation_price($product_id);
                    } else {
                        $list_price = wpsc_calculate_price($product_id, false, false);
                    }
                    $product['list_price'] = $this->format_price($list_price);
                }

                if (!empty($product)) {
                    $this->render('sendProduct', array('product' => $product));
                }
            }
        }

        public function send_category()
        {
            if (wpsc_is_in_category()) {
                $category_slug = get_query_var('wpsc_product_category');
                if (!empty($category_slug)) {
                    $category_term = get_term_by('slug', $category_slug, 'wpsc_product_category');
                    $category_path = $category_term;

                    echo '<script type="text/javascript">
				var _ra = _ra || {};
					_ra.sendCategoryInfo = {
						"id": ' . $category_term->term_id . ',
						"name" : "' . $category_term->name . '",
						"parent": false,
						"category_breadcrumb": []
					}
				if (_ra.ready !== undefined) {
					_ra.sendCategory(_ra.sendCategoryInfo);
				}
				</script>';
                }
            }
        }

        public function help_pages()
        {
            global $post;
            $page = $post->ID;

            $help_pages = explode(',', get_option('retargeting_help_pages'));

            if (!empty($help_pages)) {
                if (in_array($page, $help_pages)) {
                    echo "<script>var _ra = _ra || {}; _ra.visitHelpPageInfo = {'visit' : true} if (_ra.ready !== undefined) {_ra.visitHelpPage();}</script>";
                }
                return false;
            }
            return false;
        }

        public function set_email()
        {

            $email = array();
            $email['email'] = wp_get_current_user()->user_email;

            if ((!isset($_SESSION['set_email']) || $_SESSION['set_email'] != $email['email']) && (!empty($email['email']))) {
                echo '<script>_ra.setEmail({"email": "' . esc_html($email['email']) . '"});</script>';
                $_SESSION['set_email'] = $email['email'];
            }
        }

        public function checkout_ids()
        {
            if (wpsc_cart_item_count() > 0) {
                global $wpsc_cart;

                $line_items = array();

                while (wpsc_have_cart_items()) {
                    wpsc_the_cart_item();

                    $current_item = $wpsc_cart->cart_item;

                    $parent = $this->get_parent_post($current_item->product_id);
                    if ($parent) {
                        $product_id = $parent->ID;
                        $product_name = $parent->post_title;
                    } else {
                        $product_id = wpsc_cart_item_product_id();
                        $product_name = wpsc_cart_item_name();
                    }

                    $line_item = (int)$product_id;

                    $line_items[] = $line_item;
                }

                echo '<script type="text/javascript">
			        var _ra = _ra || {};
			 	    _ra.checkoutIdsInfo =' . json_encode($line_items, JSON_PRETTY_PRINT) . '
			        if (_ra.ready !== undefined) {
				        _ra.checkoutIds(_ra.checkoutIdsInfo);
				  }
			</script>';
            }
        }

        public function save_order($purchase_log)
        {
            if ($purchase_log instanceof WPSC_Purchase_Log) {
                $order = array(
                    'line_items' => array(),
                );
                $checkout_form = new WPSC_Checkout_Form_Data($purchase_log->get('id'));

                $products = $purchase_log->get_cart_contents();
                if (is_array($products)) {
                    foreach ($products as $product) {

                        $parent = $this->get_parent_post($product->prodid);
                        if ($parent) {
                            $product_id = $parent->ID;
                            $product_name = $parent->post_title;
                        } else {
                            $product_id = $product->prodid;
                            $product_name = $product->name;
                        }

                        $line_item = array(
                            'id' => (int)$product_id,
                            'quantity' => (int)$product->quantity,
                            'price' => $this->format_price($product->price),
                        );

                        $order['line_items'][] = $line_item;
                    }
                }

                echo '<script type="text/javascript">
						var _ra = _ra || {};
							_ra.saveOrderInfo = {
								"order_no": ' . $purchase_log->get('id') . ',
								"lastname": "' . $checkout_form->get('billinglastname') . '",
								"firstname": "' . $checkout_form->get('billingfirstname') . '",
								"email": "' . $checkout_form->get('billingemail') . '",
								"phone": "' . $checkout_form->get('billingphone') . '",
								"state": "' . $checkout_form->get('shippingstate') . '",
								"city": "' . $checkout_form->get('shippingcity') . '",
								"address": "' . $checkout_form->get('billingaddress') . '",
								"discount_code": "' . $purchase_log->get('discount_data') . '",
								"discount": "' . $purchase_log->get('discount_value') . '",
								"shipping": "' . $purchase_log->get('total_shipping') . '",
								"total": "' . $purchase_log->get('totalprice') . '"
							};
							_ra.saveOrderProducts = ' . json_encode($order['line_items'], JSON_PRETTY_PRINT) . '
							
							if( _ra.ready !== undefined ){
								_ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);
							}
						</script>';
            }
        }

        public function product_page_bottom()
        {
            if (wpsc_is_single_product()) {
                $default_element_ids = array(
                    'productpage-retargeting-1',
                    'productpage-retargeting-2',
                    'productpage-retargeting-3',
                );
                $element_ids = apply_filters('wpecnt_product_page_bottom', $default_element_ids);
                if (is_array($element_ids) && !empty($element_ids)) {
                    $this->render('retargeting-elements', array('element_ids' => $element_ids));
                }
            }
        }

        public function category_top()
        {
            if (wpsc_is_in_category()) {
                $default_element_ids = array(
                    'productcategory-retargeting-1',
                );
                $element_ids = apply_filters('wpecnt_category_top', $default_element_ids);
                if (is_array($element_ids) && !empty($element_ids)) {
                    $this->render('retargeting-elements', array('element_ids' => $element_ids));
                }
            }
        }

        public function add_category_page_bottom_elements()
        {
            if (wpsc_is_in_category()) {
                $default_element_ids = array(
                    'productcategory-retargeting-2',
                );
                $element_ids = apply_filters('wpecnt_add_category_page_bottom_elements', $default_element_ids);
                if (is_array($element_ids) && !empty($element_ids)) {
                    $this->render('retargeting-elements', array('element_ids' => $element_ids));
                }
            }
        }

        public function cart_bottom()
        {
            $default_element_ids = array(
                'cartpage-retargeting-1',
                'cartpage-retargeting-2',
                'cartpage-retargeting-3',
            );
            $element_ids = apply_filters('wpecnt_cart_bottom', $default_element_ids);
            if (is_array($element_ids) && !empty($element_ids)) {
                $this->render('retargeting-elements', array('element_ids' => $element_ids));
            }
        }

        public function add_search_page_top_elements()
        {
            $default_element_ids = array(
                'searchpage-retargeting-1',
            );
            $element_ids = apply_filters('wpecnt_add_search_page_top_elements', $default_element_ids);
            if (is_array($element_ids) && !empty($element_ids)) {
                $this->render('retargeting-elements', array('element_ids' => $element_ids));
            }
        }

        public function add_search_page_bottom_elements()
        {
            $default_element_ids = array(
                'searchpage-retargeting-2',
            );
            $element_ids = apply_filters('wpecnt_add_search_page_bottom_elements', $default_element_ids);
            if (is_array($element_ids) && !empty($element_ids)) {
                $this->render('retargeting-elements', array('element_ids' => $element_ids));
            }
        }

        public function add_page_top_elements()
        {
            $default_element_ids = array(
                'pagetemplate-retargeting-1',
            );
            $element_ids = apply_filters('wpecnt_add_page_top_elements', $default_element_ids);
            if (is_array($element_ids) && !empty($element_ids)) {
                $this->render('retargeting-elements', array('element_ids' => $element_ids));
            }
        }

        public function add_page_bottom_elements()
        {
            $default_element_ids = array(
                'pagetemplate-retargeting-2',
            );
            $element_ids = apply_filters('wpecnt_add_page_bottom_elements', $default_element_ids);
            if (is_array($element_ids) && !empty($element_ids)) {
                $this->render('retargeting-elements', array('element_ids' => $element_ids));
            }
        }

        protected function use_default_elements()
        {
            return (int)get_option('retargeting_tagging_use_default_elements', 1);
        }

        protected function get_parent_post($post_id, $type = 'wpsc-product')
        {
            $parent_post_id = (int)get_post_field('post_parent', $post_id);
            if (0 !== $parent_post_id) {
                $parent_post = get_post($parent_post_id);
                if ($parent_post instanceof WP_Post && $parent_post->post_type === $type) {
                    return $parent_post;
                }
            }
            return null;
        }

        protected function format_price($price)
        {
            return number_format((float)$price, 2, '.', '');
        }

        protected function build_cat($category_term)
        {
            $cat_path = '';

            if (is_object($category_term) && !empty($category_term->term_id)) {
                $category_terms = $this->get_parent_terms($category_term);
                $category_terms[] = $category_term;

                $category_term_names = array();
                foreach ($category_terms as $category_term) {
                    $category_term_names[] = $category_term->name;
                }

                if (!empty($category_term_names)) {
                    $cat_path = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $category_term_names);
                }
            }

            return $cat_path;
        }

        protected function get_parent_terms($term, $taxonomy = 'wpsc_product_category')
        {
            if (empty($term->parent)) {
                return array();
            }

            $parent = get_term($term->parent, $taxonomy);

            if (is_wp_error($parent)) {
                return array();
            }

            $parents = array($parent);

            if ($parent->parent && ($parent->parent !== $parent->term_id)) {
                $parents = array_merge($parents, $this->get_parent_terms($parent, $taxonomy));
            }

            return array_reverse($parents);
        }

        protected function check_dependencies()
        {
            global $wp_version;

            $title = sprintf(__('WP e-Commerce Retargeting %s not compatible.'), self::VERSION);
            $args = array(
                'back_link' => true,
            );

            if (version_compare($wp_version, self::REQ_WP_VER, '<')) {
                deactivate_plugins($this->plugin_name);

                $msg = __('Looks like you\'re running an older version of WordPress, you need to be running at least
					WordPress %1$s to use WP e-Commerce Retargeting %2$s.');

                wp_die(sprintf($msg, self::REQ_WP_VER, self::VERSION), $title, $args);
                return false;
            }

            if (!defined('WPSC_VERSION')) {
                deactivate_plugins($this->plugin_name);

                $msg = __('Looks like you\'re not running any version of WP e-Commerce, you need to be running at least
					WP e-Commerce %1$s to use WP e-Commerce Retargeting %2$s.');

                wp_die(sprintf($msg, self::REQ_WPSC_VER, self::VERSION), $title, $args);
                return false;
            } else if (version_compare(WPSC_VERSION, self::REQ_WPSC_VER, '<')) {
                deactivate_plugins($this->plugin_name);

                $msg = __('Looks like you\'re running an older version of WP e-Commerce, you need to be running at least
					WP e-Commerce %1$s to use WP e-Commerce Retargeting %2$s.');

                wp_die(sprintf($msg, self::REQ_WPSC_VER, self::VERSION), $title, $args);
                return false;
            }

            return true;
        }

        protected function get_lowest_product_variation_price($product_id)
        {
            global $wpdb;

            static $price_cache = array();

            if (isset($price_cache[$product_id])) {
                $results = $price_cache[$product_id];
            } else {
                $results = $wpdb->get_results($wpdb->prepare("
				SELECT pm.meta_value AS price, pm2.meta_value AS special_price
				FROM {$wpdb->posts} AS p
				INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.id AND pm.meta_key = '_wpsc_price'
				INNER JOIN {$wpdb->postmeta} AS pm2 ON pm2.post_id = p.id AND pm2.meta_key = '_wpsc_special_price'
				WHERE p.post_type = 'wpsc-product'
				AND p.post_parent = %d
			", $product_id));
                $price_cache[$product_id] = $results;
            }

            $prices = array();

            foreach ($results as $row) {
                $price = (float)$row->price;
                $special_price = (float)$row->special_price;
                if ($special_price != 0 && $special_price < $price) {
                    $prices[] = $special_price;
                } else {
                    $prices[] = $price;
                }
            }

            sort($prices);
            if (empty($prices)) {
                $prices[] = 0;
            }

            return apply_filters('wpsc_do_convert_price', $prices[0], $product_id);
        }

        /*
         * Discounts
         *
         * */

        function retargeting_api_add_query_vars($vars)
        {
            $vars[] = "retargeting";
            $vars[] = "key";
            $vars[] = "value";
            $vars[] = "type";
            $vars[] = "count";
            return $vars;
        }

        function discount_api_template($template)
        {
            global $wp_query;
            global $wpdb;
            $discounts_api_key = get_option('retargeting_discounts_api');
            if (isset($wp_query->query['retargeting']) && $wp_query->query['retargeting'] == 'discounts') {
                if (isset($wp_query->query['key']) && isset($wp_query->query['value']) && isset($wp_query->query['type']) && isset($wp_query->query['count'])) {
                    if ($wp_query->query['key'] != "" && $wp_query->query['key'] == $discounts_api_key && $wp_query->query['value'] != "" && $wp_query->query['type'] != "" && $wp_query->query['count'] != "") {
                        if (!in_array($wp_query->query['type'], array(0, 1, 2))) {
                            echo json_encode(array("status" => false, "error" => "0003: Invalid Type!"));
                            exit;
                        }
                        echo generate_coupons($wp_query->query['count']);
                        exit;
                    } else {
                        echo json_encode(array("status" => false, "error" => "0002: Invalid Parameters!"));
                        exit;
                    }
                } else {
                    echo json_encode(array("status" => false, "error" => "0001: Missing Parameters!"));
                    exit;
                }
            }
        }
    }

    function generate_coupons($count)
    {
        global $wp_query;

        $couponChars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $couponCodes = array();

        for ($x = 0; $x < $count; $x++) {
            $couponCode = "";
            for ($i = 0; $i < 8; $i++) {
                $couponCode .= $couponChars[mt_rand(0, strlen($couponChars) - 1)];
            }
            if (wpecommerce_verify_discount($couponCode)) {
                wpecommerce_add_discount($couponCode, $wp_query->query['value'], $wp_query->query['type']);
                $couponCodes[] = $couponCode;
            } else {
                $x -= 1;
            }
        }
        return json_encode($couponCodes, JSON_PRETTY_PRINT);
    }

    function wpecommerce_verify_discount($code)
    {

        global $wpdb;

        $res = $wpdb->get_results(
            "SELECT * FROM " . WPSC_TABLE_COUPON_CODES . " WHERE coupon_code = '$code'"
        );

        return !(bool)count($res);

    }

    function wpecommerce_add_discount($code, $discount, $type)
    {
        global $wpdb;

        $coupon_code = $code;
        $discount_type = (int)$type;
        $discount = ($discount_type != 2 ? (double)$discount : (double)0);
        $use_once = (int)(bool)1;
        $every_product = (int)(bool)1;
        $is_active = (int)(bool)1;
        $start_date = date("Y-m-d H:i:s");
        $end_date = null;
        $new_rules = array();

        $wpdb->insert(
            WPSC_TABLE_COUPON_CODES,
            array(
                'coupon_code' => $coupon_code,
                'value' => $discount,
                'is-percentage' => $discount_type,
                'use-once' => $use_once,
                'is-used' => 0,
                'active' => $is_active,
                'every_product' => $every_product,
                'start' => $start_date,
                'expiry' => $end_date,
                'condition' => serialize($new_rules)
            ),
            array(
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );
    }
        add_action('plugins_loaded', array(WPEC_Retargeting::get_instance(), 'init'));

        endif;