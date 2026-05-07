<?php
/**
 * Centralized MediaWiki bootstrap for SMW maintenance scripts.
 *
 * Ensures MW_INSTALL_PATH is available and includes Maintenance.php from the
 * appropriate location. Only sets MW_INSTALL_PATH when a sensible default
 * directory exists to avoid breaking custom layouts.
 */

// @codeCoverageIgnoreStart

// Prefer existing environment config
$mwInstallPath = getenv( 'MW_INSTALL_PATH' );

// Optional default when not provided; only apply if it exists
$defaultMwInstallPath = '/www/wwwroot/MediaWiki';
if ( ( $mwInstallPath === false || $mwInstallPath === '' ) && is_dir( $defaultMwInstallPath ) ) {
    putenv( 'MW_INSTALL_PATH=' . $defaultMwInstallPath );
    $mwInstallPath = $defaultMwInstallPath;
}

// Try to include from MW_INSTALL_PATH if valid; otherwise fall back to the
// conventional relative path used by extensions shipped under extensions/
if ( $mwInstallPath !== false && $mwInstallPath !== '' ) {
    $mwMaintenance = rtrim( $mwInstallPath, DIRECTORY_SEPARATOR ) . '/maintenance/Maintenance.php';
    if ( is_file( $mwMaintenance ) ) {
        require_once $mwMaintenance;
    } else {
        require_once __DIR__ . '/../../../maintenance/Maintenance.php';
    }
} else {
    require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

// @codeCoverageIgnoreEnd
