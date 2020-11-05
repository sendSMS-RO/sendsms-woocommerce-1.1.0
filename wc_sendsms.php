<?php
/*
Plugin Name: SendSMS
Plugin URI: https://www.sendsms.ro/ro/ecommerce/plugin-woocommerce/
Description: Folositi solutia noastra de expedieri SMS pentru a livra informatia corecta la momentul potrivit. Oferiti clientilor dvs. o experienta superioara!
Version: 1.2.1
Author: sendSMS
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wc_sendsms
*/

$pluginDir = plugin_dir_path(__FILE__);
$pluginDirUrl = plugin_dir_url(__FILE__);
global $wc_sendsms_db_version;
$wc_sendsms_db_version = '1.2.1';

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
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
    wp_enqueue_style('datepickerdefault', trailingslashit(plugin_dir_url(__FILE__)).'datepicker/themes/default.css');
    wp_enqueue_style('datepickerdefaultdate', trailingslashit(plugin_dir_url(__FILE__)).'datepicker/themes/default.date.css');
    wp_enqueue_script('datepickerdefault', trailingslashit(plugin_dir_url(__FILE__)).'datepicker/picker.js', array('jquery'));
    wp_enqueue_script('datepickerdefaultdate', trailingslashit(plugin_dir_url(__FILE__)).'datepicker/picker.date.js', array('jquery'));
    wp_enqueue_script('wcsendsms', trailingslashit(plugin_dir_url(__FILE__)).'wc_sendsms.js', array('jquery'));

    # script & style for jquery
    wp_enqueue_style('select2', trailingslashit(plugin_dir_url(__FILE__)).'jquery/select2/select2.min.css');
	wp_enqueue_script('select2', trailingslashit(plugin_dir_url(__FILE__)).'jquery/select2/select2.min.js', array('jquery') );
 
	// please create also an empty JS file in your theme directory and include it too
	wp_enqueue_script('js_for_select2', trailingslashit(plugin_dir_url(__FILE__)).'forselect2.js', array( 'jquery', 'select2' ) ); 
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
            'label' => __('&nbsp;Nu doresc sa primesc SMS cu starea comenzii', 'wc_sendsms'),
        ), $checkout->get_value('wc_sendsms_optout'));
        echo '</div><div style="clear: both">&nbsp;</div>';
    }
}
add_action('woocommerce_after_order_notes', 'wc_sendsms_optout');

function wc_sendsms_optout_update_order_meta($orderId)
{
    if (isset($_POST['wc_sendsms_optout'])) {
        wc_sendsms_console_log($_POST['wc_sendsms_optout'], true);
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
        plugin_dir_url(__FILE__).'images/sendsms.png'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Configurare', 'wc_sendsms'),
        __('Configurare', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_login',
        'wc_sendsms_login'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Istoric', 'wc_sendsms'),
        __('Istoric', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_history',
        'wc_sendsms_history'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Campanie', 'wc_sendsms'),
        __('Campanie', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_campaign',
        'wc_sendsms_campaign'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Trimitere test', 'wc_sendsms'),
        __('Trimitere test', 'wc_sendsms'),
        'manage_options',
        'wc_sendsms_test',
        'wc_sendsms_test'
    );
}

function wc_sendsms_main()
{
    ?>
    <div class="wrap">
        <h2><?=__('SendSMS pentru WooCommerce', 'wc_sendsms')?></h2>
        <br />
        <p><?=__('Pentru a folosi modulul, vă rugăm să introduceți datele de autentificare în pagina de configurare.', 'wc_sendsms')?></p><br />
        <p><?=__('Nu aveți cont sendSMS?', 'wc_sendsms')?><br />
            <?=__('Înregistrați-vă GRATUIT', 'wc_sendsms')?> <a href="http://www.sendsms.ro/ro" target="_blank"><?=__('aici', 'wc_sendsms')?></a>.<br />
            <?=__('Mai multe detalii despre sendSMS puteți afla', 'wc_sendsms')?> <a href="http://www.sendsms.ro/ro"><?=__('aici', 'wc_sendsms')?></a>.</p>
        <p><?=__('În pagina de setări, sub datele de autentificare, veți găsi câte un câmp text pentru fiecare status disponibil în WooCommerce. Va trebui să introduceți un mesaj pentru câmpurile la care doriți să se trimită sms de înștiințare. Dacă un câmp va fi gol, atunci sms-ul nu se va trimite.', 'wc_sendsms')?></p>
        <p><?=__('Exemplu: Dacă doriți să trimiteți un mesaj când se schimbă statusul comenzii în finalizată (Completed) atunci va trebui să completați un mesaj în câmpul text', 'wc_sendsms')?> <strong><?=__('"Mesaj: Completed"', 'wc_sendsms')?></strong>.</p><br />
        <p><?=__('Puteți introduce variabile care se vor completa în funcție de datele de comandă.', 'wc_sendsms')?></p>
        <p><?=__('Exemplu mesaj:', 'wc_sendsms')?> <strong><?=__('Salut {billing_first_name}. Comanda ta cu numarul {order_number} a fost finalizata.', 'wc_sendsms')?></strong></p>
        <p><?=__('Mesajul introdus nu trebuie să conțină diacritice. Dacă acestea sunt introduse literele cu diacritice vor fi înlocuite cu echivalentul lor fără diacritice.', 'wc_sendsms')?></p>
        <br /><br /><p style="text-align: center"><a href="http://sendsms.ro" target="_blank"><img src="<?=plugin_dir_url(__FILE__).'images/sendsms_logo.png'?>" /></a></p>
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
        __('Nume utilizator', 'wc_sendsms'),
        'wc_sendsms_settings_display_username',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_password',
        __('Parola', 'wc_sendsms'),
        'wc_sendsms_settings_display_password',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_from',
        __('Label expeditor', 'wc_sendsms'),
        'wc_sendsms_settings_display_from',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation',
        __('Simulare trimitere SMS', 'wc_sendsms'),
        'wc_sendsms_settings_display_simulation',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation_number',
        __('Număr telefon simulare', 'wc_sendsms'),
        'wc_sendsms_settings_display_simulation_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner',
        __('Trimitere SMS la fiecare comanda noua', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_number',
        __('Numarul de telefon unde vor fi trimise mesajele', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_content',
        __('Mesajul ce va fi trimis', 'wc_sendsms'),
        'wc_sendsms_settings_display_send_to_owner_content',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_optout',
        __('Opt-out în coș', 'wc_sendsms'),
        'wc_sendsms_settings_display_optout',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_content',
        __('Statusuri', 'wc_sendsms'),
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
        <h2><?=__('SendSMS - Date autentificare', 'wc_sendsms')?></h2>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('wc_sendsms_plugin_options'); ?>
            <?php do_settings_sections('wc_sendsms_plugin'); ?>

            <input name="Submit" type="submit" class="button button-primary button-large" value="<?=__('Salvează', 'wc_sendsms')?>" />
        </form>
    </div>
    <?php
}

function wc_sendsms_get_woocommerce_product_list()
{
    $full_product_list = array();
    $loop = new WP_Query( array( 'post_type' => array('product', 'product_variation'), 'posts_per_page' => -1 ) );

    while ( $loop->have_posts() ) : $loop->the_post();
        $theid = get_the_ID();
        if( get_post_type() == 'product_variation' ){
            $product = new WC_Product_Variation($theid);
        } else {
            $product = new WC_Product($theid);
        }
        // its a variable product
        if( get_post_type() == 'product_variation' ){
            $parent_id = wp_get_post_parent_id($theid );
            $sku = get_post_meta($theid, '_sku', true );
            $thetitle = get_the_title( $parent_id);

            // ****** Some error checking for product database *******
            // check if variation sku is set
            if ($sku == '') {
                if ($parent_id == 0) {
                    // Remove unexpected orphaned variations.. set to auto-draft
                    $false_post = array();
                    $false_post['ID'] = $theid;
                    $false_post['post_status'] = 'auto-draft';
                    wp_update_post( $false_post );
                    if (function_exists(add_to_debug)) add_to_debug('false post_type set to auto-draft. id='.$theid);
                } else {
                    // there's no sku for this variation > copy parent sku to variation sku
                    // & remove the parent sku so the parent check below triggers
                    $sku = get_post_meta($parent_id, '_sku', true );
                    if (function_exists(add_to_debug)) add_to_debug('empty sku id='.$theid.'parent='.$parent_id.'setting sku to '.$sku);
                    update_post_meta($theid, '_sku', $sku );
                    update_post_meta($parent_id, '_sku', '' );
                }
            }
            // ****************** end error checking *****************

            // its a simple product
        } else {
            $sku = get_post_meta($theid, '_sku', true );
            $thetitle = get_the_title();
        }
        // add product to array but don't add the parent of product variations
        if (!empty($sku)) $full_product_list[] = array($thetitle, $sku, $theid);
    endwhile; wp_reset_query();
    // sort into alphabetical order, by title
    sort($full_product_list);
    return $full_product_list;
}

function wc_sendsms_test()
{
    if (isset($_POST) && !empty($_POST)) {
        if (empty($_POST['wc_sendsms_phone'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>'.__('Nu ați introdus numărul de telefon!', 'wc_sendsms').'</p>
            </div>';
        }
        if (empty($_POST['wc_sendsms_message'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>'.__('Nu ați introdus un mesaj!', 'wc_sendsms').'</p>
            </div>';
        }
        if (!empty($_POST['wc_sendsms_message']) && !empty($_POST['wc_sendsms_phone'])) {
            $options = get_option('wc_sendsms_plugin_options');
            $username = '';
            $password = '';
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
                    wc_sendsms_send($username, $password, $phone, sanitize_textarea_field($_POST['wc_sendsms_message']), $from, 'test');
                    echo '<div class="notice notice-success is-dismissible">
                    <p>' . __('Mesajul a fost trimis', 'wc_sendsms') . '</p>
                </div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible">
                    <p>'.__('Numărul de telefon validat este gol!', 'wc_sendsms').'</p>
                </div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible">
                    <p>'.__('Nu ați configurat modulul!', 'wc_sendsms').'</p>
                </div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h2><?=__('SendSMS - Trimitere test', 'wc_sendsms')?></h2>
        <form method="post" action="<?=admin_url('admin.php?page=wc_sendsms_test')?>">
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><?=__('Număr de telefon', 'wc_sendsms')?></th>
                    <td><input type="text" name="wc_sendsms_phone" style="width: 400px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><?=__('Mesaj', 'wc_sendsms')?></th>
                    <td>
                        <textarea name="wc_sendsms_message" class="wc_sendsms_content" style="width: 400px; height: 100px;" maxlength="160"></textarea>
                        <p>160 <?=__('caractere rămase', 'wc_sendsms')?></p>
                    </td>
                </tr>
                </tbody>
            </table>
            <p style="clear: both;"><button type="submit" class="button button-primary button-large" id="wc_sendsms_send_test"><?=__('Trimite mesajul', 'wc_sendsms')?></button></p>
        </form>
        <script type="text/javascript">
            var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content');
            for (var i = 0; i < wc_sendsms_content.length; i++) {
                var wc_sendsms_element = wc_sendsms_content[i];
                wc_sendsms_element.onkeyup = function() {
                    var text_length = this.value.length;
                    var text_remaining = 160 - text_length;
                    this.nextElementSibling.innerHTML = text_remaining + ' <?=__('caractere rămase', 'wc_sendsms')?>';
                };
            }
        </script>
    </div>
    <?php
}

function wc_sendsms_campaign()
{
    global $wpdb;

    # get all products
    $products = wc_sendsms_get_woocommerce_product_list();  

    $billing_states = $wpdb->get_results('SELECT DISTINCT meta_value FROM '.$wpdb->prefix.'postmeta WHERE meta_key = \'_billing_state\' ORDER BY meta_value ASC');

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
        ( select group_concat( order_item_id separator '|' ) from ".$wpdb->prefix."woocommerce_order_items where order_id = p.ID ) as order_items
    from
        ".$wpdb->prefix."posts p
        join ".$wpdb->prefix."postmeta pm on p.ID = pm.post_id
        join ".$wpdb->prefix."woocommerce_order_items oi on p.ID = oi.order_id
        WHERE post_type = 'shop_order' AND post_status = 'wc-completed'";
    $filters = array();
    $where = '';
    $having = [];
    if (!empty($_GET['perioada_start'])) {
        $where .= ' AND post_date >= %s';
        $filters[] = wc_sendsms_sanitize_event_time($_GET['perioada_start']);
    }
    if (!empty($_GET['perioada_final'])) {
        $where .= ' AND post_date <= %s';
        $filters[] = wc_sendsms_sanitize_event_time($_GET['perioada_final']);
    }
    if (!empty($_GET['suma'])) {
        $having[] = 'order_total >= %d';
        $filters[] = wc_sendsms_sanitize_float($_GET['suma']);
    }
    if (!empty($_GET['judete'])) {
        $having[] = '_billing_state IN (';
        $elem = count($having) - 1;
        for($i = 0; $i < count($_GET['judete']); $i++)
        {
            $having[$elem] .= '\'%s\'';
            if($i < count($_GET['judete']) - 1)
            {
                $having[$elem] .= ', ';
            }
            $filters[] = str_replace("id_", "", sanitize_text_field($_GET['judete'][$i]));
        }
        $having[$elem] .= ')';
    }

    $query .= $where.' group by p.ID';
    if (!empty($having)) {
        $query .=' HAVING '.implode(' AND ', $having);
    }

    $orders = $wpdb->get_results($wpdb->prepare($query, $filters));
    
    if (!empty($_GET['produse'])) 
    {
        foreach($orders as $key => $order)
        {
            $items_id =  explode('|', $order->items_id);
            $ok = false;
            foreach($items_id as $id)
            {
                for($i = 0; $i < count($_GET['produse']); $i++)
                {   
                    $ok = str_replace("id_", "", $_GET['produse'][$i]) == $id ? true : $ok;
                }
            }
            if(!$ok)
            {
                unset($orders[$key]);
            }
        }
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


    ?>
    <div class="wrap">
        <h2><?=__('SendSMS - Campanie', 'wc_sendsms')?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="wc_sendsms_campaign" />
            <div style="width: 100%; clear: both;">
                <div style="width: 48%; float: left;">
                    <p><?=__('Perioada', 'wc_sendsms')?> <input type="text" class="wcsendsmsdatepicker" name="perioada_start" value="<?=isset($_GET['perioada_start'])?wc_sendsms_sanitize_event_time($_GET['perioada_start']):''?>" /> - <input type="text" class="wcsendsmsdatepicker" name="perioada_final" value="<?=isset($_GET['perioada_final'])?wc_sendsms_sanitize_event_time($_GET['perioada_final']):''?>" /></p>
                </div>
                <div style="width: 48%; float: left">
                    <p><?=__('Suma minimă pe comandă:', 'wc_sendsms')?> <input type="number" name="suma" value="<?=isset($_GET['suma'])?wc_sendsms_sanitize_float($_GET['suma']):'0'?>" /></p>
                </div>
                <div style="width: 100%; clear: both;">
                    <div style="width: 48%; float: left;" class="mySelect">
                        <p><?=__('Produs cumpărat (lasă gol pentru a selecta toate produsele):', 'wc_sendsms')?>
                            <select id="produse_selectate" name="produse[]" multiple="multiple" style="width:80%;max-width:25em;">
                                <?php
                                    for($i = 0; $i < count($products); $i++)
                                    {
                                        $selected = false;
                                        if(isset($_GET['produse']))
                                        {
                                            $lenght = count($_GET['produse']);
                                            for($j = 0; $j < $lenght; $j++)
                                            {
                                                if(strcmp($_GET['produse'][$j], "id_" . $products[$i][2]) === 0)
                                                {
                                                    $selected = true;
                                                }
                                            }
                                        }
                                        ?>
                                            <option value="<?="id_" . esc_attr($products[$i][2])?>" <?=$selected?'selected="selected"':''?>><?=esc_attr($products[$i][0]) . " - " . esc_attr($products[$i][1])?></option>
                                        <?php
                                    }
                                ?>
                            </select>
                        </p>
                    </div>
                    <div style="width: 48%; float: left;">
                        <p><?=__('Județ facturare (lasă gol pentru a selecta toate judetele):', 'wc_sendsms')?>
                            <select id="judete_selectate" name="judete[]" multiple="multiple" style="width:80%;max-width:25em;">
                                <?php
                                    for($i = 0; $i < count($billing_states); $i++)
                                    {
                                        $selected = false;
                                        if(isset($_GET['judete']))
                                        {
                                            $lenght = count($_GET['judete']);
                                            for($j = 0; $j < $lenght; $j++)
                                            {
                                                if(strcmp($_GET['judete'][$j], "id_" . $billing_states[$i]->meta_value) === 0)
                                                {
                                                    $selected = true;
                                                }
                                            }
                                        }
                                        ?>
                                            <option value="<?="id_" . esc_attr($billing_states[$i]->meta_value)?>" <?=$selected?'selected="selected"':''?>><?=esc_attr($billing_states[$i]->meta_value)?></option>
                                        <?php
                                    }
                                ?>
                            </select>
                        </p>
                    </div>
                </div>
            </div>
            <div style="width: 100%; clear: both;">
                <button type="submit" class="button button-default button-large aligncenter"><?=__('Filtrează', 'wc_sendsms')?></button>
            </div>
        </form>
        <hr />
        <h3><?=__('Rezultate filtru:', 'wc_sendsms')?> <?=count($phones)?> <?=__('numere de telefon', 'wc_sendsms')?></h3>
        <div style="width: 100%; clear: both; padding-top: 20px;">
            <div style="width: 73%; float: left">
                <div><?=__('Mesaj:', 'wc_sendsms')?> <br />
                    <textarea name="content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 90%; height: 250px;" maxlength="160"></textarea>
                    <p>160 <?=__('caractere rămase', 'wc_sendsms')?></p>
                </div>
            </div>
            <div style="width: 25%; float: left">
                <p><?=__('Telefoane:', 'wc_sendsms')?> <br /></p>
                <select name="phones" id="wc_sendsms_phones" multiple style="width: 90%; height: 250px">
                    <?php
                    if (!empty($phones)) :
                        foreach ($phones as $phone) :
                            ?>
                            <option value="<?=$phone?>" selected><?=$phone?></option>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </select>
            </div>
        </div>
        <p style="clear: both;"><button type="submit" class="button button-primary button-large" id="wc_sendsms_send_campaign"><?=__('Trimite mesajul', 'wc_sendsms')?></button></p>
    </div>
    <script type="text/javascript">
        var wc_sendsms_content = document.getElementsByClassName("wc_sendsms_content");
        for (var i = 0; i < wc_sendsms_content.length; i++) {
            var wc_sendsms_element = wc_sendsms_content[i];
            wc_sendsms_element.onkeyup = function() {
                var text_length = this.value.length;
                var text_remaining = 160 - text_length;
                this.nextElementSibling.innerHTML = text_remaining + " <?=__('caractere rămase', 'wc_sendsms')?>";
            };
        }
    </script>
    <?php
}

function wc_sendsms_javascript_send() { ?>
    <script type="text/javascript" >
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_campaign').on('click', function() {
                jQuery('#wc_sendsms_send_campaign').html('<?=__('Se trimite...', 'wc_sendsms')?>');
                jQuery('#wc_sendsms_send_campaign').attr('disabled', 'disabled');
                var data = {
                    'action': 'wc_sendsms_campaign',
                    'phones': jQuery('#wc_sendsms_phones').val(),
                    'content': jQuery('#wc_sendsms_content').val()
                };

                jQuery.post(ajaxurl, data, function(response) {
                    jQuery('#wc_sendsms_send_campaign').html('<?=__('Trimite mesajul', 'wc_sendsms')?>');
                    jQuery('#wc_sendsms_send_campaign').removeAttr('disabled');
                    alert(response);
                });
            });
        });
    </script> <?php
}

add_action('admin_footer', 'wc_sendsms_javascript_send');

function wc_sendsms_ajax_send() {
    if (!empty($_POST['content']) && !empty($_POST['phones'])) {
        $options = get_option('wc_sendsms_plugin_options');
        $username = '';
        $password = '';
        if (!empty($options) && is_array($options) && isset($options['username'])) {
            $username = $options['username'];
        } else {
            echo __('Nu ați introdus numele de utilizator', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['password'])) {
            $password = $options['password'];
        } else {
            echo __('Nu ați introdus parola', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['from'])) {
            $from = $options['from'];
        } else {
            $from = '';
        }
        foreach ($_POST['phones'] as $phone) {
            $phone = wc_sendsms_validate_phone($phone);
            if (!empty($phone)) {
                wc_sendsms_send($username, $password, $phone, sanitize_textarea_field($_POST['content']), $from, 'campaign');
            }
        }
        echo __('Mesajele au fost trimise', 'wc_sendsms');
    } else {
        echo __('Trebuie să completați mesajul și să alegeți cel puțin un număr de telefon', 'wc_sendsms');
    }
    wp_die();
}
add_action('wp_ajax_wc_sendsms_campaign', 'wc_sendsms_ajax_send');

function wc_sendsms_history()
{
    ?>
    <div class="wrap">
        <h2><?=__('SendSMS - Istoric', 'wc_sendsms')?></h2>
        <form method="get">
            <?php
            $_table_list = new WC_SendSMS_History_List_Table();
            $_table_list->prepare_items();
            echo '<input type="hidden" name="page" value="wc_sendsms_history" />';

            $_table_list->views();
            $_table_list->search_box(__('Caută', 'wc_sendsms' ), 'key');
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
        $username = $options['username'];
    } else {
        $username = '';
    }
    echo '
    <input id="wc_sendsms_settings_username" name="wc_sendsms_plugin_options[username]" type="text" value="'.$username.'" style="width: 400px;" />';
}

function wc_sendsms_settings_display_password()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['password'])) {
        $password = $options['password'];
    } else {
        $password = '';
    }
    echo '
    <input id="wc_sendsms_settings_password" name="wc_sendsms_plugin_options[password]" type="password" value="'.$password.'" style="width: 400px;" />';
}

function wc_sendsms_settings_display_from()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['from'])) {
        $from = $options['from'];
    } else {
        $from = '';
    }
    echo '
    <input id="wc_sendsms_settings_from" name="wc_sendsms_plugin_options[from]" type="text" value="'.$from.'" style="width: 400px;" /> <span>'.__('maxim 11 caractere alfa numerice', 'wc_sendsms').'</span>';
}

function wc_sendsms_settings_display_simulation()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['simulation'])) {
        $simulation = $options['simulation'];
    } else {
        $simulation = '';
    }
    echo '
    <input id="wc_sendsms_settings_simulation" name="wc_sendsms_plugin_options[simulation]" type="checkbox" value="1" '.(!empty($simulation)?'checked="checked"':'').' />';
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
    <input id="wc_sendsms_settings_send_to_owner" name="wc_sendsms_plugin_options[send_to_owner]" type="checkbox" value="1" '.(!empty($send_to_owner)?'checked="checked"':'').' />';
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
    <input id="wc_sendsms_settings_simulation_number" name="wc_sendsms_plugin_options[simulation_number]" type="text" value="'.$number.'" style="width: 400px;" />';
}

function wc_sendsms_settings_display_send_to_owner_number()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_number'])) {
        $number = $options['send_to_owner_number'];
    } else {
        $number = '';
    }
    echo '
    <input id="wc_sendsms_settings_send_to_owner_number" name="wc_sendsms_plugin_options[send_to_owner_number]" type="text" value="'.$number.'" style="width: 400px;" />';
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
    <input id="wc_sendsms_settings_optout" name="wc_sendsms_plugin_options[optout]" type="checkbox" value="1" '.(!empty($optout)?'checked="checked"':'').' />';
}

function wc_sendsms_settings_display_send_to_owner_content()
{
    echo '<p>' . __('Variabile disponibile:', 'wc_sendsms') . ' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_content'])) {
        $content = $options['send_to_owner_content'];
    } else {
        $content = "";
    }

    echo '<div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_send_to_owner_content" name="wc_sendsms_plugin_options[send_to_owner_content]" style="width: 400px; height: 100px;" maxlength="160" class="wc_sendsms_content">' . (!empty($content) ? $content : '') . '</textarea>
                <p>' . (160 - strlen($content)) . ' ' . __('caractere rămase', 'wc_sendsms') . '</p>
            </div>
            <div style="width: 45%; float: left">
            </div>
        </div>';

    echo '
                <script type="text/javascript">
                    var wc_sendsms_content = document.getElementsByClassName("wc_sendsms_settings_send_to_owner_content");
                    for (var i = 0; i < wc_sendsms_content.length; i++) {
                        var wc_sendsms_element = wc_sendsms_content[i];
                        wc_sendsms_element.onkeyup = function() {
                            var text_length = this.value.length;
                            var text_remaining = 160 - text_length;
                            this.nextElementSibling.innerHTML = text_remaining + " ' . __('caractere rămase', 'wc_sendsms') . '";
                        };
                    }
                </script>
            ';
}

function wc_sendsms_settings_display_enabled()
{

}

function wc_sendsms_settings_display_content()
{
    $examples = array(
        'wc-pending' => __('Comanda cu numarul {order_number} a fost plasata cu succes si va fi expediata imediat ce primim plata dvs in valoare de {order_total} RON. NumeSite.ro', 'wc_sendsms'),
        'wc-processing' => __('Comanda cu numarul {order_number} este in curs de procesare si urmeaza a fi livrata. NumeSite.ro', 'wc_sendsms'),
        'wc-on-hold' => __('Comanda cu numarul {order_number} este in asteptare, unul sau mai multe produse lipsesc', 'wc_sendsms'),
        'wc-completed' => __('Comanda {order_number} a fost pregatita si va fi predata catre Curier. Ramburs: {order_total} RON. Va multumim, NumeSite.ro', 'wc_sendsms'),
        'wc-cancelled' => __('Comanda cu numarul {order_number} a fost anulata. Pentru detalii: {contact_site} . NumeSite.ro', 'wc_sendsms'),
        'wc-refunded' => __('Cererea de restituire pentru comanda cu numarul {order_number} a fost finalizata. NumeSite.ro', 'wc_sendsms'),
        'wc-failed' => __('Exista o problema cu procesarea platii pentru comanda cu numarul {order_number}. Va rugam sa ne contactati. NumeSite.ro', 'wc_sendsms')
    );
    echo '<p>'.__('Variabile disponibile:', 'wc_sendsms').' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = $options['content'];
        if(isset($options['enabled']))
        {
            $enabled = $options['enabled'];
        }else
        {
            $enabled = array();
        }
    } else {
        $content = array();
        $enabled = array();
    }
    $statuses = wc_get_order_statuses();
    foreach ($statuses as $key => $value) {
        $checked = false;
        if (isset($enabled[$key])) {
            $checked = true;
        }
        echo '<p style="clear: both; padding-top: 10px;">Mesaj: '.$value.'</p><p><label><input type="checkbox" name="wc_sendsms_plugin_options[enabled]['.$key.']" value="1" '.($checked?'checked="checked"':'').' /> Activ</label></p>
        <div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_content_'.$key.'" name="wc_sendsms_plugin_options[content]['.$key.']" style="width: 400px; height: 100px;" maxlength="160" class="wc_sendsms_content">'.(isset($content[$key])?$content[$key]:'').'</textarea>
                <p>'.(160-strlen($content[$key])).' '.__('caractere rămase', 'wc_sendsms').'</p>
            </div>
            <div style="width: 45%; float: left">
            ';
        if (isset($examples[$key])) {
            echo __('Exemplu: ', 'wc_sendsms').$examples[$key];
        }
        echo '
            </div>
        </div>';

        echo '
                <script type="text/javascript">
                    var wc_sendsms_content = document.getElementsByClassName("wc_sendsms_content");
                    for (var i = 0; i < wc_sendsms_content.length; i++) {
                        var wc_sendsms_element = wc_sendsms_content[i];
                        wc_sendsms_element.onkeyup = function() {
                            var text_length = this.value.length;
                            var text_remaining = 160 - text_length;
                            this.nextElementSibling.innerHTML = text_remaining + " '.__('caractere rămase', 'wc_sendsms').'";
                        };
                    }
                </script>
            ';
    }
}

function wc_sendsms_plugin_options_validate($input)
{
    return $input;
}

# magic
add_action("woocommerce_order_status_changed", "wc_sendsms_order_status_changed");

function wc_sendsms_order_status_changed($order_id, $checkout = null)
{
    global $woocommerce;
    $order = new WC_Order($order_id);
    $status = $order->status;
    $order_meta = get_post_meta($order_id);

    # check if user opted out for the order
    if (isset($order_meta['wc_sendsms_optout'])) {
        return;
    }

    $options = get_option('wc_sendsms_plugin_options');

    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = $options['content'];
        $enabled = $options['enabled'];
    } else {
        $content = array();
        $enabled = array();
    }

    wc_sendsms_get_account_info($username, $password, $from, $options);

    if (!empty($username) && !empty($password)) {
        if (isset($content['wc-' . $status]) && !empty($content['wc-' . $status]) && isset($enabled['wc-'.$status])) {
            # replace variables
            $message = $content['wc-' . $status];
            wc_sendsms_replace_characters($message, $order, $order_id);

            wc_sendsms_console_log($message, true);

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
                wc_sendsms_send($username, $password, $phone, $message, $from);
            }
        }
    }
}

# magic - 2
add_action( 'woocommerce_new_order', 'wc_sendsms_new_order'); 

function wc_sendsms_new_order($order_id) { 
    $options = get_option('wc_sendsms_plugin_options');

    if(isset($options) && $options['send_to_owner'] && $options['send_to_owner_number'] && $options['send_to_owner_content']){

        $order = new WC_Order($order_id);

        wc_sendsms_get_account_info($username, $password, $from, $options);

        if (!empty($username) && !empty($password)) {
            $phone = wc_sendsms_validate_phone($options['send_to_owner_number']);
            $message = $options['send_to_owner_content'];

            wc_sendsms_replace_characters($message, $order, $order_id);

            if (!empty($phone)) {
                # send sms
                wc_sendsms_send($username, $password, $phone, $message, $from);
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
        __('Trimite SMS', 'wc_sendsms'),
        'wc_sendsms_order_details_sms_box',
        'shop_order',
        'side',
        'high'
    );
}

function wc_sendsms_order_details_sms_box($post)
{
    ?>
    <input type="hidden" name="wc_sendsms_order_id" id="wc_sendsms_order_id" value="<?=$post->ID?>" />
    <p><?=__('Telefon:', 'wc_sendsms')?></p>
    <p><input type="text" name="wc_sendsms_phone" id="wc_sendsms_phone" style="width: 100%" /></p>
    <p><?=__('Mesaj:', 'wc_sendsms')?></p>
    <div>
        <textarea name="wc_sendsms_content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 100%; height: 100px;" maxlength="160"></textarea>
        <p>160 <?=__('caractere rămase', 'wc_sendsms')?></p>
    </div>
    <p><button type="submit" class="button" id="wc_sendsms_send_single"><?=__('Trimite mesajul', 'wc_sendms')?></button></p>
    <script type="text/javascript">
        var wc_sendsms_content = document.getElementsByClassName("wc_sendsms_content");
        for (var i = 0; i < wc_sendsms_content.length; i++) {
            var wc_sendsms_element = wc_sendsms_content[i];
            wc_sendsms_element.onkeyup = function() {
                var text_length = this.value.length;
                var text_remaining = 160 - text_length;
                this.nextElementSibling.innerHTML = text_remaining + " <?=__('caractere rămase', 'wc_sendsms')?>";
            };
        }
    </script>
    <?php
}

function wc_sendsms_javascript_send_single() { ?>
    <script type="text/javascript" >
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_single').on('click', function() {
                jQuery('#wc_sendsms_send_single').html('<?=__('Se trimite...', 'wc_sendsms')?>');
                jQuery('#wc_sendsms_send_single').attr('disabled', 'disabled');
                var data = {
                    'action': 'wc_sendsms_single',
                    'phone': jQuery('#wc_sendsms_phone').val(),
                    'content': jQuery('#wc_sendsms_content').val(),
                    'order': jQuery('#wc_sendsms_order_id').val()
                };

                jQuery.post(ajaxurl, data, function(response) {
                    jQuery('#wc_sendsms_send_single').html('<?=__('Trimite mesajul', 'wc_sendsms')?>');
                    jQuery('#wc_sendsms_send_single').removeAttr('disabled');
                    jQuery('#wc_sendsms_phone').val('');
                    jQuery('#wc_sendsms_content').val('');
                    alert(response);
                });
            });
        });
    </script> <?php
}
add_action('admin_footer', 'wc_sendsms_javascript_send_single');

function wc_sendsms_ajax_send_single() {
    if (!empty($_POST['content']) && !empty($_POST['phone']) && !empty($_POST['order'])) {
        $options = get_option('wc_sendsms_plugin_options');
        $username = '';
        $password = '';
        if (!empty($options) && is_array($options) && isset($options['username'])) {
            $username = $options['username'];
        } else {
            echo __('Nu ați introdus numele de utilizator', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['password'])) {
            $password = $options['password'];
        } else {
            echo __('Nu ați introdus parola', 'wc_sendsms');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['from'])) {
            $from = $options['from'];
        } else {
            $from = '';
        }
        $phone = wc_sendsms_validate_phone($_POST['phone']);
        if (!empty($phone)) {
            wc_sendsms_send($username, $password, $phone, sanitize_textarea_field($_POST['content']), $from, 'single order');
            global $woocommerce;
            $order = new WC_Order($_POST['order']);
            $order->add_order_note(__('Mesaj SMS trimis către '.$phone.': ' . sanitize_textarea_field($_POST['content']),'wc_sendsms'));
        }
        echo __('Mesajul a fost trimis', 'wc_sendsms');
    } else {
        echo __('Trebuie să completați mesajul și un număr de telefon', 'wc_sendsms');
    }
    wp_die();
}
add_action('wp_ajax_wc_sendsms_single', 'wc_sendsms_ajax_send_single');

function wc_sendsms_send($username, $password, $phone, $message, $from, $type = 'order')
{
    global $wpdb;

    $args['headers'] = [
        'url' => get_site_url()
    ];
    $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=message_send_gdpr&username='.urlencode($username).'&password='.urlencode($password).'&from='.urlencode($from).'&to='.urlencode($phone).'&text='.urlencode($message).'&short=true', $args)), true);

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
            isset($results['status'])?$results['status']:'',
            isset($results['message'])?$results['message']:'',
            isset($results['details'])?$results['details']:'',
            $message,
            $type,
            date('Y-m-d H:i:s')
        )
    );
}


function wc_sendsms_validate_phone($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 1) == '0' && strlen($phone) == 10) {
        $phone = '4'.$phone;
    } elseif (substr($phone, 0, 1) != '0' && strlen($phone) == 9) {
        $phone = '40'.$phone;
    } elseif (strlen($phone) == 13 && substr($phone, 0, 2) == '00') {
        $phone = substr($phone, 2);
    }
    return $phone;
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
        "\xe2\x80\x93");
    $cleanLetters = array("A", "a", "A", "a", "I", "i", "S", "s", "T", "t", "S", "s", "T", "t", "a", " ", "-");
    return str_replace($balarii, $cleanLetters, $string);
}

function wc_sendsms_sanitize_event_time($event_time) {
    // General sanitization, to get rid of malicious scripts or characters
    $event_time = sanitize_text_field($event_time);
    $event_time = filter_var($event_time, FILTER_SANITIZE_STRING);

    // Validation to see if it is the right format
    if (wc_sendsms_my_validate_date($event_time)){
        return $event_time;
    }
    // default value, to return if checks have failed
    return "";
}

function wc_sendsms_my_validate_date($date, $format = 'Y-m-d') {
    // Create the format date
    $d = DateTime::createFromFormat($format, $date);

    // Return the comparison    
    return $d && $d->format($format) === $date;
}

function wc_sendsms_sanitize_float( $input ) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function wc_sendsms_console_log($output, $woocommerce = false, $with_script_tags = true) {
    if(!$woocommerce)
    {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
        if ($with_script_tags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
    }else
    {
        $log = new WC_Logger();
        $log_entry = print_r( $output, true );
        $log->log( 'test-123', $log_entry );
    }
    echo $js_code;
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
        '{billing_first_name}' => wc_sendsms_clean_diacritice($order->billing_first_name),
        '{billing_last_name}' => wc_sendsms_clean_diacritice($order->billing_last_name),
        '{shipping_first_name}' => wc_sendsms_clean_diacritice($order->shipping_first_name),
        '{shipping_last_name}' => wc_sendsms_clean_diacritice($order->shipping_last_name),
        '{order_number}' => $order_id,
        '{order_date}' => date('d-m-Y', strtotime($order->order_date)),
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