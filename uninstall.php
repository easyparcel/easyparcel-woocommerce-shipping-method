<?php

// Multisite not supported

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}
global $wpdb;
$tableArray = [
    $wpdb->prefix . "easyparcel_courier",
    $wpdb->prefix . "easyparcel_courier_setting",
    $wpdb->prefix . "easyparcel_zones_courier",
    $wpdb->prefix . "easyparcel_zones",
    $wpdb->prefix . "easyparcel_zone_locations",
    // $wpdb->prefix . "table2",
];
foreach ($tableArray as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// uninstall delete option
delete_option('woocommerce_easyparcel_settings');