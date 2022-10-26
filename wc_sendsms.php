<?php
/*
Plugin Name: SendSMS
Plugin URI: https://www.sendsms.ro/ro/ecommerce/plugin-woocommerce/
Description: Use our SMS shipping solution to deliver the right information at the right time. Give your customers a superior experience!
Version: 1.2.9
Author: sendSMS
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wc_sendsms
Domain Path: /languages/
*/

$pluginDir = plugin_dir_path(__FILE__);
$pluginDirUrl = plugin_dir_url(__FILE__);
global $wc_sendsms_db_version;
$wc_sendsms_db_version = '1.2.8';

$need = false;

if (!function_exists('is_plugin_active_for_network')) {
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

// multisite 
if (is_multisite()) {
    // this plugin is network activated - Woo must be network activated 
    // this plugin is network activated - Woo must be network activated 
    // this plugin is network activated - Woo must be network activated 
    if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        $need = is_plugin_active_for_network('woocommerce/woocommerce.php') ? false : true;
        $need = is_plugin_active_for_network('woocommerce/woocommerce.php') ? false : true;
        $need = is_plugin_active_for_network('woocommerce/woocommerce.php') ? false : true;
        // this plugin is locally activated - Woo can be network or locally activated 
        // this plugin is locally activated - Woo can be network or locally activated 
        // this plugin is locally activated - Woo can be network or locally activated 
    } else {
        $need = is_plugin_active('woocommerce/woocommerce.php')  ? false : true;
    }
    // this plugin runs on a single site    
    // this plugin runs on a single site    
    // this plugin runs on a single site    
} else {
    $need =  is_plugin_active('woocommerce/woocommerce.php') ? false : true;
}

if ($need === true) {
    return;
}

# history table
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

include 'HistoryListTable.php';
# create database
function wc_sendsms_install()
{
    global $wpdb;
    global $wc_sendsms_db_version;

    $table_name = $wpdb->prefix . 'wcsendsms_history';
    $charset_collate = $wpdb->get_charset_collate();
    $installed_ver = get_option('wc_sendsms_db_version');

    if ($installed_ver != $wc_sendsms_db_version) {
        $sql = "CREATE TABLE `$table_name` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `phone` varchar(255) DEFAULT NULL,
          `status` varchar(255) DEFAULT NULL,
          `message` varchar(255) DEFAULT NULL,
          `details` longtext,
          `content` longtext,
          `type` varchar(255) DEFAULT NULL,
          `sent_on` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('wc_sendsms_db_version', $wc_sendsms_db_version);
    }
}
register_activation_hook(__FILE__, 'wc_sendsms_install');

add_action('init', 'wc_sendsms_load_textdomain');

/**
 * Load plugin textdomain.
 */
function wc_sendsms_load_textdomain()
{
    load_plugin_textdomain('wc_sendsms', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

# update db structure
function wc_sendsms_update_db_check()
{
    global $wc_sendsms_db_version;
    if (get_site_option('wc_sendsms_db_version') != $wc_sendsms_db_version) {
        wc_sendsms_install();
    }
}
add_action('plugins_loaded', 'wc_sendsms_update_db_check');

# add scripts
function wc_sendsms_load_scripts()
{
    # load jquery if it's not loaded
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }

    # script for datepicker
    wp_enqueue_style('datepickerdefault', trailingslashit(plugin_dir_url(__FILE__)) . 'datepicker/themes/default.css');
    wp_enqueue_style('datepickerdefaultdate', trailingslashit(plugin_dir_url(__FILE__)) . 'datepicker/themes/default.date.css');
    wp_enqueue_script('datepickerdefault', trailingslashit(plugin_dir_url(__FILE__)) . 'datepicker/picker.js', array('jquery'));
    wp_enqueue_script('datepickerdefaultdate', trailingslashit(plugin_dir_url(__FILE__)) . 'datepicker/picker.date.js', array('jquery'));
    wp_enqueue_script('wcsendsms', trailingslashit(plugin_dir_url(__FILE__)) . 'wc_sendsms.js', array('jquery'));

    # script & style for jquery
    wp_enqueue_style('select2', trailingslashit(plugin_dir_url(__FILE__)) . 'jquery/select2/select2.min.css');
    wp_enqueue_script('select2', trailingslashit(plugin_dir_url(__FILE__)) . 'jquery/select2/select2.min.js', array('jquery'));

    // please create also an empty JS file in your theme directory and include it too
    wp_enqueue_script('js_for_select2', trailingslashit(plugin_dir_url(__FILE__)) . 'forselect2.js', array('jquery', 'select2'));
}
add_action('admin_enqueue_scripts', 'wc_sendsms_load_scripts');

#  checkout field for opt-out
function wc_sendsms_optout($checkout)
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['optout'])) {
        $optout = $options['optout'];
    } else {
        $optout = '';
    }
    if (!empty($optout)) {
        echo '<div>';
        woocommerce_form_field('wc_sendsms_optout', array(
            'type' => 'checkbox',
            'class' => array('input-checkbox', 'form-row-wide'),
            'label' => __('&nbsp;I do not want to receive an SMS with the status of the order', 'wc_sendsms'),
        ), $checkout->get_value('wc_sendsms_optout'));
        echo '</div><div style="clear: both">&nbsp;</div>';
    }
}
add_action('woocommerce_after_order_notes', 'wc_sendsms_optout');

function wc_sendsms_optout_update_order_meta($orderId)
{
    if (isset($_POST['wc_sendsms_optout'])) {
        update_post_meta($orderId, 'wc_sendsms_optout', wc_sendsms_sanitize_bool($_POST['wc_sendsms_optout']));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'wc_sendsms_optout_update_order_meta');

# admin page
add_action('admin_menu', 'wc_sendsms_add_menu');

function wc_sendsms_add_menu()
{
    add_menu_page(
        __('SendSMS', 'wc_sendsms'),
        __('SendSMS', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_main',
        'wc_sendsms_main',
        plugin_dir_url(__FILE__) . 'images/sendsms.png'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Configuration', 'wc_sendsms'),
        __('Configuration', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_login',
        'wc_sendsms_login'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('History', 'wc_sendsms'),
        __('History', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_history',
        'wc_sendsms_history'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Campaign', 'wc_sendsms'),
        __('Campaign', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_campaign',
        'wc_sendsms_campaign'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Send a test', 'wc_sendsms'),
        __('Send a test', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_test',
        'wc_sendsms_test'
    );
}

function wc_sendsms_main()
{
?>
    <div class="wrap">
        <h2><?= __('SendSMS for WooCommerce', 'wc_sendsms') ?></h2>
        <br />
        <p><?= __('To use the module, please enter your credentials on the configuration page.', 'wc_sendsms') ?></p><br />
        <p><?= __('You don\'t have a sendSMS account?', 'wc_sendsms') ?><br />
            <?= __('Sign up for FREE', 'wc_sendsms') ?> <a href="http://www.sendsms.ro/ro" target="_blank"><?= __('here', 'wc_sendsms') ?></a>.<br />
            <?= __('You can find out more about sendSMS', 'wc_sendsms') ?> <a href="http://www.sendsms.ro/ro"><?= __('here', 'wc_sendsms') ?></a>.</p>
        <p><?= __('On the settings page, below the credentials, you\'ll find a text field for each status available in WooCommerce. You will need to enter a message for the fields to which you want to send the notification. If a field is empty, then the text message will not be sent.', 'wc_sendsms') ?></p>
        <p><?= __('Example: If you want to send a message when the status of the order changes to Completed, then you will need to fill in a message in the text field.', 'wc_sendsms') ?> <strong><?= __('"Message: Completed"', 'wc_sendsms') ?></strong>.</p><br />
        <p><?= __('You can enter variables that will be filled in according to the order data.', 'wc_sendsms') ?></p>
        <p><?= __('Example message:', 'wc_sendsms') ?> <strong><?= __('Hi {billing_first_name}. Your order with order {order_number} has been completed.', 'wc_sendsms') ?></strong></p>
        <p><?= __('The message entered must not contain diacritics. If they are entered the letters with diacritics will be replaced with their equivalent without diacritics.', 'wc_sendsms') ?></p>
        <br /><br />
        <p style="text-align: center"><a href="http://sendsms.ro" target="_blank"><img src="<?= plugin_dir_url(__FILE__) . 'images/sendsms_logo.png' ?>" /></a></p>
    </div>
<?php
}

# options
add_action('admin_init', 'wc_sendsms_admin_init');
function wc_sendsms_admin_init()
{
    # for login
    register_setting(
        'wc_sendsms_plugin_options',
        'wc_sendsms_plugin_options',
        'wc_sendsms_plugin_options_validate'
    );
    add_settings_section(
        'wc_sendsms_plugin_login',
        '',
        'wc_sendsms_plugin_login_section_text',
        'wc_sendsms_plugin'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_username',
        __('Username', 'wc_sendsms'),
        'wc_sendsms_settings_display_username',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_password',
        __('Password / API Key', 'wc_sendsms'),
        'wc_sendsms_settings_display_password',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_from',
        __('Shipper label', 'wc_sendsms'),
        'wc_sendsms_settings_display_from',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_cc',
        __('Country Code', 'wc_sendsms'),
        'wc_sendsms_settings_display_cc',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation',
        __('SMS sending simulation', 'wc_sendsms'),
        'wc_sendsms_settings_display_simulation',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation_number',
        __('Simulation phone number', 'wc_sendsms'),
        'wc_sendsms_settings_display_simulation_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner',
        __('Send an SMS to each new order', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_short',
        __('Short URL?', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_short',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_gdpr',
        __('Add unsubscribe link?', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_gdpr',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_number',
        __('The phone number where the messages will be sent', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_content',
        __('Message', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_content',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_optout',
        __('Opt-out in cart', 'wc_sendsms'),
        'wc_sendsms_settings_display_optout',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_content',
        __('Status Updates', 'wc_sendsms'),
        'wc_sendsms_settings_display_content',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_enabled',
        __('', 'wc_sendsms'),
        'wc_sendsms_settings_display_enabled',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
}

function wc_sendsms_login()
{
?>
    <div class="wrap">
        <h2><?= __('SendSMS - Login data', 'wc_sendsms') ?></h2>
        <h3><?php
            $options = get_option('wc_sendsms_plugin_options');
            $username = "";
            $password = "";
            $from = "";
            wc_sendsms_get_account_info($username, $password, $from, $options);

            $results = json_decode(wp_remote_retrieve_body(wp_remote_get('http://api.sendsms.ro/json?action=user_get_balance&username=' . urlencode($username) . '&password=' . urlencode($password))), true);

            if ($results['status'] >= 0) {
                echo __('You have ', 'wc_sendsms') . $results['details'] . __(' euro in your sendSMS account.', 'wc_sendsms');
            } else {
                echo __('The plugin is not configured.', 'wc_sendsms');
            }
            ?></h3>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('wc_sendsms_plugin_options'); ?>
            <?php do_settings_sections('wc_sendsms_plugin'); ?>

            <input name="Submit" type="submit" class="button button-primary button-large" value="<?= __('Save', 'wc_sendsms') ?>" />
        </form>
    </div>
<?php
}

function wc_sendsms_get_woocommerce_product_list()
{
    $full_product_list = array();
    $loop = new WP_Query(array('post_type' => array('product', 'product_variation'), 'posts_per_page' => -1));

    while ($loop->have_posts()) : $loop->the_post();
        $theid = get_the_ID();
        if (get_post_type() == 'product_variation') {
            $product = new WC_Product_Variation($theid);
        } else {
            $product = new WC_Product($theid);
        }
        // its a variable product
        if (get_post_type() == 'product_variation') {
            $parent_id = wp_get_post_parent_id($theid);
            $sku = get_post_meta($theid, '_sku', true);
            $thetitle = get_the_title($parent_id);

            // ****** Some error checking for product database *******
            // check if variation sku is set
            if ($sku == '') {
                if ($parent_id == 0) {
                    // Remove unexpected orphaned variations.. set to auto-draft
                    $false_post = array();
                    $false_post['ID'] = $theid;
                    $false_post['post_status'] = 'auto-draft';
                    wp_update_post($false_post);
                    //if (function_exists(add_to_debug)) add_to_debug('false post_type set to auto-draft. id='.$theid);
                } else {
                    // there's no sku for this variation > copy parent sku to variation sku
                    // & remove the parent sku so the parent check below triggers
                    $sku = get_post_meta($parent_id, '_sku', true);
                    //if (function_exists(add_to_debug)) add_to_debug('empty sku id='.$theid.'parent='.$parent_id.'setting sku to '.$sku);
                    update_post_meta($theid, '_sku', $sku);
                    update_post_meta($parent_id, '_sku', '');
                }
            }
            // ****************** end error checking *****************

            // its a simple product
        } else {
            $sku = get_post_meta($theid, '_sku', true);
            $thetitle = get_the_title();
        }
        // add product to array but don't add the parent of product variations
        if (!empty($sku)) $full_product_list[] = array($thetitle, $sku, $theid);
    endwhile;
    wp_reset_query();
    // sort into alphabetical order, by title
    sort($full_product_list);
    return $full_product_list;
}

function wc_sendsms_test()
{
    if (isset($_POST) && !empty($_POST)) {
        if (empty($_POST['wc_sendsms_phone'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>' . __('You have not entered your phone number!', 'wc_sendsms') . '</p>
            </div>';
        }
        if (empty($_POST['wc_sendsms_message'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>' . __('You have not entered a message!', 'wc_sendsms') . '</p>
            </div>';
        }
        if (!empty($_POST['wc_sendsms_message']) && !empty($_POST['wc_sendsms_phone'])) {
            $options = get_option('wc_sendsms_plugin_options');
            $username = '';
            $password = '';
            $short = filter_var(isset($_POST['wc_sendsms_url']) ? $_POST['wc_sendsms_url'] : "false", FILTER_VALIDATE_BOOLEAN);
            $gdpr = filter_var(isset($_POST['wc_sendsms_gdpr']) ? $_POST['wc_sendsms_gdpr'] : "false", FILTER_VALIDATE_BOOLEAN);
            if (!empty($options) && is_array($options) && isset($options['username'])) {
                $username = $options['username'];
            }
            if (!empty($options) && is_array($options) && isset($options['password'])) {
                $password = $options['password'];
            }
            if (!empty($options) && is_array($options) && isset($options['from'])) {
                $from = $options['from'];
            }
            if (!empty($username) && !empty($password) && !empty($from)) {
                $phone = wc_sendsms_validate_phone($_POST['wc_sendsms_phone']);
                if (!empty($phone)) {
                    wc_sendsms_send($username, $password, $phone, sanitize_textarea_field($_POST['wc_sendsms_message']), $from, 'test', $short, $gdpr);
                    echo '<div class="notice notice-success is-dismissible">
                    <p>' . __('The message was sent.', 'wc_sendsms') . '</p>
                </div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible">
                    <p>' . __('The validated phone number is empty!', 'wc_sendsms') . '</p>
                </div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible">
                    <p>' . __('You have not configured the module!', 'wc_sendsms') . '</p>
                </div>';
            }
        }
    }
?>
    <div class="wrap">
        <h2><?= __('SendSMS - Send an SMS test', 'wc_sendsms') ?></h2>
        <form method="post" action="<?= admin_url('admin.php?page=wc_sendsms_test') ?>">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?= __('Phone number', 'wc_sendsms') ?></th>
                        <td><input type="text" name="wc_sendsms_phone" style="width: 400px;" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= __('Short URL? (Please use only links starting with https:// or http://)', 'wc_sendsms') ?></th>
                        <td><input type="checkbox" name="wc_sendsms_url" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= __('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'wc_sendsms') ?></th>
                        <td><input type="checkbox" name="wc_sendsms_gdpr" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= __('Message', 'wc_sendsms') ?></th>
                        <td>
                            <textarea name="wc_sendsms_message" class="wc_sendsms_content" style="width: 400px; height: 100px;"></textarea>
                            <p><?= __("The field is empty", 'wc_sendsms') ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p style="clear: both;"><button type="submit" class="button button-primary button-large" id="wc_sendsms_send_test"><?= __('Send the message', 'wc_sendsms') ?></button></p>
        </form>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", (event) => {
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

                wc_sendsms_content.addEventListener("input", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });
                wc_sendsms_content.addEventListener("change", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });

                function lenghtCounter(textarea, counter) {
                    var lenght = textarea.value.length;
                    var messages = lenght / 160 + 1;
                    if (lenght > 0) {
                        if (lenght % 160 === 0) {
                            messages--;
                        }
                        counter.textContent = "<?= __('The approximate number of messages: ', 'wc_sendsms'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                    } else {
                        counter.textContent = "<?= __('The field is empty', 'wc_sendsms'); ?>";
                    }
                }
            });
        </script>
    </div>
<?php
}

function wc_sendsms_campaign()
{
    global $wpdb;

    # get all products
    $products = wc_sendsms_get_woocommerce_product_list();

    $billing_states = $wpdb->get_results('SELECT DISTINCT meta_value FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = \'_billing_state\' ORDER BY meta_value ASC');



    $orders = array();
    if (!isset($_REQUEST['filtering'])) {
        $orders = wc_sendsms_get_all_orders();
    }
    if (isset($_REQUEST['filtering']) && $_REQUEST['filtering'] === "true") {
        if (!wp_verify_nonce($_GET['_wpnonce'], "wc_sendsms_send_campaign")) die("You are not supposed to be here");
        $orders = wc_sendsms_get_orders_filtered(
            isset($_GET['perioada_start']) ? $_GET['perioada_start'] : "",
            isset($_GET['perioada_final']) ? $_GET['perioada_final'] : "",
            isset($_GET['suma']) ? $_GET['suma'] : "",
            isset($_GET['judete']) ? $_GET['judete'] : "",
            isset($_GET['produse']) ? $_GET['produse'] : ""
        );
    }

    $phones = array();
    if (count($orders)) {
        foreach ($orders as $order) {
            $phone = wc_sendsms_validate_phone($order->_billing_phone);
            if (!empty($phone)) {
                $phones[] = $phone;
            }
        }
    }
    $phones = array_unique($phones);

    // // Generate dumy phones for testing
    // $phones = array();
    // for ($i = 0; $i < 10; $i++) {
    //     $phones[] = "4021" . wc_sendsms_randomNumberSequence();
    // }

?>
    <div class="wrap">
        <h2><?= __('SendSMS - Campaign', 'wc_sendsms') ?></h2>

        <!-- This is the filtering form -->
        <form method="GET" action="">
            <?php
            wp_nonce_field("wc_sendsms_send_campaign");
            ?>
            <input type="hidden" name="page" value="wc_sendsms_campaign" />
            <input type="hidden" name="filtering" value="true" />
            <div style="width: 100%; clear: both;">
                <div style="width: 48%; float: left;">
                    <p><?= __('Period', 'wc_sendsms') ?> <input type="text" class="wcsendsmsdatepicker" name="perioada_start" value="<?= isset($_GET['perioada_start']) ? wc_sendsms_sanitize_event_time($_GET['perioada_start']) : '' ?>" /> - <input type="text" class="wcsendsmsdatepicker" name="perioada_final" value="<?= isset($_GET['perioada_final']) ? wc_sendsms_sanitize_event_time($_GET['perioada_final']) : '' ?>" /></p>
                </div>
                <div style="width: 48%; float: left">
                    <p><?= __('Minimum amount per order:', 'wc_sendsms') ?> <input type="number" name="suma" value="<?= isset($_GET['suma']) ? wc_sendsms_sanitize_float($_GET['suma']) : '0' ?>" /></p>
                </div>
                <div style="width: 100%; clear: both;">
                    <div style="width: 48%; float: left;" class="mySelect">
                        <p><?= __('The purchased product (leave blank to select all products):', 'wc_sendsms') ?>
                            <select id="produse_selectate" name="produse[]" multiple="multiple" style="width:80%;max-width:25em;">
                                <?php
                                for ($i = 0; $i < count($products); $i++) {
                                    $selected = false;
                                    if (isset($_GET['produse'])) {
                                        $lenght = count($_GET['produse']);
                                        for ($j = 0; $j < $lenght; $j++) {
                                            if (strcmp($_GET['produse'][$j], "id_" . $products[$i][2]) === 0) {
                                                $selected = true;
                                            }
                                        }
                                    }
                                ?>
                                    <option value="<?= "id_" . esc_attr($products[$i][2]) ?>" <?= $selected ? 'selected="selected"' : '' ?>><?= esc_attr($products[$i][0]) . " - " . esc_attr($products[$i][1]) ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </p>
                    </div>
                    <div style="width: 48%; float: left;">
                        <p><?= __('Billing County (leave blank to select all counties):', 'wc_sendsms') ?>
                            <select id="judete_selectate" name="judete[]" multiple="multiple" style="width:80%;max-width:25em;">
                                <?php
                                for ($i = 0; $i < count($billing_states); $i++) {
                                    $selected = false;
                                    if (isset($_GET['judete'])) {
                                        $lenght = count($_GET['judete']);
                                        for ($j = 0; $j < $lenght; $j++) {
                                            if (strcmp($_GET['judete'][$j], "id_" . $billing_states[$i]->meta_value) === 0) {
                                                $selected = true;
                                            }
                                        }
                                    }
                                ?>
                                    <option value="<?= "id_" . esc_attr($billing_states[$i]->meta_value) ?>" <?= $selected ? 'selected="selected"' : '' ?>><?= esc_attr($billing_states[$i]->meta_value) ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </p>
                    </div>
                </div>
            </div>
            <div style="width: 100%; clear: both;">
                <button type="submit" class="button button-default button-large aligncenter" value="filter"><?= __('Filter', 'wc_sendsms') ?></button>
            </div>
        </form>


        <hr />
        <h3><?= __('Filter results:', 'wc_sendsms') ?> <?= count($phones) ?> <?= __('phone number(s)', 'wc_sendsms') ?></h3>

        <!-- Send campaign form -->
        <form method="POST" action="">
            <input type="hidden" name="page" value="wc_sendsms_campaign" />
            <input type="hidden" name="action" value="send_campaign" />
            <div style="width: 100%; clear: both; padding-top: 20px;">
                <div style="width: 73%; float: left">
                    <div><?= __('Message:', 'wc_sendsms') ?> <br />
                        <textarea name="content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 90%; height: 250px;"></textarea>
                        <p><?= __('The field is empty', 'wc_sendsms') ?></p>
                    </div>
                </div>
                <div style="width: 25%; float: left">
                    <p><?= __('Phone numbers:', 'wc_sendsms') ?> <br /></p>
                    <div style="margin-bottom: 10px">
                        <input type="checkbox" id="wc_sendsms_to_all" class="wc_sendsms_to_all" name="wc_sendsms_to_all" checked />
                        <?= __('Send SMS to every number.', 'wc_sendsms') ?></label>
                    </div>
                    <select name="phones[]" id="phones" multiple="MULTIPLE" style="width: 90%; height: 250px" size="<?= empty($phones) ? 0 : count($phones) ?>">
                        <?php
                        if (!empty($phones)) :
                            foreach ($phones as $phone) :
                        ?>
                                <option value="<?= $phone ?>" selected><?= $phone ?></option>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                </div>
            </div>
            <p style="clear: both;">
                <button type="submit" class="button button-primary button-large" id="wc_sendsms_send_campaign"><?= __('Send the message', 'wc_sendsms') ?></button>
                <button type="button" class="button button-primary button-large" name="action" value="estimate_price" id="wc_sendsms_send_campaign_estimate_price"><?= __('Estimate the price', 'wc_sendsms') ?></button>
            </p>
        </form>
    </div>
    <?= wc_sendsms_javascript_estimate_price(); //just add the check price as a separated function
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", (event) => {
            var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

            wc_sendsms_content.addEventListener("input", (event) => {
                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
            });
            wc_sendsms_content.addEventListener("change", (event) => {
                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
            });

            function lenghtCounter(textarea, counter) {
                var lenght = textarea.value.length;
                var messages = lenght / 160 + 1;
                if (lenght > 0) {
                    if (lenght % 160 === 0) {
                        messages--;
                    }
                    counter.textContent = "<?= __('The approximate number of messages: ', 'wc_sendsms'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                } else {
                    counter.textContent = "<?= __('The field is empty', 'wc_sendsms'); ?>";
                }
            }
        });
    </script>
<?php
}

function wc_sendsms_javascript_send()
{ ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_campaign').on('click', function() {
                jQuery('#wc_sendsms_send_campaign').html("<?= __('It\'s being sent...', 'wc_sendsms') ?>");
                jQuery('#wc_sendsms_send_campaign').attr('disabled', 'disabled');
                all = jQuery('#wc_sendsms_to_all').is(":checked");
                if (all) {
                    phones = '';
                    produse = <?= isset($_GET['produse']) ? json_encode($_GET['produse']) : "[]" ?>;
                    judete = <?= isset($_GET['judete']) ? json_encode($_GET['judete']) : "[]" ?>;
                    suma = "<?= isset($_GET['suma']) ? $_GET['suma'] : "" ?>";
                    perioada_final = "<?= isset($_GET['perioada_final']) ? $_GET['perioada_final'] : "" ?>";
                    perioada_start = "<?= isset($_GET['perioada_start']) ? $_GET['perioada_start'] : "" ?>";
                    filtering = "<?= isset($_REQUEST['filtering']) ? true : false ?>";
                } else {
                    phones = jQuery('#phones').val().join("|");
                    produse = "";
                    judete = "";
                    suma = "";
                    perioada_final = "";
                    perioada_start = "";
                    filtering = "";
                }
                var data = {
                    'security': '<?= wp_create_nonce('wc_sendsms_send_campaign') ?>',
                    'action': 'wc_sendsms_campaign',
                    'all': all,
                    'phones': phones,
                    'perioada_start': perioada_start,
                    'perioada_final': perioada_final,
                    'suma': suma,
                    'judete': judete,
                    'produse': produse,
                    'filtering': filtering,
                    'content': jQuery('#wc_sendsms_content').val(),
                    // 'short': jQuery('#wc_sendsms_short').is(":checked"),
                    // 'gdpr': jQuery('#wc_sendsms_gdpr').is(":checked")
                };
                jQuery.post(ajaxurl, data, function(response) {
                    jQuery('#wc_sendsms_send_campaign').html('<?= __('Send the message', 'wc_sendsms') ?>');
                    jQuery('#wc_sendsms_send_campaign').removeAttr('disabled');
                    alert(response);
                });
            });
        });
    </script>
<?php
}

add_action('admin_footer', 'wc_sendsms_javascript_send');

function wc_sendsms_ajax_send()
{
    if (!check_ajax_referer('wc_sendsms_send_campaign', 'security', false)) {
        wp_die();
    }
    if (!empty($_POST['content'])) {
        if (isset($_POST['all']) && $_POST['all'] === "true") {
            $orders = array();
            if (empty($_POST['filtering'])) {
                $orders = wc_sendsms_get_all_orders();
            }
            if (isset($_POST['filtering']) && $_POST['filtering'] === "1") {
                $orders = wc_sendsms_get_orders_filtered(
                    isset($_POST['perioada_start']) ? $_POST['perioada_start'] : "",
                    isset($_POST['perioada_final']) ? $_POST['perioada_final'] : "",
                    isset($_POST['suma']) ? $_POST['suma'] : "",
                    isset($_POST['judete']) ? $_POST['judete'] : "",
                    isset($_POST['produse']) ? $_POST['produse'] : ""
                );
            }
            $phones = array();
            if (count($orders)) {
                foreach ($orders as $order) {
                    $phone = wc_sendsms_validate_phone($order->_billing_phone);
                    if (!empty($phone)) {
                        $phones[] = $phone;
                    }
                }
            }
            $phones = array_unique($phones);
        } else {
            $phones = explode("|", $_POST['phones']);
            if (count($phones) === 0) {
                echo __('You must choose at least one phone number.', 'wc_sendsms');
                wp_die();
            }
        }
    } else {
        echo __('You must complete the message first.', 'wc_sendsms');
        wp_die();
    }
    global $pluginDir;
    if ($file = fopen("$pluginDir/batches/batch.csv", "w")) {
        $options = get_option('wc_sendsms_plugin_options');
        $username = '';
        $password = '';
        $from = '';
        if (!empty($options) && is_array($options) && isset($options['username'])) {
            $username = $options['username'];
        } else {
            echo __('You did not enter a username', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['password'])) {
            $password = $options['password'];
        } else {
            echo __('You have not entered a password', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['from'])) {
            $from = $options['from'];
        } else {
            $from = '';
        }
        $headers = array(
            "message",
            "to",
            "from"
        );
        fputcsv($file, $headers);
        foreach ($phones as $phone) {
            fputcsv($file, array(
                $_POST['content'],
                $phone,
                $from
            ), ',', '"', '');
        }
        // $start_time = "2970-01-01 02:00:00";
        $start_time = "";
        $name = 'Wordpress - ' . get_site_url() . ' - ' . uniqid();
        $data = file_get_contents("$pluginDir/batches/batch.csv");
        $results = json_decode(wp_remote_retrieve_body(wp_remote_post(
            'https://api.sendsms.ro/json?action=batch_create&username=' . urlencode($username) . '&password=' . urlencode($password) . '&start_time=' . urlencode($start_time) . '&name=' . urlencode($name),
            array(
                'body' => array('data' => $data)
            )
        )), true);
        if (!isset($results['status']) || $results['status'] < 0) {
            echo json_encode($results);
            wp_die();
        }
        //log into history table
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcsendsms_history';
        $wpdb->query(
            $wpdb->prepare(
                "
                INSERT INTO $table_name
                (`phone`, `status`, `message`, `details`, `content`, `type`, `sent_on`)
                VALUES ( %s, %s, %s, %s, %s, %s, %s)
            ",
                __("Go to hub.sendsms.ro", 'wc_sendsms'),
                isset($results['status']) ? $results['status'] : '',
                isset($results['message']) ? $results['message'] : '',
                isset($results['details']) ? $results['details'] : '',
                __("We created your campaign. Go and check the batch called: ", 'wc_sendsms') . $name,
                __("Batch Campaign", 'wc_sendsms'),
                date('Y-m-d H:i:s')
            )
        );
        fclose($file);
        if (!unlink("$pluginDir/batches/batch.csv")) {
            echo "Unable to delete previous batch file! Please check file/folder permisions ($pluginDir/batches/batch.csv)";
            wp_die();
        }
        echo __("Success", "wc_sendsms");
        wp_die();
    } else {
        echo "Unable to open/create batch file! Please check file/folder permisions ($pluginDir/batches/batch.csv)";
        wp_die();
    }
}

add_action('wp_ajax_wc_sendsms_campaign', 'wc_sendsms_ajax_send');

function wc_sendsms_javascript_estimate_price()
{ ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_campaign_estimate_price').on('click', function() {
                all = jQuery('#wc_sendsms_to_all').is(":checked");
                if (all) {
                    phones = jQuery('select[id=phones] > option').length;
                } else {
                    phones = jQuery('#phones').val().length;
                }
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];
                var lenght = wc_sendsms_content.value.length;
                var messages = lenght / 160 + 1;
                if (lenght > 0) {
                    if (lenght % 160 === 0) {
                        messages--
                    }
                    messages = Math.floor(messages);
                    price = <?= get_option('wc-sendsms-default-price', 0) ?>;
                    if (price > 0) {
                        alert("<?= __('The estimate price is: ', 'wc_sendsms') ?>" + parseFloat(messages * price * phones).toPrecision(4) + "<?= __(' (This is just an estimation, and not the actual price)', 'wc_sendsms') ?>");
                    } else {
                        alert("<?= __('Please send a message first', 'wc_sendsms') ?>");
                    }
                } else {
                    alert("<?= __('Please fill the message box first', 'wc_sendsms') ?>")
                }
            });
        });
    </script>
<?php
}

add_action('wp_ajax_wc_sendsms_estimate_price', 'wc_sendsms_ajax_estimate_price');

function wc_sendsms_history()
{
?>
    <div class="wrap">
        <h2><?= __('SendSMS - Historic', 'wc_sendsms') ?></h2>
        <form method="get">
            <?php
            $_table_list = new WC_SendSMS_History_List_Table();
            $_table_list->prepare_items();
            echo '<input type="hidden" name="page" value="wc_sendsms_history" />';

            $_table_list->views();
            $_table_list->search_box(__('Search', 'wc_sendsms'), 'key');
            $_table_list->display();
            ?>
        </form>
    </div>
<?php
}

function wc_sendsms_plugin_login_section_text()
{
    //
}

function wc_sendsms_settings_display_username()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['username'])) {
        $username = esc_html($options['username']);
    } else {
        $username = '';
    }
    echo '<input id="wc_sendsms_settings_username" name="wc_sendsms_plugin_options[username]" type="text" value="' . $username . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_password()
{
    $options = get_option('wc_sendsms_plugin_options');

    if (!empty($options) && is_array($options) && isset($options['password'])) {
        $password = esc_html($options['password']);
    } else {
        $password = '';
    }
    echo '<input id="wc_sendsms_settings_password" name="wc_sendsms_plugin_options[password]" type="password" value="' . $password . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_from()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['from'])) {
        $from = esc_html($options['from']);
    } else {
        $from = '';
    }
    echo '<input id="wc_sendsms_settings_from" name="wc_sendsms_plugin_options[from]" type="text" value="' . $from . '" style="width: 400px;" /> <span>' . __('maximum 11 alpha-numeric characters', 'wc_sendsms') . '</span>';
}

function wc_sendsms_settings_display_cc()
{
    include 'cc.php';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['cc'])) {
        $cc = esc_html($options['cc']);
    } else {
        $cc = '';
    }
?>
    <select id="wc_sendsms_settings_cc" name="wc_sendsms_plugin_options[cc]">
        <option value="INT">International</option>
        <?php
        foreach ($country_codes as $key => $value) {
            echo "<option value='$key' " . ($cc == $key ? "selected" : "") . ">$key (+$value)</option>";
        }
        ?>
    </select>
    <?php
}

function wc_sendsms_settings_display_simulation()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['simulation'])) {
        $simulation = $options['simulation'];
    } else {
        $simulation = '';
    }
    echo '<input id="wc_sendsms_settings_simulation" name="wc_sendsms_plugin_options[simulation]" type="checkbox" value="1" ' . (!empty($simulation) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner'])) {
        $send_to_owner = $options['send_to_owner'];
    } else {
        $send_to_owner = '';
    }
    echo '
    <input id="wc_sendsms_settings_send_to_owner" name="wc_sendsms_plugin_options[send_to_owner]" type="checkbox" value="1" ' . (!empty($send_to_owner) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner_short()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_short'])) {
        $send_to_owner_short = $options['send_to_owner_short'];
    } else {
        $send_to_owner_short = '';
    }
    echo '<label>
    <input id="wc_sendsms_settings_send_to_owner_short" name="wc_sendsms_plugin_options[send_to_owner_short]" type="checkbox" value="1" ' . (!empty($send_to_owner_short) ? 'checked="checked"' : '') . ' />' . __('Please use only links starting with https:// or http://', 'wc_sendsms') . '</label>';
}

function wc_sendsms_settings_display_send_to_owner_gdpr()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_gdpr'])) {
        $send_to_owner_gdpr = $options['send_to_owner_gdpr'];
    } else {
        $send_to_owner_gdpr = '';
    }
    echo '<label>
    <input id="wc_sendsms_settings_send_to_owner_gdpr" name="wc_sendsms_plugin_options[send_to_owner_gdpr]" type="checkbox" value="1" ' . (!empty($send_to_owner_gdpr) ? 'checked="checked"' : '') . ' />' . __('You must specify the key message {gdpr}. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.', 'wc_sendsms') . '</label>';
}

function wc_sendsms_settings_display_simulation_number()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['simulation_number'])) {
        $number = $options['simulation_number'];
    } else {
        $number = '';
    }
    echo '
    <input id="wc_sendsms_settings_simulation_number" name="wc_sendsms_plugin_options[simulation_number]" type="text" value="' . $number . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_send_to_owner_number()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_number'])) {
        $number = esc_html($options['send_to_owner_number']);
    } else {
        $number = '';
    }
    echo '
    <input id="wc_sendsms_settings_send_to_owner_number" name="wc_sendsms_plugin_options[send_to_owner_number]" type="text" value="' . $number . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_optout()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['optout'])) {
        $optout = $options['optout'];
    } else {
        $optout = '';
    }
    echo '
    <input id="wc_sendsms_settings_optout" name="wc_sendsms_plugin_options[optout]" type="checkbox" value="1" ' . (!empty($optout) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner_content()
{
    echo '<p>' . __('Variable available:', 'wc_sendsms') . ' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_content'])) {
        $content = esc_html($options['send_to_owner_content']);
    } else {
        $content = "";
    }

    echo '<div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_send_to_owner_content" name="wc_sendsms_plugin_options[send_to_owner_content]" style="width: 400px; height: 100px;" class="wc_sendsms_content">' . (!empty($content) ? $content : '') . '</textarea>
                <p></p>
            </div>
            <div style="width: 45%; float: left">
            </div>
        </div>';
}

function wc_sendsms_settings_display_enabled()
{
}

function wc_sendsms_settings_display_content()
{
    $examples = array(
        'wc-pending' => __('The order with the number {order_number} has been placed successfully and will be shipped as soon as we receive your payment in the amount of {order_total} EURO. sitename.com', 'wc_sendsms'),
        'wc-processing' => __('The order with the number {order_number} is being processed and is to be delivered. sitename.com', 'wc_sendsms'),
        'wc-on-hold' => __('The order with the number {order_number} is pending, one or more products are missing', 'wc_sendsms'),
        'wc-completed' => __('The order {order_number} has been prepared and will be delivered to the Courier. Payment: {order_total} LEI. Thank you, sitename.com', 'wc_sendsms'),
        'wc-cancelled' => __('The order with the number {order_number} has been canceled. For details: sitename.com', 'wc_sendsms'),
        'wc-refunded' => __('Refund request for order {order_number} has been completed.', 'wc_sendsms'),
        'wc-failed' => __('There is a problem processing the payment for the order with the number {order_number}. Please contact us.', 'wc_sendsms')
    );
    echo '<p>' . __('Variable available:', 'wc_sendsms') . ' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = $options['content'];
        if (isset($options['enabled'])) {
            $enabled = $options['enabled'];
        } else {
            $enabled = array();
        }
        if (isset($options['short'])) {
            $short = $options['short'];
        } else {
            $short = array();
        }
        if (isset($options['gdpr'])) {
            $gdpr = $options['gdpr'];
        } else {
            $gdpr = array();
        }
    } else {
        $content = array();
        $enabled = array();
        $short = array();
        $gdpr = array();
    }

    $statuses = wc_get_order_statuses();
    foreach ($statuses as $key => $value) {
        $shortChecked = false;
        $gdprChecked = false;
        $checked = false;
        if (isset($enabled[$key])) {
            $checked = true;
        }
        if (isset($short[$key])) {
            $shortChecked = true;
        }
        if (isset($gdpr[$key])) {
            $gdprChecked = true;
        }

        echo '  <p style="clear: both; padding-top: 10px;">' . __('Message: ', 'wc_sendsms') . $value . '</p><p><label><input type="checkbox" name="wc_sendsms_plugin_options[enabled][' . $key . ']" value="1" ' . ($checked ? 'checked="checked"' : '') . ' /> ' . __('Activated', 'wc_sendsms') . '</label></p>
                <label style="width:40%;"><input type="checkbox" name="wc_sendsms_plugin_options[short][' . $key . ']" value="1" ' . ($shortChecked ? 'checked="checked"' : '') . ' />' . __('Short URL? (Please use only links starting with https:// or http://)', 'wc_sendsms') . '</label>
                <label style="display:block; width:40%;"><input type="checkbox" name="wc_sendsms_plugin_options[gdpr][' . $key . ']" value="1" ' . ($gdprChecked ? 'checked="checked"' : '') . ' />' . __('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'wc_sendsms') . '</label>
        <div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_content_' . $key . '" name="wc_sendsms_plugin_options[content][' . $key . ']" style="width: 400px; height: 100px;" class="wc_sendsms_content">' . (isset($content[$key]) ? $content[$key] : '') . '</textarea>
                <p></p>
            </div>
            <div style="width: 45%; float: left">
            ';
        if (isset($examples[$key])) {
            echo __('Example: ', 'wc_sendsms') . $examples[$key];
        }
        echo '
            </div>
        </div>';
    }

    echo    '
            <script type="text/javascript">
                document.addEventListener("DOMContentLoaded", (event) => {
                    var wc_sendsms_content = document.getElementsByClassName(\'wc_sendsms_content\');

                    for (var i = 0; i < wc_sendsms_content.length; i++) {
                        var wc_sendsms_element = wc_sendsms_content[i];
                        wc_sendsms_element.addEventListener("input", (event) => 
                            {
                                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                            });
                        wc_sendsms_element.addEventListener("change", (event) => 
                            {
                                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                            });
                        lenghtCounter(wc_sendsms_element, wc_sendsms_element.nextElementSibling);
                        function lenghtCounter(textarea, counter)
                        {
                            var lenght = textarea.value.length;
                            var messages = lenght / 160 + 1;
                            if(lenght > 0)
                            {
                                if(lenght % 160 === 0)
                                {
                                    messages--;
                                }
                                counter.textContent = "' . __('The approximate number of messages: ', 'wc_sendsms') . '" + Math.floor(messages) + " (" + lenght + ")";
                            }else
                            {
                                counter.textContent = "' . __('The field is empty', 'wc_sendsms') . '";
                            }
                        }
                    };
                });
            </script>';
}

function wc_sendsms_plugin_options_validate($input)
{
    return $input;
}

# magic
add_action("woocommerce_order_status_changed", "wc_sendsms_order_status_changed");

function wc_sendsms_order_status_changed($order_id, $checkout = null)
{
    $order = new WC_Order($order_id);
    $status = $order->get_status();
    $order_meta = get_post_meta($order_id);

    # check if user opted out for the order
    if (isset($order_meta['wc_sendsms_optout'])) {
        return;
    }

    $options = get_option('wc_sendsms_plugin_options');

    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = isset($options['content']) ? $options['content'] : array();
        $enabled = isset($options['enabled']) ? $options['enabled'] : array();
        $short = isset($options['short']) ? $options['short'] : array();
        $gdpr = isset($options['gdpr']) ? $options['gdpr'] : array();
    } else {
        $content = array();
        $enabled = array();
        $short = array();
        $gdpr = array();
    }

    wc_sendsms_get_account_info($username, $password, $from, $options);

    if (!empty($username) && !empty($password)) {
        if (isset($content['wc-' . $status]) && !empty($content['wc-' . $status]) && isset($enabled['wc-' . $status])) {
            # replace variables
            $message = $content['wc-' . $status];
            wc_sendsms_replace_characters($message, $order, $order_id);

            # check if simulation is on and number is entered
            if (!empty($options) && is_array($options) && isset($options['content']) && isset($options['simulation']) && !empty($options['simulation_number'])) {
                # generate valid phone number
                $phone = wc_sendsms_validate_phone($options['simulation_number']);
            } else {
                # generate valid phone number
                $phone = wc_sendsms_validate_phone($order->billing_phone);
            }

            if (!empty($phone)) {
                # send sms
                wc_sendsms_send($username, $password, $phone, $message, $from, 'order', isset($short['wc-' . $status]) ? true : false, isset($gdpr['wc-' . $status]) ? true : false);
            }
        }
    }
}

# magic - 2
add_action('woocommerce_new_order', 'wc_sendsms_new_order');

function wc_sendsms_new_order($order_id)
{
    $options = get_option('wc_sendsms_plugin_options');

    if (isset($options) && isset($options['send_to_owner']) && isset($options['send_to_owner_number']) && isset($options['send_to_owner_content'])) {

        $order = new WC_Order($order_id);

        wc_sendsms_get_account_info($username, $password, $from, $options);

        if (!empty($username) && !empty($password)) {
            $phone = wc_sendsms_validate_phone($options['send_to_owner_number']);
            $message = $options['send_to_owner_content'];
            $short = $options['send_to_owner_short'] == 1 ? true : false;
            $gdpr = $options['send_to_owner_gdpr'] == 1 ? true : false;
            wc_sendsms_replace_characters($message, $order, $order_id);

            if (!empty($phone)) {
                # send sms
                wc_sendsms_send($username, $password, $phone, $message, $from, 'new order', $short, $gdpr);
            }
        }
    }
};

# afisare casuta de trimitere sms in comenzi
add_action('add_meta_boxes', 'wc_sendsms_order_details_meta_box');
function wc_sendsms_order_details_meta_box()
{
    add_meta_box(
        'wc_sendsms_meta_box',
        __('Send SMS', 'wc_sendsms'),
        'wc_sendsms_order_details_sms_box',
        'shop_order',
        'side',
        'high'
    );
}

function wc_sendsms_order_details_sms_box($post)
{
    ?>
        <input type="hidden" name="wc_sendsms_order_id" id="wc_sendsms_order_id" value="<?= $post->ID ?>" />
        <p><?= __('Phone:', 'wc_sendsms') ?></p>
        <p><input type="text" name="wc_sendsms_phone" id="wc_sendsms_phone" style="width: 100%" /></p>
        <p><?= __('Short URL? (Please use only links starting with https:// or http://)', 'wc_sendsms') ?></p>
        <p><input type="checkbox" name="wc_sendsms_short" id="wc_sendsms_short" /></p>
        <p><?= __('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'wc_sendsms') ?></p>
        <p><input type="checkbox" name="wc_sendsms_gdpr" id="wc_sendsms_gdpr" /></p>
        <p><?= __('Message:', 'wc_sendsms') ?></p>
        <div>
            <textarea name="wc_sendsms_content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 100%; height: 100px;"></textarea>
            <p><?= __('The field is empty', 'wc_sendsms') ?></p>
        </div>
        <p><button type="submit" class="button" id="wc_sendsms_send_single"><?= __('Send the message', 'wc_sendsms') ?></button></p>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", (event) => {
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

                wc_sendsms_content.addEventListener("input", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });
                wc_sendsms_content.addEventListener("change", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });

                function lenghtCounter(textarea, counter) {
                    var lenght = textarea.value.length;
                    var messages = lenght / 160 + 1;
                    if (lenght > 0) {
                        if (lenght % 160 === 0) {
                            messages--;
                        }
                        counter.textContent = "<?= __('The approximate number of messages: ', 'wc_sendsms'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                    } else {
                        counter.textContent = "<?= __('The field is empty', 'wc_sendsms'); ?>";
                    }
                }
            });
        </script>
    <?php
}

function wc_sendsms_javascript_send_single()
{ ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                jQuery('#wc_sendsms_send_single').on('click', function() {
                    jQuery('#wc_sendsms_send_single').html("<?= __('It\'s being sent...', 'wc_sendsms') ?>");
                    jQuery('#wc_sendsms_send_single').attr('disabled', 'disabled');
                    var data = {
                        'action': 'wc_sendsms_single',
                        'phone': jQuery('#wc_sendsms_phone').val(),
                        'content': jQuery('#wc_sendsms_content').val(),
                        'order': jQuery('#wc_sendsms_order_id').val(),
                        'short': jQuery('#wc_sendsms_short').is(":checked"),
                        'gdpr': jQuery('#wc_sendsms_gdpr').is(":checked")
                    };

                    jQuery.post(ajaxurl, data, function(response) {
                        jQuery('#wc_sendsms_send_single').html('<?= __('Send the message', 'wc_sendsms') ?>');
                        jQuery('#wc_sendsms_send_single').removeAttr('disabled');
                        jQuery('#wc_sendsms_phone').val('');
                        jQuery('#wc_sendsms_content').val('');
                        jQuery('#wc_sendsms_short').prop('checked', false);
                        jQuery('#wc_sendsms_gdpr').prop('checked', false);
                        alert(response);
                    });
                });
            });
        </script>
    <?php
}
add_action('admin_footer', 'wc_sendsms_javascript_send_single');

function wc_sendsms_ajax_send_single()
{
    if (!empty($_POST['content']) && !empty($_POST['phone']) && !empty($_POST['order'])) {
        $options = get_option('wc_sendsms_plugin_options');
        $username = '';
        $password = '';
        $short = filter_var($_POST['short'], FILTER_VALIDATE_BOOLEAN);
        $gdpr = filter_var($_POST['gdpr'], FILTER_VALIDATE_BOOLEAN);
        if (!empty($options) && is_array($options) && isset($options['username'])) {
            $username = $options['username'];
        } else {
            echo __('You did not enter a username', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['password'])) {
            $password = $options['password'];
        } else {
            echo __('You did not enter a password', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['from'])) {
            $from = $options['from'];
        } else {
            $from = '';
        }
        $phone = wc_sendsms_validate_phone($_POST['phone']);
        if (!empty($phone)) {
            wc_sendsms_send($username, $password, $phone, sanitize_textarea_field($_POST['content']), $from, 'single order', $short, $gdpr);
            $order = new WC_Order(wc_sendsms_sanitize_float($_POST['order']));
            $order->add_order_note(__('SMS message sent to ', 'wc_sendsms') . $phone . ': ' . sanitize_textarea_field($_POST['content']));
        }
        echo __('The message was sent', 'wc_sendsms');
    } else {
        echo __('You must complete the message and a phone number', 'wc_sendsms');
    }
    wp_die();
}
add_action('wp_ajax_wc_sendsms_single', 'wc_sendsms_ajax_send_single');

function wc_sendsms_send($username, $password, $phone, $message, $from, $type = 'order', $short = false, $gdpr = false)
{
    global $wpdb;

    $args['headers'] = [
        'url' => get_site_url()
    ];

    $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=message_send' . ($gdpr ? "_gdpr" : "") . '&username=' . urlencode($username) . '&password=' . urlencode($password) . '&from=' . urlencode($from) . '&to=' . urlencode(trim($phone)) . '&text=' . urlencode($message) . '&short=' . ($short ? 'true' : 'false'), $args)), true);

    # history
    $table_name = $wpdb->prefix . 'wcsendsms_history';
    $wpdb->query(
        $wpdb->prepare(
            "
                INSERT INTO $table_name
                (`phone`, `status`, `message`, `details`, `content`, `type`, `sent_on`)
                VALUES ( %s, %s, %s, %s, %s, %s, %s)
            ",
            $phone,
            isset($results['status']) ? $results['status'] : '',
            isset($results['message']) ? $results['message'] : '',
            isset($results['details']) ? $results['details'] : '',
            $message,
            $type,
            date('Y-m-d H:i:s')
        )
    );
    if (!get_option('wc-sendsms-default-price-time', false) || get_option('wc-sendsms-default-price-time') < date('Y-m-d H:i:s')) {
        $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=route_check_price&username=' . urlencode($username) . '&password=' . urlencode($password) . '&to=' . urlencode($phone), $args)), true);
        if ($results['details']['status'] === 64) {
            update_option('wc-sendsms-default-price', $results['details']['cost']);
            update_option('wc-sendsms-default-price-time', date('Y-m-d H:i:s', strtotime('+1 day')));
        }
    }
}


function wc_sendsms_validate_phone($phone_number)
{
    if(empty($phone_number)) return '';
    include 'cc.php';
    $phone_number = wc_sendsms_clear_phone_number($phone_number);
    //Strip out leading zeros:
    //this will check the country code and apply it if needed
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['cc'])) {
        $cc = $options['cc'];
    } else {
        $cc = 'INT';
    }
    if ($cc === "INT") {
        return $phone_number;
    }
    $phone_number = ltrim($phone_number, '0');
    $country_code = $country_codes[$cc];

    if (!preg_match('/^' . $country_code . '/', $phone_number)) {
        $phone_number = $country_code . $phone_number;
    }

    return $phone_number;
}

function wc_sendsms_clear_phone_number($phone_number)
{
    $phone_number = str_replace(['+', '-'], '', filter_var($phone_number, FILTER_SANITIZE_NUMBER_INT));
    //Strip spaces and non-numeric characters:
    $phone_number = preg_replace("/[^0-9]/", "", $phone_number);
    return $phone_number;
}

function wc_sendsms_clean_diacritice($string)
{
    $balarii = array(
        "\xC4\x82",
        "\xC4\x83",
        "\xC3\x82",
        "\xC3\xA2",
        "\xC3\x8E",
        "\xC3\xAE",
        "\xC8\x98",
        "\xC8\x99",
        "\xC8\x9A",
        "\xC8\x9B",
        "\xC5\x9E",
        "\xC5\x9F",
        "\xC5\xA2",
        "\xC5\xA3",
        "\xC3\xA3",
        "\xC2\xAD",
        "\xe2\x80\x93"
    );
    $cleanLetters = array("A", "a", "A", "a", "I", "i", "S", "s", "T", "t", "S", "s", "T", "t", "a", " ", "-");
    return str_replace($balarii, $cleanLetters, $string);
}

function wc_sendsms_get_orders_filtered($perioada_start, $perioada_final, $suma, $judete, $produse)
{
    global $wpdb;

    # get all orders
    $query = "select
        p.ID as order_id,
        p.post_date,
        max( CASE WHEN pm.meta_key = '_billing_email' and p.ID = pm.post_id THEN pm.meta_value END ) as billing_email,
        max( CASE WHEN pm.meta_key = '_billing_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_first_name,
        max( CASE WHEN pm.meta_key = '_billing_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_last_name,
        max( CASE WHEN pm.meta_key = '_billing_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_address_1,
        max( CASE WHEN pm.meta_key = '_billing_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_address_2,
        max( CASE WHEN pm.meta_key = '_billing_city' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_city,
        max( CASE WHEN pm.meta_key = '_billing_state' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_state,
        max( CASE WHEN pm.meta_key = '_billing_phone' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_phone,
        max( CASE WHEN pm.meta_key = '_billing_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_postcode,
        max( CASE WHEN pm.meta_key = '_shipping_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_first_name,
        max( CASE WHEN pm.meta_key = '_shipping_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_last_name,
        max( CASE WHEN pm.meta_key = '_shipping_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_address_1,
        max( CASE WHEN pm.meta_key = '_shipping_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_address_2,
        max( CASE WHEN pm.meta_key = '_shipping_city' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_city,
        max( CASE WHEN pm.meta_key = '_shipping_state' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_state,
        max( CASE WHEN pm.meta_key = '_shipping_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_postcode,
        max( CASE WHEN pm.meta_key = '_order_total' and p.ID = pm.post_id THEN pm.meta_value END ) as order_total,
        max( CASE WHEN pm.meta_key = '_order_tax' and p.ID = pm.post_id THEN pm.meta_value END ) as order_tax,
        max( CASE WHEN pm.meta_key = '_paid_date' and p.ID = pm.post_id THEN pm.meta_value END ) as paid_date,
        (
            SELECT
                GROUP_CONCAT(oim.meta_value SEPARATOR '|')
            FROM
                wp_woocommerce_order_itemmeta oim, wp_woocommerce_order_items oi
            WHERE 
                oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'  AND oi.order_id = p.ID
        ) AS items_id,
        ( select group_concat( order_item_id separator '|' ) from " . $wpdb->prefix . "woocommerce_order_items where order_id = p.ID ) as order_items
    from
        " . $wpdb->prefix . "posts p
        join " . $wpdb->prefix . "postmeta pm on p.ID = pm.post_id
        join " . $wpdb->prefix . "woocommerce_order_items oi on p.ID = oi.order_id
        WHERE post_type = 'shop_order' AND post_status = 'wc-completed'";
    $filters = array();
    $having = [];
    $where = '';
    if (!empty($perioada_start)) {
        $where .= ' AND post_date >= %s';
        $filters[] = wc_sendsms_sanitize_event_time($perioada_start);
    }
    if (!empty($perioada_final)) {
        $where .= ' AND post_date <= %s';
        $filters[] = wc_sendsms_sanitize_event_time($perioada_final);
    }
    if (!empty($suma)) {
        $having[] = 'order_total >= %d';
        $filters[] = wc_sendsms_sanitize_float($suma);
    }
    if (!empty($judete)) {
        $having[] = '_billing_state IN (';
        $elem = count($having) - 1;
        for ($i = 0; $i < count($judete); $i++) {
            $having[$elem] .= '\'%s\'';
            if ($i < count($judete) - 1) {
                $having[$elem] .= ', ';
            }
            $filters[] = str_replace("id_", "", sanitize_text_field($judete[$i]));
        }
        $having[$elem] .= ')';
    }

    $query .= $where . ' group by p.ID';
    if (!empty($having)) {
        $query .= ' HAVING ' . implode(' AND ', $having);
    }

    if (!empty($filters)) {
        $orders = $wpdb->get_results($wpdb->prepare($query, $filters));
    } else {
        $orders = $wpdb->get_results($query);
    }

    if (!empty($produse)) {
        foreach ($orders as $key => $order) {
            $items_id =  explode('|', $order->items_id);
            $ok = false;
            foreach ($items_id as $id) {
                for ($i = 0; $i < count($produse); $i++) {
                    $ok = str_replace("id_", "", sanitize_text_field($produse[$i])) == $id ? true : $ok;
                }
            }
            if (!$ok) {
                unset($orders[$key]);
            }
        }
    }
    return $orders;
}

function wc_sendsms_get_all_orders()
{
    global $wpdb;
    # get all orders
    $query = "select
        p.ID as order_id,
        p.post_date,
        max( CASE WHEN pm.meta_key = '_billing_email' and p.ID = pm.post_id THEN pm.meta_value END ) as billing_email,
        max( CASE WHEN pm.meta_key = '_billing_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_first_name,
        max( CASE WHEN pm.meta_key = '_billing_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_last_name,
        max( CASE WHEN pm.meta_key = '_billing_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_address_1,
        max( CASE WHEN pm.meta_key = '_billing_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_address_2,
        max( CASE WHEN pm.meta_key = '_billing_city' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_city,
        max( CASE WHEN pm.meta_key = '_billing_state' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_state,
        max( CASE WHEN pm.meta_key = '_billing_phone' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_phone,
        max( CASE WHEN pm.meta_key = '_billing_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) as _billing_postcode,
        max( CASE WHEN pm.meta_key = '_shipping_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_first_name,
        max( CASE WHEN pm.meta_key = '_shipping_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_last_name,
        max( CASE WHEN pm.meta_key = '_shipping_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_address_1,
        max( CASE WHEN pm.meta_key = '_shipping_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_address_2,
        max( CASE WHEN pm.meta_key = '_shipping_city' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_city,
        max( CASE WHEN pm.meta_key = '_shipping_state' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_state,
        max( CASE WHEN pm.meta_key = '_shipping_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) as _shipping_postcode,
        max( CASE WHEN pm.meta_key = '_order_total' and p.ID = pm.post_id THEN pm.meta_value END ) as order_total,
        max( CASE WHEN pm.meta_key = '_order_tax' and p.ID = pm.post_id THEN pm.meta_value END ) as order_tax,
        max( CASE WHEN pm.meta_key = '_paid_date' and p.ID = pm.post_id THEN pm.meta_value END ) as paid_date,
        (
            SELECT
                GROUP_CONCAT(oim.meta_value SEPARATOR '|')
            FROM
                wp_woocommerce_order_itemmeta oim, wp_woocommerce_order_items oi
            WHERE 
                oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'  AND oi.order_id = p.ID
        ) AS items_id,
        ( select group_concat( order_item_id separator '|' ) from " . $wpdb->prefix . "woocommerce_order_items where order_id = p.ID ) as order_items
    from
        " . $wpdb->prefix . "posts p
        join " . $wpdb->prefix . "postmeta pm on p.ID = pm.post_id
        join " . $wpdb->prefix . "woocommerce_order_items oi on p.ID = oi.order_id
        WHERE post_type = 'shop_order' AND post_status = 'wc-completed'";
    $query .= ' group by p.ID';
    return $orders = $wpdb->get_results($query);
}

function wc_sendsms_sanitize_event_time($event_time)
{
    // General sanitization, to get rid of malicious scripts or characters
    $event_time = sanitize_text_field($event_time);
    $event_time = filter_var($event_time, FILTER_SANITIZE_STRING);

    // Validation to see if it is the right format
    if (wc_sendsms_my_validate_date($event_time)) {
        return $event_time;
    }
    // default value, to return if checks have failed
    return "";
}

function wc_sendsms_my_validate_date($date, $format = 'Y-m-d')
{
    // Create the format date
    $d = DateTime::createFromFormat($format, $date);

    // Return the comparison    
    return $d && $d->format($format) === $date;
}

function wc_sendsms_sanitize_float($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function wc_sendsms_get_account_info(&$username, &$password, &$from, $options)
{
    if (!empty($options) && is_array($options) && isset($options['username'])) {
        $username = $options['username'];
    } else {
        $username = '';
    }
    if (!empty($options) && is_array($options) && isset($options['password'])) {
        $password = $options['password'];
    } else {
        $password = '';
    }
    if (!empty($options) && is_array($options) && isset($options['from'])) {
        $from = $options['from'];
    } else {
        $from = '';
    }
}

function wc_sendsms_replace_characters(&$message, $order, $order_id)
{
    $replace = array(
        '{billing_first_name}' => wc_sendsms_clean_diacritice($order->get_billing_first_name()),
        '{billing_last_name}' => wc_sendsms_clean_diacritice($order->get_billing_last_name()),
        '{shipping_first_name}' => wc_sendsms_clean_diacritice($order->get_shipping_first_name()),
        '{shipping_last_name}' => wc_sendsms_clean_diacritice($order->get_shipping_last_name()),
        '{order_number}' => $order_id,
        '{order_date}' => date('d-m-Y', strtotime($order->get_date_created())),
        '{order_total}' => number_format($order->get_total(), wc_get_price_decimals(), ',', '')
    );
    foreach ($replace as $key => $value) {
        $message = str_replace($key, $value, $message);
    }
}

function wc_sendsms_sanitize_bool($data)
{
    return $data ? 1 : 0;
}


function wc_sendsms_randomNumberSequence($requiredLength = 7, $highestDigit = 8)
{
    $sequence = '';
    for ($i = 0; $i < $requiredLength; ++$i) {
        $sequence .= mt_rand(0, $highestDigit);
    }
    return $sequence;
}
