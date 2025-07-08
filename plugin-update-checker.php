<?php
/**
 * Plugin Name:       Plugin Update Checker
 * Plugin URI:        https://github.com/utkwdn/plugin-update-checker
 * Description:       Checks for updates for GitHub-hosted plugins
 * Version:           1.0.0
 * Author:            The University of Tennessee, Knoxville
 *
 * @package PluginUpdateChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register the update checker.
add_filter( 'pre_set_site_transient_update_plugins', 'ghpu_check_for_github_plugin_updates' );

/**
 * Returns a list of GitHub-hosted plugins to check for updates.
 *
 * Each plugin should include a package.json file with version attribute
 *
 * @return array[]
 */
function ghpu_get_plugin_update_configs() {
	return array(
		array(
			'directory' => 'plugin-update-checker',
			'entryFile' => 'plugin-update-checker.php',
		),
		array(
			'directory' => 'a-to-z-plugin',
			'entryFile' => 'a-to-z-plugin.php',
		),
		array(
			'directory' => 'home-hero-plugin',
			'entryFile' => 'home-hero-plugin.php',
		),
		array(
			'directory' => 'degree-search-plugin',
			'entryFile' => 'degree-search-plugin.php',
		),
		array(
			'directory' => 'custom-search-template-plugin',
			'entryFile' => 'custom-search-template.php',
		),
		array(
			'directory' => 'vision-page-plugin',
			'entryFile' => 'vision-page-plugin.php',
		),
		array(
			'directory' => 'ut-alert-plugin',
			'entryFile' => 'ut-alert.php',
		),
		array(
			'directory' => 'dynamic-content-plugin',
			'entryFile' => 'dynamic-content-plugin.php',
		),
	);
}

/**
 * Check GitHub repositories for plugin updates and updates the transient if needed.
 *
 * @param stdClass $transient The plugin update transient.
 * @return stdClass
 */
function ghpu_check_for_github_plugin_updates( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$org     = 'utkwdn';
	$plugins = ghpu_get_plugin_update_configs();

	foreach ( $plugins as $plugin ) {
		$plugin_slug = "{$plugin['directory']}/{$plugin['entryFile']}";
		$package_url = "https://raw.githubusercontent.com/{$org}/{$plugin['directory']}/refs/heads/main/package.json";

		// Check that package.json exists.
		$response = wp_remote_get( $package_url );
		if ( is_wp_error( $response ) ) {
			continue;
		}

		// Check that package.json includes version number.
		$package_info = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $package_info->version ) ) {
			continue;
		}

		// Check that zip file exists at expected location.
		$zip_url   = "https://github.com/{$org}/{$plugin['directory']}/releases/download/v{$package_info->version}/{$plugin['directory']}.zip";
		$zip_check = wp_remote_head(
			$zip_url,
			array(
				'redirection'      => 5,
				'follow_redirects' => true,
			)
		);
		if ( is_wp_error( $zip_check ) || wp_remote_retrieve_response_code( $zip_check ) !== 200 ) {
			continue;
		}

		// Check if a new version is available.
		if (
			isset( $transient->checked[ $plugin_slug ] ) &&
			version_compare( $transient->checked[ $plugin_slug ], $package_info->version, '<' )
		) {
			$transient->response[ $plugin_slug ] = (object) array(
				'slug'        => $plugin['directory'],
				'plugin'      => $plugin_slug,
				'new_version' => $package_info->version,
				'url'         => "https://github.com/{$org}/{$plugin['directory']}/",
				'package'     => $zip_url,
			);
		}
	}

	return $transient;
}
