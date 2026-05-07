<?php
/**
 * Centralized MediaWiki bootstrap for SMW maintenance scripts.
 *
 * Uses MW_INSTALL_PATH when available and includes Maintenance.php from the
 * appropriate location, with a conventional relative fallback for extension
 * checkouts.
 */

// @codeCoverageIgnoreStart

// Prefer existing environment config
$mwInstallPath = getenv( 'MW_INSTALL_PATH' );

// Try to include from MW_INSTALL_PATH if valid; otherwise fall back to the
// conventional relative path used by extensions shipped under extensions/
$maintenanceFilePath = __DIR__ . '/../../../maintenance/Maintenance.php';
if ( $mwInstallPath !== false && $mwInstallPath !== '' ) {
	$candidateMaintenanceFilePath = rtrim( $mwInstallPath, DIRECTORY_SEPARATOR ) . '/maintenance/Maintenance.php';
	if ( is_file( $candidateMaintenanceFilePath ) ) {
		$maintenanceFilePath = $candidateMaintenanceFilePath;
	}
}
require_once $maintenanceFilePath;

// @codeCoverageIgnoreEnd
