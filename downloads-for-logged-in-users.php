<?php
/*
Plugin Name: Downloads for logged in users
Description: Limit access to specified media files to logged in users. Very simple interface with no unnecessary features.
Plugin URI: https://www.damiencarbery.com/downloads-for-logged-in-users/
Author: Damien Carbery
Author URI: https://www.damiencarbery.com
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: downloads-for-logged-in-users
Domain Path: /languages
Version: 0.6.20260128
*/

defined( 'ABSPATH' ) || exit;


// Force the rewrite rules to be regenerated after this plugin is actived.
// Suggested at: https://developer.wordpress.org/reference/functions/flush_rewrite_rules/#comment-2645
register_activation_hook( __FILE__, 'DownloadsForLoggedInUsers_activate' );
register_deactivation_hook( __FILE__, 'DownloadsForLoggedInUsers_activate' );
function DownloadsForLoggedInUsers_activate() {
	// Force rewrite rules to be recreated at the right time
	delete_option( 'rewrite_rules' );
}


class DownloadsForLoggedInUsers {
	private $cpt_name;
	private $meta_key;
	private $download_url;
	private $link_col;
	private $uploads_dir;


	// Returns an instance of this class. 
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		} 
		return self::$instance;
	}


	// Initialize the plugin variables.
	public function __construct() {
		$this->cpt_name = 'dcwd_simple_download';
		$this->meta_key = 'protected_file';
		$this->download_url = 'liudownload';
		$this->link_col = 'liu_link';

		$this->init();
	}


	// Set up WordPress specfic actions.
	public function init() {
		// Register the CPT.
		add_action( 'init', array( $this, 'register_cpt' ), 0 );
		// Register the download url.
		add_action( 'init', array( $this, 'register_download_endpoint' ) );


		// Download the requested file.
		add_action( 'template_include', array( $this, 'download_file' ) );

		// Enable file uploads on the Edit Download admin page.
		add_action( 'post_edit_form_tag', array( $this, 'enable_upload_support' ) );

		// Save the data submitted through the metabox.
		add_action( 'save_post_' . $this->cpt_name, array( $this, 'save_file_metabox' ) );

		// Show the download link in the CPT list table.
		add_filter( 'manage_' . $this->cpt_name . '_posts_columns', array( $this, 'add_file_column' ) );
		add_action( 'manage_' . $this->cpt_name . '_posts_custom_column', array( $this, 'display_file_column' ), 10, 2 );
		// Add the JS to allow copying of the download url to the clipboard.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_download_url_copying_js' ));

		// Delete the uploaded file when a Download post is deleted.
		add_action( 'before_delete_post', array( $this, 'delete_post' ), 10, 2 );
	}


	private function get_uploads_dir() {
		if ( empty( $this->uploads_dir ) ) {
			$this->uploads_dir = sprintf( '%s/%s/', wp_upload_dir( null, false )['basedir'], 'downloads-for-logged-in-users' );
		}

		return $this->uploads_dir;
	}


	private function get_download_url( $post_id ) {
		return wp_sprintf( '%s/%d/', get_home_url( null, $this->download_url ), $post_id );
	}


	private function verify_uploads_dir_ready() {
		if ( !file_exists( $this->get_uploads_dir() ) ) {
			wp_mkdir_p( $this->get_uploads_dir() );
		}

		// Add .htaccess for protection.
		$htaccess_file = $this->get_uploads_dir() . '/.htaccess';
		if ( !file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, 'deny from all' );
		}
		// Add empty index.html for protection.
		$index_file = $this->get_uploads_dir() . '/index.html';
		if ( !file_exists( $index_file ) ) {
			file_put_contents( $index_file, '' );
		}
	}


	// Register the Download custom post type.
	public function register_cpt() {
		$labels = array(
			'name'                  => _x( 'Downloads', 'Post Type General Name', 'downloads-for-logged-in-users' ),
			'singular_name'         => _x( 'Download', 'Post Type Singular Name', 'downloads-for-logged-in-users' ),
			'menu_name'             => __( 'Downloads', 'downloads-for-logged-in-users' ),
			'name_admin_bar'        => __( 'Download', 'downloads-for-logged-in-users' ),
			'all_items'             => __( 'All downloads', 'downloads-for-logged-in-users' ),
			'add_new_item'          => __( 'Add new download', 'downloads-for-logged-in-users' ),
			'not_found'             => __( 'Not found', 'downloads-for-logged-in-users' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'downloads-for-logged-in-users' ),
		);
		$args = array(
			'label'                 => __( 'Download', 'downloads-for-logged-in-users' ),
			'description'           => __( 'Protected downloads', 'downloads-for-logged-in-users' ),
			'labels'                => $labels,
			'supports'              => array( 'title' ),
			// ToDo: Decide whether to create custom categories.
			'taxonomies'            => array( 'category' ),
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-download',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'page',
			'show_in_rest'          => false,
			'register_meta_box_cb'  => array( $this, 'add_file_metabox' ),
		);
		register_post_type( $this->cpt_name, $args );
	}


	public function register_download_endpoint() {
		add_rewrite_endpoint( $this->download_url, EP_ROOT );
	}


	// Delete the uploaded file when A Download post is deleted.
	public function delete_post( $post_id, $post ) {
		$file_name = get_post_meta( $post_id, $this->meta_key, true );
		if ( $file_name ) {
			$file_path = $this->get_uploads_dir() . $file_name;
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
	}


	public function download_file( $template ) {
		global $wp_query;

		if ( isset( $wp_query->query_vars[ $this->download_url ] ) ) {
			$post_id = intval( $wp_query->query_vars[ $this->download_url ] );
			if ( $post_id ) {
				// Ensure that the user is logged in before allowing the download.
				$user_logged_in = is_user_logged_in();
				// Developers can use the filter to change the access permissions.
				$user_can_download = apply_filters( 'spdownload_check_perms', $user_logged_in, $post_id );

				if ( $user_can_download ) {
					$file_name = get_post_meta( $post_id, $this->meta_key, true );
					if ( $file_name ) {
						$file_path = $this->get_uploads_dir() . $file_name;
						if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
							header( 'X-Robots-Tag: noindex, nofollow', true );
							header( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private' );
							header( 'Content-Description: File Transfer', false );
							header( 'Content-Disposition: attachment; filename="' . $file_name . '";', false );
							header( 'Content-Transfer-Encoding: binary' );
							header( 'Keep-Alive: timeout=5, max=100' );
							header( 'Connection: Keep-Alive' );
							header( 'Content-Transfer-Encoding: binary' );
							$mime_type = mime_content_type( $file_path );
							if ( $mime_type ) {
								header( 'Content-Type: '. $mime_type );
							}

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo file_get_contents( $file_path );

							do_action( 'spdownload_after_download', $post_id );

							exit;
						}
					}
				}
				else {
					add_action( 'wp_footer', array( $this, 'download_not_permitted_message' ) );
				}
			}
		}

		// Nothing matched our endpoint.
		return $template;
	}


	// Display a JavaScript alert() when the user is not allowed to download the file.
	// This could be triggered if the file does not exist but it is easier to have a generic message.
	public function download_not_permitted_message() {
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename( __FILE__ ) );
		$handle = $plugin_data['TextDomain'];
		wp_register_script( $handle, '', null, $plugin_data['Version'], true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, "window.addEventListener('DOMContentLoaded', function() {
		alert( \"" . esc_html__( 'You do not have permission to download that file.', 'downloads-for-logged-in-users' ) . "\" );
		});"
		);
	}


	// Add metabox for file upload to 'dcwd_simple_download' custom post type
	public function add_file_metabox() {
		add_meta_box(
			'dcwdspd_file_upload',
			__( 'Protected File', 'downloads-for-logged-in-users' ),
			array( $this, 'file_metabox_callback' ),
			$this->cpt_name,
			'normal',
			'high'
		);
	}


	public function enable_upload_support( $post ) {
		if ( $post->post_type === $this->cpt_name ) {
			echo ' enctype="multipart/form-data"';
		}
	}


	// Metabox callback function
	function file_metabox_callback( $post ) {
		wp_nonce_field( 'dcwdspd_file_save', 'dcwdspd_file_nonce' );

		$file_path = get_post_meta( $post->ID, $this->meta_key, true );

		?>
		<div class="dcwdspd-file-upload">
			<p>
				<input type="file" name="dcwdspd_file" id="dcwdspd_file" />
			</p>

			<?php if ( $file_path ) { ?>
				<p>
					<strong>Current file:</strong> <?php echo esc_html( basename( $file_path ) ); ?>
					<br>
					<label>
						<input type="checkbox" name="dcwdspd_remove_file" value="1" />
						<?php echo esc_html__( 'Remove current file', 'downloads-for-logged-in-users' ); ?>
					</label>
				</p>
			<?php } else { ?>
				<p><em><?php echo esc_html__( 'No file uploaded yet.', 'downloads-for-logged-in-users' ); ?></em></p>
			<?php } ?>
		</div>
		<?php
	}


	// Save metabox data
	function save_file_metabox( $post_id ) {
		// Check nonce
		if ( !isset( $_POST['dcwdspd_file_nonce'] ) ||
			!wp_verify_nonce( sanitize_key( $_POST['dcwdspd_file_nonce'] ), 'dcwdspd_file_save' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Handle file removal
		if ( isset( $_POST['dcwdspd_remove_file'] ) ) {
			$old_file = get_post_meta( $post_id, $this->meta_key, true );
			if ( $old_file && file_exists( $this->get_uploads_dir() . $old_file ) ) {
				wp_delete_file( $this->get_uploads_dir() . $old_file );
			}
			delete_post_meta( $post_id, $this->meta_key );
			return;
		}

		// Handle file upload
		if ( isset( $_FILES['dcwdspd_file'] ) && isset( $_FILES['dcwdspd_file']['error'] ) && $_FILES['dcwdspd_file']['error'] == 0 ) {
			// Verify that the uploads dir is available.
			$this->verify_uploads_dir_ready();

			// Delete old file if exists
			$old_file = get_post_meta( $post_id, $this->meta_key, true );
			if ( $old_file && file_exists( $this->get_uploads_dir() . $old_file ) ) {
				wp_delete_file( $this->get_uploads_dir() . $old_file );
			}

			if ( isset( $_FILES['dcwdspd_file'] ) ) {
				// Change the upload dir to be the custom dir.
				add_filter( 'upload_dir', array( $this, 'change_upload_dir' ) );

				$movefile = wp_handle_upload( $_FILES['dcwdspd_file'], array( 'test_form' => false ) );

				// Remove the filter so other uploads are not redirected.
				remove_filter( 'upload_dir', array( $this, 'change_upload_dir' ) );

				if ( $movefile && ! isset( $movefile['error'] ) ) {
					update_post_meta( $post_id, $this->meta_key, basename( $movefile['file'] ) );
				}
			}
		}
	}


	// Change the upload dir to be the custom dir.
	public function change_upload_dir( $dirs ) {
		$dirs['path'] = $this->get_uploads_dir();

		return $dirs;
	}


	// Add custom column to dcwd_simple_download post type admin list
	function add_file_column( $columns ) {
		// Insert the File column after the title
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[$key] = $value;
			if ( $key === 'title' ) {
				$new_columns[ $this->link_col ] = __( 'Download link', 'downloads-for-logged-in-users' );
				$new_columns[ $this->meta_key ] = __( 'File', 'downloads-for-logged-in-users' );
			}
		}
		return $new_columns;
	}

	// Display the Download URL and File column content.
	function display_file_column( $column, $post_id ) {
		if ( $column === $this->meta_key || $column == $this->link_col ) {
			$file_name = get_post_meta( $post_id, $this->meta_key, true );

			if ( $file_name ) {
				$file_path = $this->get_uploads_dir() . $file_name;
				if ( file_exists( $file_path ) ) {
					
					if ( $column === $this->meta_key ) {
						$file_size = size_format( filesize( $file_path ) );
						echo wp_sprintf( '%s<br><small>(%s)</small>', esc_html( $file_name), esc_html( $file_size ) );
						return;
					}
					if ( $column == $this->link_col ) {
						echo wp_sprintf( '<div class="spd-copy-url dashicons dashicons-admin-links" data-spd_url="%s" title="%s"></div>',
						esc_attr( $this->get_download_url( $post_id ) ), esc_html__( 'Click to copy the download url.', 'downloads-for-logged-in-users' ) );
						return;
					}
				}
			}
			echo '<span class="spd-no-file">—</span>';
		}
	}


	// Add CSS for the Download link and JS to copy it to the clipboard.
	function add_download_url_copying_js() {
		$screen = get_current_screen();
		if ( 'edit-dcwd_simple_download' == $screen->id ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename(__FILE__) );

			wp_enqueue_style( 'downloads-for-logged-in-users', plugins_url( 'assets/downloads-for-logged-in-users.css', __FILE__ ), null, $plugin_data['Version'] );
			wp_enqueue_script( 'downloads-for-logged-in-users', plugins_url( 'assets/downloads-for-logged-in-users.js', __FILE__ ), array( 'jquery' ), $plugin_data['Version'], array( 'in_footer' => true ) );
		}
	}
}

//WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$DownloadsForLoggedInUsers = new DownloadsForLoggedInUsers();
