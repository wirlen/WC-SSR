<?php
/**
 * Plugin Name: WooCommerce to GTM Server-Side Tracking
 * Description: Sends WooCommerce events and order data to Google Tag Manager (server-side), including page views.
 * Version: 1.1
 * Author: Johan Wirlén Enroth
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Your GTM server-side container URL
define('GTM_ENDPOINT_URL', 'https://gtm.your-server-url.com/collect');

// Send page views to GTM
add_action('wp', 'send_page_view_to_gtm');
function send_page_view_to_gtm() {
    if (is_admin() || wp_doing_ajax()) return; // Prevent tracking in admin area or AJAX requests

    $data = array(
        'event' => 'page_view',
        'page_url' => get_permalink(),
        'page_title' => wp_title('', false),
    );

    send_data_to_gtm($data);
}

// Send product page views to GTM
add_action('woocommerce_after_single_product', 'send_product_view_to_gtm');
function send_product_view_to_gtm() {
    if (!is_product()) return;

    global $product;
    $data = array(
        'event' => 'product_view',
        'product_id' => $product->get_id(),
        'product_name' => $product->get_name(),
        'price' => $product->get_price(),
    );

    send_data_to_gtm($data);
}

// Send add-to-cart events to GTM
add_action('woocommerce_add_to_cart', 'send_add_to_cart_to_gtm', 10, 6);
function send_add_to_cart_to_gtm($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $product = wc_get_product($product_id);
    $data = array(
        'event' => 'add_to_cart',
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'price' => $product->get_price(),
        'quantity' => $quantity,
    );

    send_data_to_gtm($data);
}


add_action('woocommerce_before_checkout_form', 'send_checkout_start_to_gtm');
function send_checkout_start_to_gtm() {
    $cart = WC()->cart;
    $cart_items = array();

    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $cart_items[] = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'price' => $product->get_price(),
            'quantity' => $cart_item['quantity'],
        );
    }

    $data = array(
        'event' => 'checkout_start',
        'items' => $cart_items,
    );

    send_data_to_gtm($data);
}

// Send checkout events to GTM
add_action('woocommerce_thankyou', 'send_order_to_gtm');
function send_order_to_gtm($order_id) {
    $order = wc_get_order($order_id);
    $order_data = array(
        'event' => 'purchase',
        'transaction_id' => $order->get_id(),
        'value' => $order->get_total(),
        'currency' => $order->get_currency(),
        'items' => array(),
    );

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $order_data['items'][] = array(
            'item_id' => $product->get_id(),
            'item_name' => $product->get_name(),
            'price' => $product->get_price(),
            'quantity' => $item->get_quantity(),
        );
    }

    send_data_to_gtm($order_data);
}

// Send cart view to GTM
add_action('woocommerce_cart_collaterals', 'send_cart_view_to_gtm');
function send_cart_view_to_gtm() {
    $cart = WC()->cart;
    $cart_items = array();

    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $cart_items[] = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'price' => $product->get_price(),
            'quantity' => $cart_item['quantity'],
        );
    }

    $data = array(
        'event' => 'cart_view',
        'items' => $cart_items,
    );

    send_data_to_gtm($data);
}

// Utility function to send data to GTM server-side container
function send_data_to_gtm($data) {
    $response = wp_remote_post(GTM_ENDPOINT_URL, array(
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('Error sending data to GTM: ' . $response->get_error_message());
    }
}
