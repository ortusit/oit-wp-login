<?php
/**
 * Plugin Name: OrtusIT New WP-Admin URL
 * Version: 1.4
 * Plugin URI: https://github.com/ortusit/oit-wp-login
 * Description: Set a custom login URL slug instead of wp-login.php (Settings → Permalinks). Uses rewrite rules or .htaccess when needed and session checks so only your slug can open the login flow.
 * Author: OrtusIT
 * Author URI: https://ortusit.com/
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Requires PHP: 7.4 or higher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OIT_NewWPAdminURL' ) ) {

	class OIT_NewWPAdminURL {

		/**
		 * Read .htaccess as a single string; empty string if missing/unreadable.
		 *
		 * @param string $path Absolute path to .htaccess.
		 * @return string
		 */
		private function read_htaccess_file( $path ) {
			if ( ! is_string( $path ) || $path === '' || ! is_readable( $path ) ) {
				return '';
			}
			$contents = @file_get_contents( $path );
			return is_string( $contents ) ? $contents : '';
		}

		function rewrite_admin_url( $wp_rewrite ) {
			// be sure rules are written every time permalinks are updated
			$rule = get_option( 'custom_wpadmin_slug' );
			if ( $rule !== '' && $rule !== false ) {
				add_rewrite_rule( $rule . '/?$', 'wp-login.php', 'top' );
			}
		}

		function custom_admin_url() {
			if ( isset( $_POST['custom_wpadmin_slug'] ) ) {

				// sanitize input
				$wpadmin_slug = trim( sanitize_key( wp_strip_all_tags( wp_unslash( $_POST['custom_wpadmin_slug'] ) ) ) );
				$home_path     = get_home_path();

				// check if permalinks are turned off, if so force push rules to .htaccess
				if ( isset( $_POST['selection'] ) && $_POST['selection'] === '' && $wpadmin_slug !== '' ) {
					// check if .htaccess is writable
					$htaccess = $home_path . '.htaccess';
					if ( ( ! file_exists( $htaccess ) && is_writable( $home_path ) ) || is_writable( $htaccess ) ) {

						// taken from wp-includes/rewrite.php
						$parsed = parse_url( home_url() );
						if ( is_array( $parsed ) && isset( $parsed['path'] ) ) {
							$home_root = trailingslashit( $parsed['path'] );
						} else {
							$home_root = '/';
						}
						// create rules
						$rules  = "<IfModule mod_rewrite.c>\n";
						$rules .= "RewriteEngine On\n";
						$rules .= "RewriteRule ^$wpadmin_slug/?$ " . $home_root . "wp-login.php [QSA,L]\n";
						$rules .= "</IfModule>";
						// write to .htaccess
						insert_with_markers( $htaccess, 'WPAdminURL', explode( "\n", $rules ) );
					}
				} elseif ( isset( $_POST['selection'] ) ) {
					// remove rules if permalinks were enabled (or structure changed)
					$htaccess   = $home_path . '.htaccess';
					$raw        = $this->read_htaccess_file( $htaccess );
					$markerdata = $raw !== '' ? explode( "\n", $raw ) : array();
					$found      = false;
					$newdata    = '';
					foreach ( $markerdata as $line ) {
						$line = rtrim( $line, "\r\n" );
						if ( $line === '# BEGIN WPAdminURL' ) {
							$found = true;
						}
						if ( ! $found ) {
							$newdata .= $line . "\n";
						}
						if ( $line === '# END WPAdminURL' ) {
							$found = false;
						}
					}
					// write back
					$f = @fopen( $htaccess, 'w' );
					if ( $f ) {
						fwrite( $f, $newdata );
						fclose( $f );
					}
				}

				// save to db
				update_option( 'custom_wpadmin_slug', $wpadmin_slug );

				// write rewrite rules right away
				if ( $wpadmin_slug !== '' ) {
					add_rewrite_rule( $wpadmin_slug . '/?$', 'wp-login.php', 'top' );
				} else {
					flush_rewrite_rules();
				}
			}
		}

		/**
		 * Register permalink field and option (Settings → Permalinks; also options.php POST so saves work in WP 6.9.x).
		 */
		function register_permalink_settings() {
			global $pagenow;
			$permalink_post = isset( $_POST['option_page'] ) && 'permalink' === $_POST['option_page'];
			if ( 'options-permalink.php' !== $pagenow && ! ( 'options.php' === $pagenow && $permalink_post ) ) {
				return;
			}
			add_settings_field(
				'custom_wpadmin_slug',
				'New WP-Admin slug',
				array( $this, 'options_page' ),
				'permalink',
				'optional',
				array( 'label_for' => 'custom_wpadmin_slug' )
			);
			register_setting(
				'permalink',
				'custom_wpadmin_slug',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => '',
				)
			);
		}

		function options_page() {
			$slug = get_option( 'custom_wpadmin_slug' );
			if ( ! is_string( $slug ) ) {
				$slug = '';
			}
			?>
			<input id="custom_wpadmin_slug" name="custom_wpadmin_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $slug ); ?>">
			<p class="howto">Allowed characters are a-z, 0-9, - and _</p>
			<?php
		}

		// custom login url
		function custom_login() {
			// start session if doesn't exist
			if ( ! session_id() ) {
				session_start();
			}
			$url = $this->get_url();
			// see referal url by the web client (URI may have no query string — avoid undefined offset)
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
			$uri_parts   = explode( '?', $request_uri, 2 );
			$file        = $uri_parts[0];
			$arguments   = isset( $uri_parts[1] ) ? $uri_parts[1] : '';
			// on localhost remove subdir
			$file = ( $url['rewrite_base'] !== '' ) ? implode( '', explode( '/' . $url['rewrite_base'], $file ) ) : $file;

			if ( '/wp-login.php?loggedout=true' === $file . '?' . $arguments ) {
				session_destroy();
				wp_safe_redirect( home_url( '/' ) );
				exit;
			} elseif ( 0 === strpos( (string) $arguments, 'action=logout' ) ) {
				unset( $_SESSION['valid_login'] );
			} elseif ( 'action=lostpassword' === $url['query'] || 'action=postpass' === $url['query'] ) {
				// let user to this pages
			} elseif ( $file === '/' . get_option( 'custom_wpadmin_slug' ) || $file === '/' . get_option( 'custom_wpadmin_slug' ) . '/' ) {
				$_SESSION['valid_login'] = true;
			} elseif ( isset( $_SESSION['valid_login'] ) ) {
				// let them pass
			} else {
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		}

		// return parsed url
		function get_url() {
			$url = array();

			$https = isset( $_SERVER['HTTPS'] ) ? strtolower( (string) $_SERVER['HTTPS'] ) : '';
			$url['scheme']       = ( $https !== '' && $https !== 'off' ) ? 'https' : 'http';
			$url['domain']       = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
			$url['port']         = isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] !== '' ? (string) $_SERVER['SERVER_PORT'] : '';
			$blog_url            = (string) get_bloginfo( 'url' );
			$prefix              = $url['scheme'] . '://' . $url['domain'];
			$host                = explode( $prefix, $blog_url, 2 );
			$url['rewrite_base'] = ( isset( $host[1] ) && $host[1] !== '' ) ? preg_replace( '/^\//', '', $host[1] ) : '';

			$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
			$url['path'] = ( $url['rewrite_base'] !== '' ) ? implode( '', explode( '/' . $url['rewrite_base'], $script_name ) ) : $script_name;
			$url['query'] = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';

			return $url;
		}

		function check_login() {
			// just chek if we are on the right place
			$slug = get_option( 'custom_wpadmin_slug' );
			if (
				isset( $GLOBALS['pagenow'] ) &&
				in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ), true ) &&
				$slug !== false &&
				$slug !== ''
			) {

				// check if our plugin have written necesery line to .htaccess, sometimes WP doesn't write correctly so we don't want to disable login in that case
				$htaccess_path = $this->get_home_path() . '.htaccess';
				$raw           = $this->read_htaccess_file( $htaccess_path );
				$markerdata    = $raw !== '' ? explode( "\n", $raw ) : array();
				$found         = false;
				$url           = $this->get_url();
				$rewrite_chunk = ( $url['rewrite_base'] !== '' ) ? '/' . $url['rewrite_base'] : '';
				$expected      = 'RewriteRule ^' . $slug . '/?$ ' . $rewrite_chunk . '/wp-login.php [QSA,L]';

				foreach ( $markerdata as $line ) {
					if ( trim( $line ) === $expected ) {
						$found = true;
						break;
					}
				}
				if ( $found ) {
					$this->custom_login();
				}
			}
		}

		/* Taken from "/wp-admin/includes/file.php" */
		function get_home_path() {
			$home    = get_option( 'home' );
			$siteurl = get_option( 'siteurl' );
			if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
				$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
				$script_file         = isset( $_SERVER['SCRIPT_FILENAME'] ) ? str_replace( '\\', '/', (string) $_SERVER['SCRIPT_FILENAME'] ) : '';
				$needle              = trailingslashit( $wp_path_rel_to_home );
				$pos                 = $script_file !== '' ? strripos( $script_file, $needle ) : false;
				if ( false === $pos ) {
					return trailingslashit( ABSPATH );
				}
				$home_path = substr( $script_file, 0, $pos );
				return trailingslashit( $home_path );
			}
			return trailingslashit( ABSPATH );
		}
	}

	$hc_custom_wpadmin_url = new OIT_NewWPAdminURL();

	// add hooks
	add_filter( 'generate_rewrite_rules', array( $hc_custom_wpadmin_url, 'rewrite_admin_url' ) );
	add_action( 'admin_init', array( $hc_custom_wpadmin_url, 'register_permalink_settings' ), 5 );
	add_action( 'admin_init', array( $hc_custom_wpadmin_url, 'custom_admin_url' ), 10 );
	add_action( 'login_init', array( $hc_custom_wpadmin_url, 'check_login' ) );

}
