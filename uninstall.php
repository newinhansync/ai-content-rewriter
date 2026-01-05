<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is deleted (not deactivated).
 * This removes all plugin data from the database.
 *
 * @package AIContentRewriter
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user capabilities
if (!current_user_can('activate_plugins')) {
    exit;
}

// Check if this is the correct plugin being uninstalled
if (!isset($_REQUEST['plugin']) || strpos($_REQUEST['plugin'], 'ai-content-rewriter') === false) {
    exit;
}

global $wpdb;

// Check if user wants to delete data (option can be set in settings)
$delete_data = get_option('aicr_delete_data_on_uninstall', false);

if ($delete_data) {
    // Drop all plugin tables
    $tables = [
        $wpdb->prefix . 'aicr_feed_items',
        $wpdb->prefix . 'aicr_feeds',
        $wpdb->prefix . 'aicr_api_usage',
        $wpdb->prefix . 'aicr_templates',
        $wpdb->prefix . 'aicr_schedules',
        $wpdb->prefix . 'aicr_history',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    // Delete all plugin options
    $options = [
        'aicr_version',
        'aicr_activated_at',
        'aicr_deactivated_at',
        'aicr_default_ai_provider',
        'aicr_default_language',
        'aicr_auto_publish',
        'aicr_default_post_status',
        'aicr_chunk_size',
        'aicr_api_keys',
        'aicr_prompt_templates',
        'aicr_chatgpt_api_key',
        'aicr_chatgpt_model',
        'aicr_gemini_api_key',
        'aicr_gemini_model',
        'aicr_rss_fetch_interval',
        'aicr_rss_max_items',
        'aicr_rss_auto_cleanup',
        'aicr_rss_retention_days',
        'aicr_rss_concurrent_fetch',
        'aicr_rss_default_auto_rewrite',
        'aicr_rss_default_auto_publish',
        'aicr_rss_rewrite_queue_limit',
        'aicr_delete_data_on_uninstall',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete all transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aicr_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aicr_%'");

    // Delete post meta created by the plugin
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aicr_%'");

    // Clear any scheduled cron events
    $cron_hooks = [
        'aicr_scheduled_rewrite',
        'aicr_batch_process',
        'aicr_cleanup_logs',
        'aicr_rss_fetch_feeds',
        'aicr_rss_cleanup',
        'aicr_rss_process_queue',
    ];

    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}
