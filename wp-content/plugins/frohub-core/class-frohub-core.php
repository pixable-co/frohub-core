<?php

	/**
	 *
	 * @link              https://pixable.co/
	 * @since             0.0.4
	 * @package           Frohub Ecommerce Core Plugin
	 *
	 * @wordpress-plugin
	 * Plugin Name:       Frohub Ecommerce Core Plugin
	 * Plugin URI:        https://pixable.co/
	 * Description:       Core Plugin & Functions For Frohub Ecommerce
	 * Version:           0.0.4
	 * Author:            Pixable
	 * Author URI:        https://pixable.co/
	 * License:           GPL-2.0+
	 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
	 * Text Domain:       frohub-core
	 * Tested up to:      6.7
	 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FrohubCore {

	private function __construct() {
		$this->define_constants();
		$this->load_dependency();
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

			return $instance;
	}

	public function define_constants() {
		define( 'FHCORE_VERSION', '0.0.4' );
		define( 'FHCORE_PLUGIN_FILE', __FILE__ );
		define( 'FHCORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'FHCORE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'FHCORE_ROOT_DIR_URL', plugin_dir_url( __FILE__ ) );
		define( 'FHCORE_INCLUDES_DIR_PATH', FHCORE_ROOT_DIR_PATH . 'includes/' );
		define( 'FHCORE_PLUGIN_SLUG', 'frohub-core' );
	}

	public function on_plugins_loaded() {
		do_action( 'frohub_loaded' );
	}

	public function init_plugin() {
		$this->load_textdomain();
		$this->dispatch_hooks();
	}

	public function dispatch_hooks() {
		FECore\Autoload::init();
		FECore\Enqueue::init();
		FECore\Shortcodes::init();
		FECore\API::init();
		FECore\Ajax::init();
		FECore\Actions::init();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'frohub-core',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	public function load_dependency() {
		require_once FHCORE_INCLUDES_DIR_PATH . 'class-autoload.php';
	}

	public function activate() {
	}

	public function deactivate() {
	}
}

function frohub_start() {
	return FrohubCore::init();
}


frohub_start();