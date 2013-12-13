<?php
/**
 * Plugin Name: Simple Sessions for WordPress
 * Plugin URI: http://www.adcstudio.com/
 * Description: Partitionable session management for WordPress.
 * Uses database-backed options for storage. Does NOT use transients,
 * which are unreliable for session purposes in memory cached systems.
 * Version: 1.0
 * Author: Kevin Newman, Eric Mann
 * Author URI: http://unfoc.us, http://eamann.com
 * License: GPLv2+
 */

include 'class.SimpleSession.php';

/**
 * Clean up expired sessions by removing data and their expiration entries from
 * the WordPress options table.
 *
 * This method should never be called directly and should instead be triggered as part
 * of a scheduled task or cron job.
 */
function simple_session_cleanup()
{
	global $wpdb;

	if ( defined( 'WP_SETUP_CONFIG' ) ) {
		return;
	}

	if ( ! defined( 'WP_INSTALLING' ) ) {
		$expiration_keys = $wpdb->get_results(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'smplsess_expires|%'"
		);

		$now = time();
		$expired_sessions = array();

		foreach( $expiration_keys as $expiration ) {
			// If the session has expired
			if ( $now > intval( $expiration->option_value ) ) {
				// Get the session ID by parsing the option_name
				$exp_key_parts = explode('|', $expiration->option_name );

				// Mark the two options for elimination (data store and expiration keys).
				$expired_sessions[] = $expiration->option_name;
				$expired_sessions[] = 'smplsess|' . $exp_key_parts[1] . '|' . $exp_key_parts[2];
			}
		}

		// Delete all expired sessions in a single query
		if ( ! empty( $expired_sessions ) ) {
			$option_names = implode( "','", $expired_sessions );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ('$option_names')" );
		}
		// :NOTE: Due to the way MySQL works, these actually still exist in the database.
		// We'd need to OPTIMIZE TABLE to get rid of them for real, which is probably too
		// expinsive for larger WordPress databases.
	}

	// Allow other plugins to hook in to the garbage collection process.
	do_action( 'simple_session_cleanup' );
}

/**
 * Register the garbage collector as a twice daily event.
 */
function simple_session_register_garbage_collection()
{
	if ( ! wp_next_scheduled( 'simple_session_garbage_collection' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'simple_session_garbage_collection' );
	}
}

//add_action( 'simple_session_garbage_collection', 'simple_session_cleanup' );
//add_action( 'wp', 'simple_session_register_garbage_collection' );
