<?php
/**
 * Removes SEO Copilot data on user-initiated delete.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$prefix = $wpdb->prefix . 'seocp_';
$tables = ['templates', 'runs', 'segments', 'queue', 'options_long'];
foreach ($tables as $t) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$t}");
}

delete_option('seocp_settings');
delete_option('seocp_db_version');
delete_option('seocp_enabled_post_types');
delete_option('seocp_field_defaults');
delete_option('seocp_templates_seeded');

wp_clear_scheduled_hook('seocp_run_bulk_batch');
