<?php
/**
 * Frontend output handling.
 *
 * Registers the HTML rewriter when a theme is active.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FastCloud\WordPress\Html_Rewriter;

if ( wp_using_themes() ) {
	Html_Rewriter::register();
}
