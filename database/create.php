<?php

add_action('activated_plugin', 'create_easyparcel_zones_table');
add_action('activated_plugin', 'create_easyparcel_zones_location_table');
add_action('activated_plugin', 'create_easyparcel_zones_courier_table');
register_activation_hook(__FILE__, 'create_easyparcel_zones_table');
register_activation_hook(__FILE__, 'create_easyparcel_zones_location_table');
register_activation_hook(__FILE__, 'create_easyparcel_zones_courier_table');

global $wpdb;


function create_easyparcel_zones_table() {
    global $wpdb;
    global $jal_db_version;
    $jal_db_version = '1.0';
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'easyparcel_zones';
    // check if exist then drop previous
    $dropsql = "DROP TABLE IF EXISTS  $table_name;";

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            zone_id BIGINT UNSIGNED NOT NULL auto_increment,
            zone_name varchar(200) NOT NULL,
            zone_order BIGINT UNSIGNED NOT NULL COMMENT 'the order for sequence',
            PRIMARY KEY  (zone_id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // $wpdb->query($dropsql);
    dbDelta($sql);

    add_option('jal_db_version', $jal_db_version);
}
function create_easyparcel_zones_courier_table() {
    global $wpdb;
    global $jal_db_version;
    $jal_db_version = '1.0';
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'easyparcel_zones_courier';
    // check if exist then drop previous
    $dropsql = "DROP TABLE IF EXISTS  $table_name;";

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL auto_increment,
            zone_id BIGINT UNSIGNED NOT NULL COMMENT 'refer to easyparcel_zones zone_id',
            service_id varchar(9) NULL,
            service_name varchar(200) NULL,
            service_type varchar(50) NULL,
            courier_id varchar(9) NULL,
            courier_name tinytext NULL,
            courier_logo tinytext NULL,
            courier_info text NULL,
            courier_display_name varchar(100) NULL COMMENT 'the name that seller edit, visible to buyer',
            courier_dropoff_point varchar(100) NULL COMMENT 'courier dropoff point',
            sample_cost float(9,2) DEFAULT 0.00 COMMENT 'sample cost for shipment',
            charges tinyint(2) NOT NULL COMMENT '1: Flat, 2:Member, 3:Public, 4:Addon',
            charges_value varchar(50) DEFAULT 0 COMMENT 'based on charges if 1=5, 2=0, 3=0, 4=1:5(addon price by 5), 4=2:10(addon price by 10%)',
            free_shipping tinyint(2) DEFAULT 2 COMMENT '1: enable, 2:not enable',
            free_shipping_by tinyint(2) DEFAULT 0 COMMENT '0: none, 1: amount, 2:quantity',
            free_shipping_value varchar(50) DEFAULT 0 COMMENT '0=NONE if 1=5(free if price above equal 5), 2=3(free if quantity 3)',
            courier_order BIGINT UNSIGNED NOT NULL COMMENT 'the order for sequence',
            status tinyint(2) DEFAULT 1 COMMENT '1: active, 2:not active',
            PRIMARY KEY  (id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // $wpdb->query($dropsql);
    dbDelta($sql);

    add_option('jal_db_version', $jal_db_version);
}

function create_easyparcel_zones_location_table() {
    global $wpdb;
    global $jal_db_version;
    $jal_db_version = '1.0';
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'easyparcel_zone_locations';
    // check if exist then drop previous
    $dropsql = "DROP TABLE IF EXISTS  $table_name;";

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        location_id BIGINT UNSIGNED NOT NULL auto_increment,
        zone_id BIGINT UNSIGNED NOT NULL,
        location_code varchar(200) NOT NULL,
        location_type varchar(40) NOT NULL,
        PRIMARY KEY  (location_id),
        KEY location_id (location_id),
        KEY location_type_code (location_type(10),location_code(20))
      ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // $wpdb->query($dropsql);
    dbDelta($sql);

    add_option('jal_db_version', $jal_db_version);
}

?>