<?php
/*
 * Plugin Name: Simple Protected Downloads
 * Description: Limit access to specified media files to logged in users. Very simple interface with no unnecessary features.
 * Plugin URI: https://www.damiencarbery.com/
 * Update URI: https://www.damiencarbery.com/
 * Author: Damien Carbery
 * Author URI: https://www.damiencarbery.com
 * Text Domain: simple_protected_downloads
 * Version: 0.1.20260109
 */

defined( 'ABSPATH' ) || exit;



/*
register_activation_hook( __FILE__, 'activate' );

function activate() {
    //this function will be called at activation, and then on every init as well:
    SimpleProtectedDownloads::register_download_endpoint();

    //run this at activation ONLY, AFTER setting the endpoint - needed for your endpoint to actually create a query_vars entry:
    flush_rewrite_rules();
}


register_deactivation_hook( __FILE__, 'deactivate' );
function deactivate() {
    flush_rewrite_rules();
}*/


class SimpleProtectedDownloads {
	private $cpt_name = 'dcwd_simple_download';
	private $meta_key = 'protected_file';
	private $download_url = 'spdownload';
	private $link_col = 'spd_link';
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

		// Add metabox to the CPT.
		add_action( 'add_meta_boxes', array( $this, 'add_file_metabox' ) );
		// Enable file uploads on the Edit Download admin page.
		add_action( 'post_edit_form_tag', array( $this, 'enable_upload_support' ) );
		//add_action( 'wp_enqueue_scripts', array( $this, 'file_upload_scripts' ) );

		// Save the data submitted through the metabox.
		add_action( 'save_post_' . $this->cpt_name, array( $this, 'save_file_metabox' ) );

		// Show the download link in the CPT list table.
		add_filter( 'manage_' . $this->cpt_name . '_posts_columns', array( $this, 'add_file_column' ) );
		add_action( 'manage_' . $this->cpt_name . '_posts_custom_column', array( $this, 'display_file_column' ), 10, 2 );
		// Add the JS to allow copying of the download url to the clipboard.
		add_action( 'in_admin_footer', array( $this, 'add_download_url_copying_js' ));
//add_filter( 'manage_edit-' . $this->cpt_name . '_sortable_columns', array( $this, 'sortable_file_column' ) );
//add_action( 'pre_get_posts', array( $this, 'file_column_orderby' ) );

// ToDo: Add code to delete the uploaded file when a post is deleted.
	}

	private function get_uploads_dir() {
		if ( empty( $this->uploads_dir ) ) {
			$this->uploads_dir = wp_get_upload_dir()['basedir']  . '/woocommerce_uploads/';
		}

		return $this->uploads_dir;
	}


	private function get_download_url( $post_id ) {
		return wp_sprintf( '%s/%d/', get_home_url( null, $this->download_url ), $post_id );
	}


	// Register the Download custom post type.
	public function register_cpt() {
// ToDo: Review all these labels - can some be removed?
		$labels = array(
			'name'                  => _x( 'Downloads', 'Post Type General Name', 'simple_protected_downloads' ),
			'singular_name'         => _x( 'Download', 'Post Type Singular Name', 'simple_protected_downloads' ),
			'menu_name'             => __( 'Downloads', 'simple_protected_downloads' ),
			'name_admin_bar'        => __( 'Download', 'simple_protected_downloads' ),
			'archives'              => __( 'Download Archives', 'simple_protected_downloads' ),
			'attributes'            => __( 'Download Attributes', 'simple_protected_downloads' ),
			'parent_item_colon'     => __( 'Parent download:', 'simple_protected_downloads' ),
			'all_items'             => __( 'All downloads', 'simple_protected_downloads' ),
			'add_new_item'          => __( 'Add new download', 'simple_protected_downloads' ),
			'add_new'               => __( 'Add download', 'simple_protected_downloads' ),
			'new_item'              => __( 'New download', 'simple_protected_downloads' ),
			'edit_item'             => __( 'Edit download', 'simple_protected_downloads' ),
			'update_item'           => __( 'Update download', 'simple_protected_downloads' ),
			'view_item'             => __( 'View download', 'simple_protected_downloads' ),
			'view_items'            => __( 'View downloads', 'simple_protected_downloads' ),
			'search_items'          => __( 'Search download', 'simple_protected_downloads' ),
			'not_found'             => __( 'Not found', 'simple_protected_downloads' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'simple_protected_downloads' ),
			//'featured_image'        => __( 'Featured Image', 'simple_protected_downloads' ),
			//'set_featured_image'    => __( 'Set featured image', 'simple_protected_downloads' ),
			//'remove_featured_image' => __( 'Remove featured image', 'simple_protected_downloads' ),
			//'use_featured_image'    => __( 'Use as featured image', 'simple_protected_downloads' ),
			'insert_into_item'      => __( 'Insert into item', 'simple_protected_downloads' ),
			'uploaded_to_this_item' => __( 'Uploaded to this download', 'simple_protected_downloads' ),
			'items_list'            => __( 'Downloads list', 'simple_protected_downloads' ),
			'items_list_navigation' => __( 'Downloads list navigation', 'simple_protected_downloads' ),
			'filter_items_list'     => __( 'Filter downloads list', 'simple_protected_downloads' ),
		);
		$args = array(
			'label'                 => __( 'Download', 'simple_protected_downloads' ),
			'description'           => __( 'Protected downloads', 'simple_protected_downloads' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'custom-fields' ),
			'taxonomies'            => array( 'category' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-download',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'page',
			'show_in_rest'          => false,
		);
		register_post_type( $this->cpt_name, $args );
	}


	public function register_download_endpoint() {
		add_rewrite_endpoint( $this->download_url, EP_ROOT );
	}


	public function download_file( $template ) {
		global $wp_query;

		if (isset($wp_query->query_vars[$this->download_url])) {
			$post_id = intval( $wp_query->query_vars[ $this->download_url ] );
			if ( $post_id ) {
				$file_name = get_post_meta( $post_id, $this->meta_key, true );
				if ( $file_name ) {
error_log( 'download_file $file_name: ' . $file_name );
					$file_path = $this->get_uploads_dir() . $file_name;
					if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
error_log( 'download_file $file_path: ' . $file_path );
						header( 'X-Robots-Tag: noindex, nofollow', true );
						//header( 'Content-Type: ' . self::get_download_content_type( $file_path ) );
						header( 'Content-Type: image/png' );
						header( 'Content-Description: File Transfer' );
						//header( 'Content-Disposition: ' . self::get_content_disposition() . '; filename="' . $file_name . '";' );
						header( 'Content-Disposition: attachment; filename="' . $file_name . '";' );
						header( 'Content-Transfer-Encoding: binary' );

						echo file_get_contents( $file_path );
						wp_die();
					}
				}
			//$output = 'Accessed download url: ' . $wp_query->query_vars[ $this->download_url ];
			//echo $output;
			}
			exit;
		}

    // Nothing matched our endpoint.
    return $template;
}


	// Add metabox for file upload to 'dcwd_simple_download' custom post type
	public function add_file_metabox() {
		add_meta_box(
			'dcwdspd_file_upload',
			__( 'Protected File', 'simple_protected_downloads' ),
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
						<?php echo __( 'Remove current file', 'simple_protected_downloads' ); ?>
					</label>
				</p>
			<?php } else { ?>
				<p><em><?php echo __( 'No file uploaded yet.', 'simple_protected_downloads' ); ?></em></p>
			<?php } ?>
		</div>
		<?php
	}

	// Save metabox data
	function save_file_metabox( $post_id ) {
		// Check nonce
		if ( !isset( $_POST['dcwdspd_file_nonce'] ) ||
			!wp_verify_nonce( $_POST['dcwdspd_file_nonce'], 'dcwdspd_file_save' ) ) {
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
				unlink( $this->get_uploads_dir() . $old_file );
			}
			delete_post_meta( $post_id, $this->meta_key );
			return;
		}

		// Handle file upload
		if ( isset( $_FILES['dcwdspd_file'] ) && $_FILES['dcwdspd_file']['error'] == 0 ) {
// ToDo: Move the dir and .htaccess creation to a separate function that can be called on plugin initialisation.
			// Create upload directory if it doesn't exist
			if ( !file_exists( $this->get_uploads_dir() ) ) {
				wp_mkdir_p( $this->get_uploads_dir() );
			}

			// Add .htaccess for protection
			$htaccess_file = $this->get_uploads_dir() . '/.htaccess';
			if ( !file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, 'deny from all' );
			}

			// Delete old file if exists
			$old_file = get_post_meta( $post_id, $this->meta_key, true );
			if ( $old_file && file_exists( $this->get_uploads_dir() . $old_file ) ) {
				unlink( $this->get_uploads_dir() . $old_file );
			}

			// Generate unique filename
			$file = $_FILES['dcwdspd_file'];
			$file_name = wp_unique_filename( $this->get_uploads_dir(), $file[ 'name' ] );
			$file_path = $this->get_uploads_dir() . $file_name;

			// Move uploaded file
			if ( move_uploaded_file( $file['tmp_name'], $file_path ) ) {
				update_post_meta( $post_id, $this->meta_key, $file_name );
			}
		}
	}


	public function file_upload_scripts() {
		$version = null; // Could use plugin version.
		// ToDo: Limit to a specific screen.
		// if ( in_array( $screen_id, array( 'product', 'edit-product' ) ) )
		//wp_enqueue_script( 'spd-file-upload', __DIR__ . '/spd-file-upload.js', array( 'wc-admin-meta-boxes', 'media-models' ), $version );
// See woocommerce/assets/js/admin/meta-boxes-product.js
/*
	// Uploading files.
	var downloadable_file_frame;
	var file_path_field;

	$( document.body ).on( 'click', '.upload_file_button', function ( event ) {
		var $el = $( this );

		file_path_field = $el.closest( 'tr' ).find( 'td.file_url input' );

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( downloadable_file_frame ) {
			downloadable_file_frame.open();
			return;
		}

		var downloadable_file_states = [
			// Main states.
			new wp.media.controller.Library( {
				library: wp.media.query(),
				multiple: true,
				title: $el.data( 'choose' ),
				priority: 20,
				filterable: 'uploaded',
			} ),
		];

		// Create the media frame.
		downloadable_file_frame = wp.media.frames.downloadable_file = wp.media(
			{
				// Set the title of the modal.
				title: $el.data( 'choose' ),
				library: {
					type: '',
				},
				button: {
					text: $el.data( 'update' ),
				},
				multiple: true,
				states: downloadable_file_states,
			}
		);

		// When an image is selected, run a callback.
		downloadable_file_frame.on( 'select', function () {
			var file_path = '';
			var selection = downloadable_file_frame.state().get( 'selection' );

			selection.map( function ( attachment ) {
				attachment = attachment.toJSON();
				if ( attachment.url ) {
					file_path = attachment.url;
				}
			} );

			file_path_field.val( file_path ).trigger( 'change' );
		} );

		// Set post to 0 and set our custom type.
		downloadable_file_frame.on( 'ready', function () {
			downloadable_file_frame.uploader.options.uploader.params = {
				type: 'downloadable_product',
			};
		} );

		// Finally, open the modal.
		downloadable_file_frame.open();
	} );


*/
	}


	// Add custom column to dcwd_simple_download post type admin list
	function add_file_column( $columns ) {
		// Insert the File column after the title
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[$key] = $value;
			if ( $key === 'title' ) {
				$new_columns[ $this->link_col ] = __( 'Download link', 'simple_protected_downloads' );
				$new_columns[ $this->meta_key ] = __( 'File', 'simple_protected_downloads' );
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
						echo wp_sprintf( '%s<br><small style="color: #646970;">(%s)</small>', esc_html( $file_name), esc_html( $file_size ) );
						return;
					}
					if ( $column == $this->link_col ) {
						echo wp_sprintf( '<div class="spd-copy-url dashicons dashicons-admin-links" data-spd_url="%s" title="%s"></div>',
						$this->get_download_url( $post_id ), __( 'Click to copy the download url.', 'simple_protected_downloads' ) );
						return;
					}
				}
			}
			echo '<span style="color: #dba617;">—</span>';
		}
	}


	// Add CSS for the Download link and JS to copy it to the clipboard.
	function add_download_url_copying_js() {
		$screen = get_current_screen();
		if ( 'edit-dcwd_simple_download' == $screen->id ) {
?>
<style>
.spd-copy-url { background-color: #2271b1; border-radius: 5px; color: #fff; cursor: pointer; padding: 5px; width: 100px; transition: all linear 0.3s; }
.spd-copy-url.info-copied { background-color: green !important; scale: 1.1 !important; }
.spd-copy-url.info-copied:after { content: 'Copied'; display: block; color: green; padding-top: 0.5em; font-size: 70%; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; }
</style>
<script>
jQuery(document).ready(function( $ ) {
	// Add a class and remove it a few seconds later.
	classToAdd = 'info-copied';
	timeoutDelay = '3000';

	$( '.spd-copy-url' ).on( 'click', function() {
		spDownloadIcon = $(this);
		spDownloadUrl = spDownloadIcon.data('spd_url');
		//console.log( 'Download url', spDownloadUrl );

		// Add the url to the clipboard.
		navigator.clipboard.writeText(spDownloadUrl).then(function() {
			spDownloadIcon.addClass( classToAdd );
			setTimeout(() => { spDownloadIcon.removeClass( classToAdd ); }, timeoutDelay )
		}, function(err) {
			console.error('Async: Could not copy text: ', err);
		});
	});
});
</script>
<?php
		}
	}

/**
 * Make the file column sortable (optional)
 */
/*function sortable_file_column( $columns ) {
	$columns[ $this->meta_key ] = $this->meta_key;
	return $columns;
}*/

/**
 * Handle sorting by file column (optional)
 */
/*function file_column_orderby( $query ) {
	if ( !is_admin() || !$query->is_main_query() ) {
		return;
	}
   
	if ( $this->meta_key === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', $this->meta_key );
		$query->set( 'orderby', 'meta_value' );
	}
}*/
}
$SimpleProtectedDownloads = new SimpleProtectedDownloads;


/*
WooCommerce Products page: Add 'Import' and 'Export' to right of 'Add new product' button.

( function ( $, woocommerce_admin ) {
	$( function () {
		if ( 'undefined' === typeof woocommerce_admin ) {
			return;
		}

		// Add buttons to product screen.
		var $product_screen = $( '.edit-php.post-type-product' ),
			$title_action = $product_screen.find( '.page-title-action:first' ),
			$blankslate = $product_screen.find( '.woocommerce-BlankState' );

		if ( 0 === $blankslate.length ) {
			if ( woocommerce_admin.urls.add_product ) {
				$title_action
					.first()
					.attr( 'href', woocommerce_admin.urls.add_product );
			}
			if ( woocommerce_admin.urls.export_products ) {
				const exportLink = document.createElement('a');
				exportLink.href = woocommerce_admin.urls.export_products;
				exportLink.className = 'page-title-action';
				exportLink.textContent = woocommerce_admin.strings.export_products;

				$title_action.after(exportLink);
			}
			if ( woocommerce_admin.urls.import_products ) {
				const importLink = document.createElement('a');
				importLink.href = woocommerce_admin.urls.import_products;
				importLink.className = 'page-title-action';
				importLink.textContent = woocommerce_admin.strings.import_products;

				$title_action.after(importLink);
			}
		} else {
			$title_action.hide();
		}



*/

/*
add_action( 'quick_edit_custom_box', array( $this, 'quick_edit' ), 10, 2 );
woocommerce/includes/admin/class-wc-admin-post-types.php - to add extra markup to Quick Edit.
*/