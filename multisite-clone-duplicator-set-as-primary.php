<?php
/**
 * Plugin Name:         MultiSite Clone Duplicator - Set as primary
 * Plugin URI:          https://github.com/pierre-dargham/multisite-clone-duplicator-set-as-primary
 * Description:         When you duplicate a site : make the new site erase the primary site
 * Author:              Pierre DARGHAM
 * Author URI:          https://github.com/pierre-dargham/
 *
 * Version:             0.1.0
 * Requires at least:   3.5.0
 * Tested up to:        4.2.2
 */

function mucd_remove_primary_dir( $from_site_id, $to_site_id ) {

	switch_to_blog( 1 );
	$wp_upload_info = wp_upload_dir();
	$dir = str_replace( ' ', '\\ ', trailingslashit( $wp_upload_info['basedir'] ) );
	restore_current_blog();

	rrmdir_inside_and_exclude( $dir, array( 'sites' ) );
}
add_action( 'mucd_before_copy_files', 'mucd_remove_primary_dir', 10, 2 );


function mucd_my_copy_dirs( $dirs, $from_site_id, $to_site_id ) {

	switch_to_blog( 1 );
	$wp_upload_info = wp_upload_dir();
	$dir = str_replace( ' ', '\\ ', trailingslashit( $wp_upload_info['basedir'] ) );
	restore_current_blog();

	$dirs[0]['to_dir_path'] = $dir;

	return $dirs;
}
add_filter( 'mucd_copy_dirs', 'mucd_my_copy_dirs', 10, 3 );

function mucd_my_copy_blog_data_saved_options( $saved_options ) {

	unset($saved_options['admin_email']);
	unset($saved_options['blogname']);
	
	return $saved_options;
}
add_filter( 'mucd_copy_blog_data_saved_options', 'mucd_my_copy_blog_data_saved_options', 10, 1 );


function mucd_my_string_to_replace( $string_to_replace, $from_site_id, $to_site_id ) {

			global $wpdb;

			switch_to_blog( 1 );

			$dir = wp_upload_dir();

			$primary_upload_url = str_replace( network_site_url(), get_bloginfo( 'url' ) . '/', $dir['baseurl'] );
			$primary_blog_url = get_blog_option( $to_site_id, 'siteurl' );
			$primary_blog_prefix = $wpdb->get_blog_prefix( $to_site_id );

			restore_current_blog();

			$string_to_replace[0] = $primary_upload_url;
			$string_to_replace[1] = $primary_blog_url;
			$string_to_replace[2] = $primary_blog_prefix;

}
add_filter( 'mucd_string_to_replace', 'mucd_my_string_to_replace', 10, 3 );

function mucd_changes_tables( $from_site_id, $to_site_id ) {
	MUCD_Data::db_copy_tables( $from_site_id, 1 );
	remove_users( 1 );
	MUCD_Duplicate::copy_users( $from_site_id, 1 );
	MUCD_Functions::remove_blog( $to_site_id );
}
add_action( 'mucd_after_copy_data', 'mucd_changes_tables', 10, 2 );

function rrmdir_inside_and_exclude( $dir, $exclude ) {
	if ( is_dir( $dir ) ) {
		$objects = scandir( $dir );
		foreach ( $objects as $object ) {
			if ( $object != '.' && $object != '..' && ! in_array( $object, $exclude ) ) {
				if ( 'dir' == filetype( $dir . '/' . $object ) ) {
					MUCD_Files::rrmdir( $dir . '/' . $object );
				}
				else {
					unlink( $dir . '/' . $object );
				}
		   	}
		}
		reset( $objects );
   	}
}

function remove_users( $from_site_id ) {

	global $wpdb;

	// Source Site information
	$from_site_prefix = $wpdb->get_blog_prefix( $from_site_id );		// prefix
	$from_site_prefix_length = strlen( $from_site_prefix );				// prefix length

	$users = get_users( 'blog_id='.$from_site_id );

	$admin_email = get_blog_option( $from_site_id, 'admin_email' , 'false' );

	switch_to_blog( $from_site_id );

	foreach ( $users as $user ) {
		if ( $user->user_email != $admin_email ) {
			remove_user_from_blog( $from_site_id, $user->ID );
		}
	}

	restore_current_blog();
}