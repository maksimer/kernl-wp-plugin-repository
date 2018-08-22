<?php
include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

class Kernl_Plugin_Installer_Skin extends WP_Upgrader_Skin {
	public function feedback( $string ) {
		$plugin_info   = $this->result;
		$destination   = $plugin_info['destination'];
		$main_file     = false;
		$plugins_found = false;
		$files         = glob( $destination . '*.php' );

		if ( $files ) {
			foreach ( $files as $file ) {
				$info = get_plugin_data( $file, false, false );
				if ( ! empty( $info['Name'] ) ) {
					$main_file     = $file;
					$plugins_found = true;
					break;
				}
			}
		}

		if ( true == $plugins_found ) {
			// Strip the whole path
			$main_file = substr( $main_file, strrpos( $main_file, '/' ) + 1 );

			$response = array(
				'plugin_folder' => $plugin_info['destination_name'],
				'main_file'     => $main_file,
			);

			echo json_encode( $response );
		}
	}





	public function header() {
		return false;
	}





	public function footer() {
		return false;
	}
}
