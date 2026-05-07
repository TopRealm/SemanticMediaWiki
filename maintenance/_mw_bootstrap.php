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
$mwInstallPathEnv = getenv( 'MW_INSTALL_PATH' );

// Optional default when not provided; only apply if it exists
$defaultMwInstallPath = '/www/wwwroot/MediaWiki';
if ( ( $mwInstallPath === false || $mwInstallPath === '' ) && is_dir( $defaultMwInstallPath ) ) {
    putenv( 'MW_INSTALL_PATH=' . $defaultMwInstallPath );
    $mwInstallPath = $defaultMwInstallPath;
}

// Try to include from MW_INSTALL_PATH if valid; otherwise fall back to the
// conventional relative path used by extensions shipped under extensions/
$candidateMaintenanceFilePath = null;
if ( $mwInstallPathEnv !== false && $mwInstallPathEnv !== '' ) {
	$candidateMaintenanceFilePath = rtrim( $mwInstallPathEnv, DIRECTORY_SEPARATOR ) . '/maintenance/Maintenance.php';
}

require_once ( $candidateMaintenanceFilePath !== null && is_file( $candidateMaintenanceFilePath ) )
	? $candidateMaintenanceFilePath
	: __DIR__ . '/../../../maintenance/Maintenance.php';

// @codeCoverageIgnoreEnd
