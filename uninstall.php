<?php
/**
 * Uninstall handler for Site Scripts & Speed Manager.
 *
 * Fires when the plugin is deleted via the WordPress admin.
 * Removes all plugin data from the database.
 *
 * @package SiteScriptsSpeedManager
 * @version 2.1.1
 * @author  Think Above AI
 */

// Abort if not called by WordPress uninstall process.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin option from wp_options.
delete_option('sssm_settings');

// If multisite, clean up all sites.
if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids']);
    foreach ($sites as $site_id) {
        switch_to_blog($site_id);
        delete_option('sssm_settings');
        restore_current_blog();
    }
}
