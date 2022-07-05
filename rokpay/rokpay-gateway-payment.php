<?php
/**
 * Plugin Name: Rokpay
 * Description: Make payment using Rokpay.
 * Author: Pankaj kumar
 * Author URI: https://www.linkedin.com/in/professional-webdeveloper/
 * Version: 1.0.0
 * License: GNU General Public License v3.0
 */

defined('ABSPATH') or exit;
include 'includes/RokpayConstants.php';
register_activation_hook(__FILE__, 'rokpay_payment');
function rokpay_payment()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'rokpay_payments';
    $sql = "CREATE TABLE `$table_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('sucess','cancel','fail','start') NOT NULL DEFAULT 'start',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_customer` int(10) unsigned NOT NULL,
  `id_transaction` varchar(15) NOT NULL,
  `id_order` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY(id)
  ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
  ";
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
    {
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_deactivation_hook(__FILE__, 'rokpay_remove_database');
function rokpay_remove_database()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'rokpay_payments';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
    delete_option("my_plugin_db_version");
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{
    return;
}

add_action('plugins_loaded', 'rokpay_gateway_init', 11);

function rokpay_gateway_init()
{
    require_once (plugin_basename('classes/rokapy_gateway_transfer.php'));
}

require_once (plugin_basename('includes/hooks.php'));

do_action('woocommerce_checkout_order_review');
add_action('woocommerce_api_callback', 'callback_handler');

defined('ABSPATH') or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{
    return;
}
if (isset($_GET['rokpay_action']))
{

    add_action('the_content', 'changePaymentStatus');

}
function apikeysempty($rseult)
{
    $rseult = RokpayConstants::MSG_SHOPNUMBER_EMPTY;
    return $rseult;
}

// echo $rseult;
// die();


function apiDetailsError($content)
{
    $content = RokpayConstants::API_DETAIL_ERROR;
    return $content;
}
function changePaymentStatus($content)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'rokpay_payments';

    $wporderid = $_GET['wp_orderid'];
    $order = new WC_Order($wporderid);

    if ($_GET['rokpay_action'] == "success")
    {

        $result = $wpdb->update($table_name, array(
            'status' => "sucess",
            'date_updated' => date('Y-m-d H:i:s')
        ) , array(
            'id' => $_GET["orderid"]
        ) , array(
            '%s',
            '%s'
        ) , array(
            '%d'
        ));
        $order->update_status('wc-processing');
        $order->add_order_note('Rokpay payment succesfully');

        $content = RokpayConstants::ORDER_SUCESS_PAYMENT;
    }
    elseif ($_GET['rokpay_action'] == "cancel")
    {
        $result = $wpdb->update($table_name, array(
            'status' => "cancel",
            'date_updated' => date('Y-m-d H:i:s')
        ) , array(
            'id' => $_GET["orderid"]
        ) , array(
            '%s',
            '%s'
        ) , array(
            '%d'
        ));

        $order->update_status('wc-cancel');
        $order->add_order_note('Rokpay payment Canceled');
        $content = RokpayConstants::ORDER_CANCEL_PAYMENT;

    }
    elseif ($_GET['rokpay_action'] == "failure")
    {
        $result = $wpdb->update($table_name, array(
            'status' => "fail",
            'date_updated' => date('Y-m-d H:i:s')
        ) , array(
            'id' => $_GET["orderid"]
        ) , array(
            '%s',
            '%s'
        ) , array(
            '%d'
        ));

        $order->update_status('wc-failed');
        $order->add_order_note('Rokpay payment Failed');

        $content = RokpayConstants::ORDER_PAYMENT_FAIL;
    }

    return $content;

}

add_action('template_redirect', 'verifyShop');
function makeCurlPost($data, $url)
{
    
    $ch = curl_init($url);
    $payload = json_encode($data);
    // Attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    // Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json'
    ));
    // Return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Execute the POST request
    $result = curl_exec($ch);
    $response = json_decode($result);
    /* echo "JSON response  from rokkpay<pre>";
        print_r($response);
        echo "</pre>";
        exit;*/
    // Close cURL resource
    curl_close($ch);
    //Tools::redirect($response->orderRequest->paymentUrl);
    return $response;

}
function Rp_digest_hash($apiKey, $shopNumber = "")
{
    $digest = $shopNumber . $apiKey;
    $digestHash = hash('sha512', $digest);
    return $digestHash;
}
function verifyShop()
{
    if (isset($_GET["rokpay_action"]))
    {

    }
    else
    {
        global $wp;
        $api_data = get_option('woocommerce_bacs_accountss');
        $redirect_url = plugin_dir_url(__FILE__) . 'process.php';
        if (is_checkout() && !empty($wp->query_vars['order-received']))
        {

            $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $settings = get_option( "woocommerce_offline_gateway_settings");
            $apiUrl = $settings['environment'];;

            // echo $apiUrl;
            // // die();
            

            $order_id = $wp->query_vars['order-received'];
            // Get an instance of the WC_Order object
            $order = wc_get_order($order_id);

            $order_id = $order->get_id(); // Get the order ID
            $parent_id = $order->get_parent_id(); // Get the parent order ID (for subscriptions…)
            $user_id = $order->get_user_id(); // Get the costumer ID
            $user = $order->get_user(); // Get the WP_User object
            $order_status = $order->get_status(); // Get the order status
            $currency = $order->get_currency(); // Get the currency used
            $payment_method = $order->get_payment_method(); // Get the payment method ID
            $payment_title = $order->get_payment_method_title(); // Get the payment method title
            $date_created = $order->get_date_created(); // Get date created (WC_DateTime object)
      
            
            $date_modified = $order->get_date_modified();
            $thetitle = get_the_title();

            $order = wc_get_order($order_id);
            $items_data = $order->get_items();
            $pro = array();
            $c = 0;
            foreach ($items_data as $item => $values)
            {

                $price = $values['total'] / $values['quantity'];
                $pro[$c]['name'] = $values['name'];
                $pro[$c]['price'] = $price;
                $pro[$c]['quantity'] = $values['quantity'];

                $c++;
            }
            // echo '<pre>'; print_r( $pro );
            // die;
            $product = array(
                'name' => "",
                'price' => "",
                'quantity' => ""
            );
            $billing_country = $order->get_billing_country(); // Customer billing country
            $total = $order->get_total();

            //$cart_item=WC()->cart->total;
            foreach (WC()
                ->cart
                ->get_cart() as $cart_item)
            {
                $quantity = $cart_item['quantity'];
                // echo $quantity;
                
            }

            $transaction_id = $order->get_transaction_id();
            // $apikey =$order->get_apikey();
            $apikey = $api_data[0]['key'];
            $shopnumber = $api_data[0]['shop'];
            $shopTransactionId = rand(10, 100000);
            $digestHash = Rp_digest_hash($apikey, $shopnumber);
            $date_modified = $order->get_date_modified();
            $date_added = $order->get_date_created();
            $customer_id = $order->get_user_id();

            $last_insert = rokapay_addition($customer_id, $order_id);

            $cancellationUrl = $actual_link . '&rokpay_action=cancel&orderid=' . $last_insert . "&wp_orderid=" . $order_id;
            $failureUrl = $actual_link . '&rokpay_action=failure&orderid=' . $last_insert . "&wp_orderid=" . $order_id;
            $successUrl = $actual_link . '&rokpay_action=success&orderid=' . $last_insert . "&wp_orderid=" . $order_id;
            $discounts = array();
            $discount = array("name"=>"","type"=>"","value"=>"");

            // GET THE ORDER COUPON ITEMS
$order_items = $order->get_items('coupon');


// LOOP THROUGH ORDER COUPON ITEMS
        foreach( $order_items as $item_id => $item ){

        // Retrieving the coupon ID reference
        $coupon_post_obj = get_page_by_title( $item->get_name(), OBJECT, 'shop_coupon' );
        $coupon_id = $coupon_post_obj->ID;

        // Get an instance of WC_Coupon object (necessary to use WC_Coupon methods)
        $coupon = new WC_Coupon($coupon_id);

           $order_discount_amount = wc_get_order_item_meta( $item_id, 'discount_amount', true );
             $discount["name"] = $coupon->get_code();
                $discount["type"] = $coupon->get_discount_type()=="percent"?"percentual":"fixed";
                $discount["value"] = $order_discount_amount;
                array_push($discounts, $discount);

        }
        $shippingAmount = $order->get_shipping_total();

        $data = array(
                "amount" => $total,
                "cancellationUrl" => $cancellationUrl,
                "currency" => $currency,
                "failureUrl" => $failureUrl,
                "products" => $pro,
                "shopNumber" => $shopnumber,
                "shopOrderId" => $order_id,
                "shopTransactionId" => $shopTransactionId,
                "successUrl" => $successUrl,
                "digest" => $digestHash,
                "shippingAmount"=>$shippingAmount,
            "discounts"=>$discounts,
            "shopOrderNumber"=>$order_id
            );

  
            if (empty($apikey or $shopnumber))
            {
                add_action('the_content', 'apikeysempty');
            }
            else
            {
                

                $response = makeCurlPost($data, $apiUrl);
               
                if (array_key_exists("orderRequest", get_object_vars($response)))
                {
                    wp_redirect($response
                        ->orderRequest
                        ->paymentUrl);
                    exit;
                }
                elseif (array_key_exists("exception", get_object_vars($response)))
                {
                    add_action('the_content', 'apiDetailsError');

                }
                else
                {
                    add_action('the_content', 'apiDetailsError');

                }
            }

        }
    }
}

function rokapay_addition($customer_id, $order_id)
{
    global $wpdb;
    $txnid = time();

    $table_name = $wpdb->prefix . 'rokpay_payments';

    $data = array(

        'status' => "start",
        'date_added' => date('Y-m-d H:i:s') ,
        'date_updated' => date('Y-m-d H:i:s') ,
        'id_customer' => $customer_id,
        'id_transaction' => $txnid,
        'id_order' => $order_id

    );

    $wpdb->insert($table_name, $data);

    return $wpdb->insert_id;

}

?>
