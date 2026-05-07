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
