<?php
/**
 * Plugin Name: Kernl.us plugin repository
 * Description: Adds a plugin repository from plugins hosted on <a href="https://kernl.us" target="_blank">kernl.us</a> for a simple installation
 * Author:      Maksimer AS
 * Author URI:  https://www.maksimer.no/
 * Version:     1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'KERNL_PLUGIN_REPOSITORY_DIR', plugin_dir_path( __FILE__ ) );
define( 'KERNL_PLUGIN_REPOSITORY_URL', plugin_dir_url( __FILE__ ) );

if ( is_admin() ) {
	require KERNL_PLUGIN_REPOSITORY_DIR . 'assets/updates/plugin_update_check.php';
	$MyUpdateChecker = new PluginUpdateChecker_2_0 ( 'https://kernl.us/api/v1/updates/58a4b555cae8bf04c6c617e0/', __FILE__, 'kernl-wp-plugin-repository', 1 );
}

if ( ! class_exists( 'Kernl_Plugin_Repository' ) ) :
	class Kernl_Plugin_Repository {
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_init', array( $this, 'page_init' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_ajax_kernl_install_plugin', array( $this, 'install_plugin' ) );
			add_action( 'wp_ajax_kernl_activate_plugin', array( $this, 'activate_plugin' ) );

			register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
		}





		public function enqueue_scripts( $hook ) {
			// Exit if not on kernl plugins page
			if ( 'plugins_page_kernl-plugins' != $hook ) {
				return;
			}

			wp_register_script( 'kernl_plugin_installer', KERNL_PLUGIN_REPOSITORY_URL . '/assets/js/kernl-wp-plugin-repository.js' );

			$translation = array(
				'installing' => __( 'Installing...' ),
				'installed'  => __( 'Installed!' ),
				'activating' => __( 'Activating', 'kernl-plugin-repository' ),
				'activate'   => __( 'Activate' ),
				'activated'  => __( 'Activated', 'kernl-plugin-repository' ),
			);
			wp_localize_script( 'kernl_plugin_installer', 'php', $translation );

			wp_enqueue_script( 'listjs', KERNL_PLUGIN_REPOSITORY_URL . '/assets/js/list.js', array( 'jquery' ) );
			wp_enqueue_script( 'kernl_plugin_installer' );
		}





		public function register_menu() {
			if ( empty( $GLOBALS['admin_page_hooks']['kernl-plugins'] ) ) {
				add_submenu_page(
					'plugins.php',
					__( 'Kernl.us plugins', 'kernl-plugin-repository' ),
					__( 'Kernl.us plugins', 'kernl-plugin-repository' ),
					'update_core',
					'kernl-plugins',
					array( $this, 'view' )
				);
			}
		}





		public function page_init() {
			register_setting(
				'kernl_plugin_repo',
				'kernl_plugin_repo'
			);

			add_settings_section(
				'kernl_plugin_repo_id',
				'',
				'',
				'kernl_plugin_repo_sections'
			);

			add_settings_field(
				'login_details',
				__( 'Login to your kernl.us account', 'kernl-plugin-repository' ),
				array( $this, 'kernl_login_form' ),
				'kernl_plugin_repo_sections',
				'kernl_plugin_repo_id',
				array(
					'title' => __( 'Login to your kernl.us account', 'kernl-plugin-repository' ),
					'key'   => 'login_details',
				)
			);

			if ( isset( $_GET['action'] ) && ( 'logout' == $_GET['action'] ) ) {
				delete_option( 'kernl_plugin_repo' );
				$url = remove_query_arg( 'action', $_SERVER['REQUEST_URI'] );
				wp_redirect( $url );
			}
		}





		public function kernl_login_form( $params ) {
			$value = get_option( 'kernl_plugin_repo' );
			$value = isset( $value[ $params['key'] ] ) ? $value[ $params['key'] ] : false;
			$name  = 'kernl_plugin_repo[login_details]';
			echo '<fieldset>';
				echo '<legend class="screen-reader-text"><span>' . $params['title'] . '</span></legend>';
				echo '<input type="text" class="regular-text" name="' . $name . '[username]" value="' . esc_attr( $value['username'] ) . '" placeholder="' . __( 'Username', 'kernl-plugin-repository' ) . '"><br>';
				echo '<input type="password" class="regular-text" name="' . $name . '[password]" value="' . esc_attr( $value['password'] ) . '" placeholder="' . __( 'Password', 'kernl-plugin-repository' ) . '">';
				if ( isset( $params['description'] ) ) {
					echo '<span class="description">' . $params['description'] . '</span>';
				}
			echo '</fieldset>';
		}





		public function kernl_token() {
			$kernl_settings = get_option( 'kernl_plugin_repo' );
			$username = isset( $kernl_settings['login_details']['username'] ) ? $kernl_settings['login_details']['username'] : false;
			$password = isset( $kernl_settings['login_details']['password'] ) ? $kernl_settings['login_details']['password'] : false;

			if ( ( !empty( $username ) ) && ( !empty( $password ) ) ) {
				$args = array(
					'body' => array(
						'email'    => $username,
						'password' => $password,
					),
				);

				$auth = wp_remote_post( 'https://kernl.us/api/v1/auth', $args );

				if ( 200 == $auth['response']['code'] ) {
					return $auth['body'];
				}

				return false;
			}

			return false;
		}





		public function all_plugins() {
			if ( $this->kernl_token() ) {
				$plugins_args = array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->kernl_token(),
					),
				);
				$get_plugins  = wp_remote_get( 'https://kernl.us/api/v1/plugins/', $plugins_args );
				$plugins      = $get_plugins['body'];
				$plugins      = json_decode( $plugins );

				return $plugins;
			}
		}





		public function view() {
			echo '<div id="plugin-list" class="wrap">';
				echo '<h1>' . __( 'Kernl.us plugins', 'kernl-plugin-repository' ) . '</h1>';
				if ( $this->kernl_token() ) {
					$this->logged_in_view();
				} else {
					$this->not_logged_in_view();
				}
			echo '</div>';
		}





		public function logged_in_view() {
			if ( $this->all_plugins() ) {
				$kernl_settings = get_option( 'kernl_plugin_repo' );
				$logout_url     = add_query_arg( 'action', 'logout', $_SERVER['REQUEST_URI'] );
				echo '<div class="wp-filter">';
					echo '<span class="filter-items" style="margin-top: 9px;">' . __( 'Logged in as', 'kernl-plugin-repository' ) . ' ' . $kernl_settings['login_details']['username'] . '<br><a href="' . $logout_url . '">' . __( 'Sign out' ) . '</a></span>';
					echo '<form class="search-form search-plugins">';
						echo '<input type="search" name="s" value="" class="wp-filter-search fuzzy-search" placeholder="' . __( 'Search plugins...' ) . '">';
					echo '</form>';
				echo '</div>';

				echo '<div class="wp-list-table widefat plugin-install">';
					echo '<ul class="list" id="the-list">';
						foreach ( $this->all_plugins() as $plugin ) {
							$this->single_plugin_view( $plugin );
						}
					echo '</ul>';
				echo '</div>';
			}
		}





		public function not_logged_in_view() {
			echo '<form method="post" action="options.php">';
				settings_fields( 'kernl_plugin_repo' );
				do_settings_sections( 'kernl_plugin_repo_sections' );
				submit_button( __( 'Login' ) );
			echo '</form>';
		}





		public function single_plugin_view( $plugin ) {
			if ( isset( $plugin->latestVersion ) ) {
				$plugins_found = false;
				$files = glob( WP_PLUGIN_DIR . '/' . $plugin->slug . '/*.php' );
				if ( $files ) {
					foreach ( $files as $file ) {
						$info = get_plugin_data( $file, false, false );
						if ( ! empty( $info['Name'] ) ) {
							$plugins_found = true;
							break;
						}
					}
				}

				echo '<li class="plugin-card">';
					echo '<div class="plugin-card-top" style="min-height:1px;">';
						echo '<div class="column-name">';
							echo '<h3>';
								echo '<a href="#" class="thickbox open-plugin-details-modal plugin-name">';
									echo $plugin->name;
								echo '</a>';
							echo '</h3>';
						echo '</div>';
						echo '<div class="action-links">';
							echo '<ul class="plugin-action-buttons">';
								if ( true == $plugins_found ) {
									echo '<li><a class="disabled button">' . __( 'Installed!' ) . '</a></li>';
								} else {
									$nonce = wp_create_nonce( 'updates' );
									echo '<li><a class="install-now button" data-nonce="' . $nonce . '" data-slug="' . esc_url( $plugin->latestVersion->fileName ) . '" href="#">' . __( 'Install Now' ) . '</a></li>';
								}
							echo '</ul>';
						echo '</div>';
						echo '<p>' . $plugin->description . '</p>';
					echo '</div>';
					echo '<div class="plugin-card-bottom">';
						echo '<div class="vers column-rating">';
							echo sprintf( __( 'Version %s' ), $plugin->latestVersion->version);
						echo '</div>';
						echo '<div class="column-updated">';
							echo '<strong>' . __( 'Last Updated:' ) . ' </strong>';
							$date = new DateTime( $plugin->latestVersion->uploadedDate );
							echo $date->format( 'd.m.Y' );
						echo '</div>';
					echo '</div>';
				echo '</li>';
			}
		}





		public function install_plugin() {
			if ( isset( $_POST['slug'] ) ) {
				include_once( ABSPATH . 'wp-admin/includes/file.php' );
				include_once( ABSPATH . 'wp-admin/includes/misc.php' );
				include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
				include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
				include_once( KERNL_PLUGIN_REPOSITORY_DIR . 'classes/kernl-plugin-installer-skin.php' );

				$upgrader  = new Plugin_Upgrader( new Kernl_Plugin_Installer_Skin() );
				$upgrader->install( esc_url( $_POST['slug'] ) );
			}

			die();
		}





		public function activate_plugin() {
			if ( isset( $_POST['plugin_folder'] ) ) {
				$active     = get_option( 'active_plugins' );
				$new_plugin = plugin_basename( trim( $_POST['plugin_folder'] . '/' . $_POST['main_file'] ) );

				if ( ! in_array( $new_plugin, $active ) ) {
					$active[] = $new_plugin;
					sort( $active );
					do_action( 'activate_plugin', trim( $new_plugin ) );
					update_option( 'active_plugins', $active );
					do_action( 'activate_' . trim( $new_plugin ) );
					do_action( 'activated_plugin', trim( $new_plugin ) );
				}
			}

			die();
		}





		public function deactivation_hook() {
			delete_option( 'kernl_plugin_repo' );
		}
	}

	$kernl_plugin_repository = new Kernl_Plugin_Repository();
endif;
