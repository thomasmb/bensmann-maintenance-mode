<?php

/**
 * Plugin Name: Bensmann Maintenance Mode
 * Description: Put WordPress in maintenance mode
 * Version: 1.1
 * 
 * Instructions:
 * Place this file in wp-content/mu-plugins/maintenance.php and create
 * a maintenance.html file in the root of your WordPress installation.
 * 
 * To enable maintenance mode, add the following to your wp-config.php:
 * define( 'BENSMANN_MAINTENANCE', true );
 * 
 * To allow administrators to access the site, add this to your wp-config.php:
 * define( 'BENSMANN_MAINTENANCE_ALLOW_ADMINS', true );
 * 
 * To allow WP-CLI commands to run, add this to your wp-config.php:
 * define( 'BENSMANN_MAINTENANCE_ALLOW_WP_CLI', true );
 * 
 * To bypass maintenance mode for a single wp-cli command, add this to your command:
 * --exec='define("BENSMANN_MAINTENANCE_BYPASS", true);'
 */

if( !defined( 'ABSPATH' ) ){
	exit;
}

if( !defined( 'BENSMANN_MAINTENANCE' ) ) {
	define( 'BENSMANN_MAINTENANCE', false );
}

if( !defined( 'BENSMANN_MAINTENANCE_ALLOW_ADMINS' ) ) {
	define( 'BENSMANN_MAINTENANCE_ALLOW_ADMINS', false );
}

if( !defined( 'BENSMANN_MAINTENANCE_ALLOW_WP_CLI' ) ) {
	define( 'BENSMANN_MAINTENANCE_ALLOW_WP_CLI', false );
}

if( !defined( 'BENSMANN_MAINTENANCE_BYPASS' ) ) {
	define( 'BENSMANN_MAINTENANCE_BYPASS', false );
}

add_action( 'muplugins_loaded', 'bensmann_maintenance_mode_register' );

/**
 * Check if maintenance mode is enabled.
 *
 * @return bool True if maintenance mode is enabled, false otherwise.
 */
function bensmann_maintenance_mode_is_enabled() {
	return defined( 'BENSMANN_MAINTENANCE' ) && BENSMANN_MAINTENANCE;
}

/**
 * Check if maintenance mode is bypassed.
 *
 * @return bool True if maintenance mode is bypassed, false otherwise.
 */
function bensmann_maintenance_mode_is_bypassed() {
	return defined( 'BENSMANN_MAINTENANCE_BYPASS' ) && BENSMANN_MAINTENANCE_BYPASS;
}

/**
 * Check if administrators are allowed to access the site during maintenance mode.
 *
 * @return bool True if administrators are allowed, false otherwise.
 */
function bensmann_maintenance_mode_allow_admins() {
	return defined( 'BENSMANN_MAINTENANCE_ALLOW_ADMINS' ) && BENSMANN_MAINTENANCE_ALLOW_ADMINS;
}

/**
 * Check if WP-CLI commands are allowed during maintenance mode.
 *
 * @return bool True if WP-CLI commands are allowed, false otherwise.
 */
function bensmann_maintenance_mode_allow_wp_cli() {
	return defined( 'BENSMANN_MAINTENANCE_ALLOW_WP_CLI' ) && BENSMANN_MAINTENANCE_ALLOW_WP_CLI;
}

/**
 * Register the maintenance mode functionality.
 * Handles bypassing, WP-CLI, admin access, etc.
 */
function bensmann_maintenance_mode_register(){
	$is_bypassed = bensmann_maintenance_mode_is_bypassed();
	$is_enables = bensmann_maintenance_mode_is_enabled();
	$allow_admins = bensmann_maintenance_mode_allow_admins();
	$allow_wp_cli = bensmann_maintenance_mode_allow_wp_cli();
	$is_wp_cli = defined( 'WP_CLI' ) && WP_CLI;

	// Don't check anything if we are bypassing the maintenance mode
	if( $is_bypassed ){
		return;
	}

	// If the site is not in maintenance mode, return
	if( !$is_enables ){
		return;
	}

	// Allow WP CLI to run if the constant is set
	if( $allow_wp_cli && $is_wp_cli ){
		return;
	}

	// If WP CLI is not allowed and the user is WP CLI, show an error
	if( $is_wp_cli ) {
		WP_CLI::error( implode( PHP_EOL, [
			'Maintenance mode is enabled.',
			'',
			'Allow WP-CLI while maintenance is on:',
			WP_CLI::colorize( '  %Gwp config set BENSMANN_MAINTENANCE_ALLOW_WP_CLI true --raw%n' ),
			'',
			'…or bypass maintenance for this one-off command:',
			WP_CLI::colorize( '  %G--exec=\'define("BENSMANN_MAINTENANCE_BYPASS", true);\' %n' ),
		] ) );
	}

	// Wait for init so we can check if the user is an admin
	if( $allow_admins ) {
		add_action( 'init', 'bensmann_maintenance_mode_output' );
		return;
	}

	bensmann_maintenance_mode_output();
}

/**
 * Output the maintenance mode page and terminate the request.
 * Allows admin access to wp-login.php if configured.
 */
function bensmann_maintenance_mode_output(){

	if( current_action() === 'init' && current_user_can( 'manage_options' ) ){
		return;
	}

	$allow_admins = bensmann_maintenance_mode_allow_admins();
	$is_login = isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false;

	// Allow access to wp-login.php if allow admins is true
	if( $allow_admins && $is_login ){
		return;
	}

	// Output 503 header
	http_response_code(503);
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Retry-After: 600' );

	include( ABSPATH . 'maintenance.html' );
	exit;
}
