<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package GF_Tops
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin-level data only. Per-form TowX settings live in Gravity Forms form meta; removing them here
 * could destroy production configuration if the plugin is reinstalled later.
 */

if ( ! function_exists( 'gf_tops_uninstall_site' ) ) {
	/**
	 * Remove options, cached transients, and the request log table for the current site.
	 */
	function gf_tops_uninstall_site() {
		global $wpdb;

		delete_option( 'gravityformsaddon_gravityformstops_settings' );
		delete_option( 'gravityformsaddon_gravityformstops_version' );
		delete_option( 'gf_tops_request_log_db_version' );
		delete_option( 'gf_tops_lookup_status' );
		delete_option( 'gf_tops_auth_test_ok' );

		// Cached TowX vehicle lists and per-entry confirmation results (gf_tops_list_*, gf_tops_result_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall only; transient names are not enumerable through the API.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_gf_tops_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_gf_tops_' ) . '%'
			)
		);

		$table = $wpdb->prefix . 'gf_tops_request_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall only; identifier via %i (WP 6.2+).
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}

if ( is_multisite() ) {
	$gf_tops_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $gf_tops_site_ids as $gf_tops_site_id ) {
		switch_to_blog( (int) $gf_tops_site_id );
		gf_tops_uninstall_site();
		restore_current_blog();
	}
} else {
	gf_tops_uninstall_site();
}

// GitHub updater cache (network-wide), plus the pre-1.4.1 release transients.
delete_site_option( 'gf_tops_github_release' );
delete_site_transient( 'gf_tops_gh_rel_' . md5( 'duppinstech/gravity-forms-tops' ) );
delete_site_transient( 'gf_tops_gh_rel_' . md5( 'oduppinsjr/gravity-forms-tops' ) );
