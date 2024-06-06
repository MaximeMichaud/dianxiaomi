<?php
/*
    Plugin Name: Dianxiaomi - WooCommerce ERP Compatibility php 8.2
    Plugin URI: http://dianxiaomi.com/
    Description: Add tracking number and carrier name to WooCommerce, display tracking info at order history page, auto import tracking numbers to Dianxiaomi.
    Version: 1.0.20   Author: Dianxiaomi (Alex Modified)
    Updated: 2024-06-04
    Author URI: https://github.com/whywilson/dianxiaomi-for-woocommerce/releases
    Copyright: © Dianxiaomi
*/

/**
 * Security Note: Prevent direct access to the file.
 */
defined('ABSPATH') or die("No script kiddies please!");

/**
 * Include required functions if they are not already defined.
 */
if (!function_exists('is_woocommerce_active')) {
    require_once('dianxiaomi-functions.php');
}

/**
 * Declare compatibility with WooCommerce HPOS before WooCommerce initializes.
 */
add_action('before_woocommerce_init', 'before_woocommerce_hpos');

function before_woocommerce_hpos(): void { 
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) { 
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true); 
    }
}

/**
 * Check if WooCommerce is active and initialize the plugin.
 */
if (is_woocommerce_active()) {

    /**
     * Define the Dianxiaomi class if it hasn't been defined.
     */
    if (!class_exists('Dianxiaomi')) {

        final class Dianxiaomi
        {
            protected static ?Dianxiaomi $_instance = null;

            /**
             * Singleton instance method.
             */
            public static function instance(): Dianxiaomi
            {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }

            /**
             * Constructor: Initialize the plugin.
             */
            public function __construct()
            {
                $this->includes();
                $this->api = new Dianxiaomi_API();
                $this->initialize_options();
            }

            /**
             * Include necessary files.
             */
            private function includes(): void
            {
                include_once('dianxiaomi-fields.php');
                $this->dianxiaomi_fields = $dianxiaomi_fields;

                include_once('class-dianxiaomi-api.php');
                include_once('class-dianxiaomi-settings.php');
            }

            /**
             * Load and set options.
             */
            private function initialize_options(): void
            {
                $options = get_option('dianxiaomi_option_name');
                if ($options) {
                    $this->plugin = $options['plugin'] ?? '';
                    $this->use_track_button = $options['use_track_button'] ?? false;
                    $this->custom_domain = $options['custom_domain'] ?? '';
                    $this->couriers = $options['couriers'] ?? [];

                    $this->register_hooks();
                }
            }

            /**
             * Register hooks and actions.
             */
            private function register_hooks(): void
            {
                add_action('admin_print_scripts', [$this, 'library_scripts']);
                add_action('in_admin_footer', [$this, 'include_footer_script']);
                add_action('admin_print_styles', [$this, 'admin_styles']);
                add_action('add_meta_boxes', [$this, 'add_meta_box']);
                add_action('woocommerce_process_shop_order_meta', [$this, 'save_meta_box'], 0, 2);
                add_action('plugins_loaded', [$this, 'load_plugin_textdomain']);
                add_action('woocommerce_view_order', [$this, 'display_tracking_info']);
                add_action('woocommerce_email_before_order_table', [$this, 'email_display']);
            }

            /**
             * Load plugin textdomain for translations.
             */
            public function load_plugin_textdomain(): void
            {
                load_plugin_textdomain('dianxiaomi', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            /**
             * Enqueue admin styles.
             */
            public function admin_styles(): void
            {
                wp_enqueue_style('dianxiaomi_styles_chosen', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.min.css');
                wp_enqueue_style('dianxiaomi_styles', plugins_url(basename(dirname(__FILE__))) . '/assets/css/admin.css');
            }

            /**
             * Enqueue scripts for the admin panel.
             */
            public function library_scripts(): void
            {
                wp_enqueue_script('dianxiaomi_script_chosen_jquery', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.jquery.min.js');
                wp_enqueue_script('dianxiaomi_script_chosen_proto', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.proto.min.js');
                wp_enqueue_script('dianxiaomi_script_util', plugins_url(basename(dirname(__FILE__))) . '/assets/js/util.js');
                wp_enqueue_script('dianxiaomi_script_couriers', plugins_url(basename(dirname(__FILE__))) . '/assets/js/couriers.js');
                wp_enqueue_script('dianxiaomi_script_admin', plugins_url(basename(dirname(__FILE__))) . '/assets/js/admin.js');
            }

            /**
             * Enqueue footer scripts.
             */
            public function include_footer_script(): void
            {
                wp_enqueue_script('dianxiaomi_script_footer', plugins_url(basename(dirname(__FILE__))) . '/assets/js/footer.js', true);
            }

            /**
             * Add a meta box for shipment info on the order page.
             */
            public function add_meta_box(): void
            {
                add_meta_box('woocommerce-dianxiaomi', __('Dianxiaomi', 'wc_dianxiaomi'), [$this, 'meta_box'], 'shop_order', 'side', 'high');
            }

            /**
             * Display the meta box for shipment info on the order page.
             */
            public function meta_box(): void
            {
                global $post;
                $selected_provider = get_post_meta($post->ID, '_dianxiaomi_tracking_provider', true);

                echo '<div id="dianxiaomi_wrapper">';
                echo '<p class="form-field"><label for="dianxiaomi_tracking_provider">' . __('Carrier:', 'wc_dianxiaomi') . '</label><br/><select id="dianxiaomi_tracking_provider" name="dianxiaomi_tracking_provider" class="chosen_select" style="width:100%">';
                if ($selected_provider == '') {
                    $selected_text = 'selected="selected"';
                } else {
                    $selected_text = '';
                }
                echo '<option disabled ' . $selected_text . ' value="">Please Select</option>';
                echo '</select>';
                echo '<br><a href="options-general.php?page=dianxiaomi-setting-admin">Update carrier list</a>';
                echo '<input type="hidden" id="dianxiaomi_tracking_provider_hidden" value="' . $selected_provider . '"/>';
                echo '<input type="hidden" id="dianxiaomi_couriers_selected" value="' . $this->couriers . '"/>';

                foreach ($this->dianxiaomi_fields as $field) {
                    if ($field['type'] == 'date') {
                        woocommerce_wp_text_input(array(
                            'id' => $field['id'],
                            'label' => __($field['label'], 'wc_dianxiaomi'),
                            'placeholder' => $field['placeholder'],
                            'description' => $field['description'],
                            'class' => $field['class'],
                            'value' => ($date = get_post_meta($post->ID, '_' . $field['id'], true)) ? date('Y-m-d', $date) : ''
                        ));
                    } else {
                        woocommerce_wp_text_input(array(
                            'id' => $field['id'],
                            'label' => __($field['label'], 'wc_dianxiaomi'),
                            'placeholder' => $field['placeholder'],
                            'description' => $field['description'],
                            'class' => $field['class'],
                            'value' => get_post_meta($post->ID, '_' . $field['id'], true),
                        ));
                    }
                }
                echo '</div>'; // End of dianxiaomi_wrapper
            }
        

   
   
        /**
         * Order Downloads Save
         *
         * Function for processing and storing all order downloads.
         */
        public function save_meta_box($post_id, $post)
        {
            if (isset($_POST['dianxiaomi_tracking_number'])) {
                $tracking_provider = woocommerce_clean($_POST['dianxiaomi_tracking_provider']);
                update_post_meta($post_id, '_dianxiaomi_tracking_provider', $tracking_provider);
        
                foreach ($this->dianxiaomi_fields as $field) {
                    $field_value = $_POST[$field['id']] ?? '';
                    if ($field['type'] == 'date') {
                        $field_value = strtotime($field_value);
                    }
                    update_post_meta($post_id, '_' . $field['id'], woocommerce_clean($field_value));
                }
            }
        }
        
        /**
         * Display the API key info for a user
         *
         * @param WP_User $user
         */
        public function add_api_key_field($user)
        {
            if (!current_user_can('manage_dianxiaomi') || !current_user_can('edit_user', $user->ID)) {
                return;
            }
        
            echo '<h3>Dianxiaomi</h3>';
            echo '<table class="form-table">';
            echo '<tbody>';
            echo '<tr>';
            echo '<th><label for="dianxiaomi_wp_api_key">' . __('Dianxiaomi\'s WordPress API Key', 'dianxiaomi') . '</label></th>';
            echo '<td>';
            if (empty($user->dianxiaomi_wp_api_key)) {
                echo '<input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />';
                echo '<span class="description">' . __('Generate API Key', 'dianxiaomi') . '</span>';
            } else {
                echo '<code id="dianxiaomi_wp_api_key">' . $user->dianxiaomi_wp_api_key . '</code><br />';
                echo '<input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />';
                echo '<span class="description">' . __('Revoke API Key', 'dianxiaomi') . '</span>';
            }
            echo '</td>';
            echo '</tr>';
            echo '</tbody>';
            echo '</table>';
        }
        
        /**
         * Generate and save (or delete) the API keys for a user
         *
         * @param int $user_id
         */
        public function generate_api_key($user_id)
        {
            if (!current_user_can('edit_user', $user_id)) {
                return;
            }
        
            $user = get_userdata($user_id);
            if (isset($_POST['dianxiaomi_wp_generate_api_key'])) {
                if (empty($user->dianxiaomi_wp_api_key)) {
                    $api_key = 'ck_' . hash('md5', $user->user_login . date('U') . mt_rand());
                    update_user_meta($user_id, 'dianxiaomi_wp_api_key', $api_key);
                } else {
                    delete_user_meta($user_id, 'dianxiaomi_wp_api_key');
                }
            }
        }
        
        /**
         * Display Shipment info in the frontend (order view/tracking page).
         *
         * @param int $order_id
         * @param bool $for_email
         */
        function display_tracking_info($order_id, $for_email = false)
        {
            if ($this->plugin == 'dianxiaomi') {
                $this->display_order_dianxiaomi($order_id, $for_email);
            } else if ($this->plugin == 'wc-shipment-tracking') {
                $this->display_order_wc_shipment_tracking($order_id, $for_email);
            }
        }
        
        private function display_order_dianxiaomi($order_id, $for_email)
        {
            $order = wc_get_order($order_id);
            if (!$order) return;
        
            $values = array();
            foreach ($this->dianxiaomi_fields as $field) {
                $meta_value = $order->get_meta('_' . $field['id']);
                if ($field['type'] == 'date' && $meta_value) {
                    $values[$field['id']] = date_i18n(__('l jS F Y', 'wc_shipment_tracking'), strtotime($meta_value));
                } else {
                    $values[$field['id']] = $meta_value;
                }
            }
        
            $dianxiaomi_tracking_provider = $order->get_meta('_dianxiaomi_tracking_provider');
            $dianxiaomi_tracking_number = $order->get_meta('_dianxiaomi_tracking_number');
            $dianxiaomi_tracking_provider_name = $order->get_meta('_dianxiaomi_tracking_provider_name');
        
            if (!$dianxiaomi_tracking_provider || !$dianxiaomi_tracking_number) return;
        
            $options = get_option('dianxiaomi_option_name');
            $track_message_1 = isset($options['track_message_1']) ? $options['track_message_1'] : 'Your order was shipped via ';
            $track_message_2 = isset($options['track_message_2']) ? $options['track_message_2'] : 'Tracking number is ';
        
            $required_fields_values = array();
            $provider_required_fields = explode(",", $order->get_meta('_dianxiaomi_tracking_required_fields'));
        
            foreach ($provider_required_fields as $field) {
                if (isset($values[$field])) {
                    $required_fields_values[] = $values[$field];
                }
            }
        
            $required_fields_msg = !empty($required_fields_values) ? ' (' . join(', ', $required_fields_values) . ')' : '';
        
            $custom_domain = $this->custom_domain ?: 'https://t.17track.net/en#nums=';
            $tracking_url = $custom_domain . $dianxiaomi_tracking_number;
        
            echo $track_message_1 . $dianxiaomi_tracking_provider_name . '<br/>' . $track_message_2 . '<a target="_blank" href="' . esc_url($tracking_url) . '">' . esc_html($dianxiaomi_tracking_number) . '</a>' . $required_fields_msg;
        
            if (!$for_email && $this->use_track_button) {
                $this->display_track_button($dianxiaomi_tracking_provider, $dianxiaomi_tracking_number, $required_fields_values);
            }
        }
        
        private function display_order_wc_shipment_tracking($order_id, $for_email)
        {
            if ($for_email || !$this->use_track_button) {
                return;
            }
        
            $order = wc_get_order($order_id);
            if (!$order) return;
        
            $tracking = $order->get_meta('_tracking_number', true);
            $sharp = strpos($tracking, '#');
            $colon = strpos($tracking, ':');
            $required_fields = array();
            if ($sharp && $colon && $sharp >= $colon) {
                return;
            } else if (!$sharp && $colon) {
                return;
            } else if ($sharp) {
                $tracking_provider = substr($tracking, 0, $sharp);
                if ($colon) {
                    $tracking_number = substr($tracking, $sharp + 1, $colon - $sharp - 1);
                    $temp = substr($tracking, $sharp + 1, strlen($tracking));
                    $required_fields = explode(':', $temp);
                } else {
                    $tracking_number = substr($tracking, $sharp + 1, strlen($tracking));
                }
            } else {
                $tracking_provider = '';
                $tracking_number = $tracking;
            }
            if ($tracking_number) {
                $this->display_track_button($tracking_provider, $tracking_number, $required_fields);
            }
        }
        
        /**
         * Display shipment info in customer emails.
         *
         * @access public
         * @return void
         */
        function email_display($order)
        {
            $this->display_tracking_info($order->id, true);
        }
        
        private function display_track_button($tracking_provider, $tracking_number, $required_fields_values)
        {
            // $js = '(function(e,t,n){var r,i=e.getElementsByTagName(t)[0];if(e.getElementById(n))return;r=e.createElement(t);r.id=n;r.src="/wp-content/plugins/dianxiaomi/assets/js/track-button.js";i.parentNode.insertBefore(r,i)})(document,"script","trackdog-jssdk")';
        
            $js = '(function(e,t,n){})(document,"script","trackdog-jssdk")';
        
            if (function_exists('wc_enqueue_js')) {
                wc_enqueue_js($js);
            } else {
                global $woocommerce;
                $woocommerce->add_inline_js($js);
            }
        
            if (count($required_fields_values)) {
                $tracking_number = $tracking_number . ':' . join(':', $required_fields_values);
            }
        
            $temp_url = '';
            $temp_slug = ' data-slug="' . $tracking_provider . '"';
            if ($this->custom_domain != '') {
                $temp_url = '" data-domain="' . $this->custom_domain;
                $temp_slug = '';
            }
        
            $this->display_track_button_html($this->custom_domain, $tracking_number, $tracking_provider);
        
            // $track_button = '<div id="as-root"></div><div class="as-track-button"' . $temp_slug . ' data-tracking-number="' . $tracking_number . $temp_url .'" data-support="true" data-width="400" data-size="normal" data-hide-tracking-number="true"></div>';
            // echo wpautop(sprintf('%s', $track_button));
            echo "<br><br>";
        }
        
        
        private function display_track_button_html($custom_domain, $tracking_number, $tracking_provider)
        {
            $css = '<style>.btn{position:relative; border-radius: 4px;text-decoration: none !important; border:2px solid #1e88e5;text-align:left;background-color:#1e88e5;color:#fff !important;font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;} .btn:hover{border-color: #1c95ff;background-color: #1c95ff;} .btn span{font-size: 16px;vertical-align: middle;}.btn.a:focus,.btn.a:hover{border-color:#1c95ff;background-color:#1c95ff;}.btn.a{padding:10px 6px 12px;border-radius:4px;outline:0} .btn.a{background-color:transparent}.btn.a:active,.btn.a:hover{outline:0}*,:after,:before{box-sizing:border-box}.tracking-widget .fluid-input-wrapper{display:block;overflow:hidden}.-has-tracking-number .fluid-input-wrapper{float:left}.tracking-widget input{padding:2px 6px 3px;width:100%}.tracking-widget .btn{float:right;padding:4px 10px 3px 36px;margin-left:7px}.tracking-widget .-has-tracking-number .btn,.tracking-widget .-hidden-tracking-number .btn{float:none}.tracking-widget .text-large{font-size:17.5px;padding:10px 6px 12px}.tracking-widget .btn-large{font-size:17.5px;padding:10px 20px 12px 58px}.tracking-widget .text-small{padding:2px 6px 3px;font-size:12px}.tracking-widget .btn-small{padding:2px 10px 3px 32px;font-size:12px}.icon-trackdog{left:9px;top:7px;width:17px;height:19px}.tracking-widget .btn-small .icon-trackdog{left:9px;top:7px;height:19px;width:16px}.icon-trackdog,.icon-trackdog.-large{height:28px;width:24px}.tracking-widget .btn-large .icon-trackdog{left:20px;top:7px;height:28px;width:24px}.ie9 .tracking-widget .btn-small .icon-trackdog{top:0}.-hidden-tracking-number .btn{margin-left:0}.tracking-widget+.tracking-widget{margin-top:20px}.icon-trackdog{position:absolute;display:inline-block;background-repeat:no-repeat;background-position:0 0}.tracking-widget .icon-trackdog{height:21px}.tracking-copyright{font-size:12px;padding:3px 3px 0;text-align:left}.tracking-preset{line-height:28px}.tracking-preset.large{line-height:47px}.tracking-preset.small{font-size:14px;line-height:24px} .tracking-widget .btn{padding: 1px 20px;}</style>';
        
            echo $css;
        
            $go_url = $custom_domain;
        
            //check is 17track add params
            if (strpos($custom_domain, "17track") > -1) {
                $go_url = $custom_domain . $tracking_number . "&pf=wc_d&pf_c=" . urlencode($tracking_provider);
            }
        
            //show track button
            $html = '<div class="tracking-widget"><div class="tracking-widget -has-tracking-number"><a class="btn" href="//' . $go_url . '" target="_blank">
                         <span class="btn_text">Track</span>
                     </a></div></div>';
            echo $html;
        }
    }

        /**
         * Register this class globally
         */
        if (!function_exists('getDianxiaomiInstance')) {
            function getDianxiaomiInstance()
            {
                return Dianxiaomi::Instance();
            }
        }
    }
        
        $GLOBALS['dianxiaomi'] = getDianxiaomiInstance();
    }