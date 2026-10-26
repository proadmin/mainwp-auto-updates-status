<?php
/**
 * Plugin Name: MainWP Auto Updates Status Extension
 * Plugin URI:  https://proadminwp.net/
 * Description: Adds an Auto Updates widget to the MainWP Operations dashboard showing the status, schedule, live progress and run history of MainWP's Advanced Automatic Updates.
 * Version:     1.0.1
 * Author:      ProAdmin
 * Author URI:  https://proadminwp.net/
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: mainwp-auto-updates-status
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Copyright:   (c) 2026 ProAdmin
 *
 * @package   MainWP_Auto_Updates_Status
 * @author    ProAdmin <https://proadminwp.net/>
 * @copyright Copyright (c) 2026 ProAdmin
 * @license   GPL-2.0-or-later
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAINWP_AUS_VERSION', '1.0.1' );
define( 'MAINWP_AUS_DB_VERSION', '1.0' );
define( 'MAINWP_AUS_FILE', __FILE__ );
define( 'MAINWP_AUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAINWP_AUS_URL', plugin_dir_url( __FILE__ ) );

require_once MAINWP_AUS_DIR . 'includes/class-db.php';
require_once MAINWP_AUS_DIR . 'includes/class-recorder.php';
require_once MAINWP_AUS_DIR . 'includes/class-status.php';
require_once MAINWP_AUS_DIR . 'includes/class-widget.php';
require_once MAINWP_AUS_DIR . 'includes/class-admin-page.php';

/**
 * Bootstrap class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether MainWP Dashboard has been detected.
	 *
	 * @var bool
	 */
	private $mainwp_active = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_filter( 'mainwp_getextensions', array( $this, 'register_extension' ) );
	}

	/**
	 * Wire everything up once all plugins are loaded.
	 */
	public function init() {
		load_plugin_textdomain( 'mainwp-auto-updates-status', false, dirname( plugin_basename( MAINWP_AUS_FILE ) ) . '/languages' );

		$this->mainwp_active = class_exists( '\MainWP\Dashboard\MainWP_System' );

		if ( ! $this->mainwp_active ) {
			add_action( 'admin_notices', array( $this, 'missing_mainwp_notice' ) );
			return;
		}

		DB::instance()->maybe_upgrade();

		// The recorder must run on cron requests too, since that is where auto updates happen.
		Recorder::instance()->init();

		if ( is_admin() ) {
			Widget::instance()->init();
			Admin_Page::instance()->init();
		}
	}

	/**
	 * Register with MainWP so the extension appears on the Extensions page.
	 *
	 * @param array $extensions Registered extensions.
	 * @return array
	 */
	public function register_extension( $extensions ) {
		$extensions[] = array(
			'plugin'   => MAINWP_AUS_FILE,
			'callback' => array( Admin_Page::instance(), 'render' ),
			'icon'     => 'sync alternate',
		);
		return $extensions;
	}

	/**
	 * Notice shown when MainWP Dashboard is not active.
	 */
	public function missing_mainwp_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'MainWP Auto Updates Status Extension requires the MainWP Dashboard plugin to be installed and active.', 'mainwp-auto-updates-status' ) . '</p></div>';
	}

	/**
	 * Activation: create tables and import whatever MainWP already holds.
	 */
	public static function activate() {
		DB::instance()->install();
		Recorder::instance()->import_existing();
	}
}

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

Plugin::instance();
