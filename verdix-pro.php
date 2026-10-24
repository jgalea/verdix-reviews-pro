<?php
/**
 * Plugin Name: Verdix Reviews Pro
 * Plugin URI:  https://github.com/jgalea/verdix-reviews-pro
 * Description: Pro add-on for Verdix Reviews. Adds multi-criteria ratings, visitor ratings, extra rating types and box styles, comparison tables, AI-drafted pros and cons, and migration from WP Product Review.
 * Version:     1.2.0
 * Author:      Jean Galea
 * Author URI:  https://jeangalea.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: verdix-pro
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI:  https://github.com/jgalea/verdix-reviews-pro
 * Requires Plugins: verdix-reviews
 */

defined( 'ABSPATH' ) || exit;

define( 'VERDIX_PRO_VERSION', '1.2.0' );
define( 'VERDIX_PRO_FILE', __FILE__ );
define( 'VERDIX_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'VERDIX_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'VERDIX_PRO_BASENAME', plugin_basename( __FILE__ ) );

function verdix_pro_has_free_plugin() {
	return defined( 'VERDIX_VERSION' );
}

function verdix_pro_missing_notice() {
	if ( verdix_pro_has_free_plugin() ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><strong><?php esc_html_e( 'Verdix Reviews Pro requires the Verdix Reviews plugin to be installed and active.', 'verdix-pro' ); ?></strong></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'verdix_pro_missing_notice' );

require_once VERDIX_PRO_DIR . 'includes/updater.php';

function verdix_pro_init() {
	if ( ! verdix_pro_has_free_plugin() ) {
		return;
	}

	require_once VERDIX_PRO_DIR . 'includes/rating-types.php';
	require_once VERDIX_PRO_DIR . 'includes/styles.php';
	require_once VERDIX_PRO_DIR . 'includes/criteria.php';
	require_once VERDIX_PRO_DIR . 'includes/migration-wpr.php';
	require_once VERDIX_PRO_DIR . 'includes/visitor-ratings.php';
	require_once VERDIX_PRO_DIR . 'includes/visitor-ratings-display.php';
	require_once VERDIX_PRO_DIR . 'includes/visitor-ratings-schema.php';
	require_once VERDIX_PRO_DIR . 'includes/ai-draft.php';
	require_once VERDIX_PRO_DIR . 'includes/comparison.php';
	require_once VERDIX_PRO_DIR . 'includes/comparison-block.php';
}
add_action( 'plugins_loaded', 'verdix_pro_init' );
