<?php
/**
 * Erase Personal Data Command Entry Point
 */

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

require_once __DIR__ . '/src/ErasePersonalDataCommand.php';

WP_CLI::add_command( 'erase-personal-data', 'WP_CLI\ErasePersonalData\ErasePersonalDataCommand' );
