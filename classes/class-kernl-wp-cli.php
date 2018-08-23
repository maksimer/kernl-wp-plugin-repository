<?php
defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class Kernl_WP_CLI {
		/**
		 * Just an empty contstruct for now
		 */
		public function __construct() {}





		/**
		 * Fetch all plugins from kernl.
		 *
		 * @return array All kernl plugins from authorized account
		 */
		public function all_plugins() {
			$kernl_token = Kernl_Plugin_Repository::kernl_token();

			if ( $kernl_token ) {
				$plugins_args = array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $kernl_token,
					),
				);

				$get_plugins = wp_remote_get( 'https://kernl.us/api/v1/plugins/', $plugins_args );
				$plugins     = $get_plugins['body'];
				$plugins     = json_decode( $plugins, true );

				return $plugins;
			}
		}





		/**
		 * Format list of plugins to suit needs of Kernl cli
		 *
		 * @return array All plugins in suited format
		 */
		public function plugin_list_formatted() {
			$plugins   = $this->all_plugins();
			$formatted = array();

			foreach ( $plugins as $key => $plugin ) {
				$formatted[ $key ] = [
					'name'    => $plugin['name'],
					'status'  => $plugin['active'] ? 'active' : 'inactive',
					'version' => $plugin['latestVersion']['version'],
				];
			}

			return $formatted;
		}





		/**
		 * Gets a list of Kernl plugins.
		 *
		 * Displays a list of the plugins installed on the site with activation
		 * status, whether or not there's an update available, etc.
		 *
		 * ## AVAILABLE FIELDS
		 *
		 * These fields will be displayed by default for each plugin:
		 *
		 * * name
		 * * status
		 * * version
		 *
		 * ## EXAMPLES
		 *
		 *     # List all plugins
		 *     $ wp kernl list
		 *     +---------+----------------+--------+---------+
		 *     | name    | status         | update | version |
		 *     +---------+----------------+--------+---------+
		 *     | akismet | active         | none   | 3.1.11  |
		 *     | hello   | inactive       | none   | 1.6     |
		 *     +---------+----------------+--------+---------+
		 *
		 * @subcommand list
		 */
		public function list() {
			$fields = array( 'name', 'status', 'version' );
			$items  = $this->plugin_list_formatted();

			WP_CLI\Utils\format_items( 'table', $items, $fields );
		}
	}

	/**
	 * Add new command to WP-CLI
	 */
	WP_CLI::add_command( 'kernl', 'Kernl_WP_CLI' );
}
