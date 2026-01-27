=== Downloads for logged in users ===
Contributors: daymobrew
Tags: download, downloads, download manager
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.5.20260127
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Limit access to specified media files to logged in users. Very simple interface with no unnecessary features.

Create a new 'Download' post, give it a title and upload a file for it. Files are stored in a protected directory under wp-content/uploads. This prevents anyone from accessing a file, even if they have the url.
Access is via a custom download url that can be copied from the plugin's admin screen.

Downloads can be assigned categories though these are for site admin organisation use and are not used in the custom url.

The download url is not the url of the file; it is a custom url with the post ID. This allows you update the downloadable file without having to change the download url.

The plugin is enabled for translation.

If you find a bug or have a feature request, please report it via the plugin's [GitHub repository](https://github.com/damiencarbery/downloads-for-logged-in-users).

== Frequently Asked Questions ==
None yet.

== Changelog ==

= 0.5.20260127 =
* Change upload dir to uploads/downloads-for-logged-in-users.

= 0.4.20260126 =
* Rename files, text domain to use new slug, 'downloads-for-logged-in-users', as requested during WordPress Plugin review.

= 0.3.20260125 =
* Change plugin name to "Downloads for logged in users" as requested during WordPress Plugin review.

= 0.2.20260118 =
* Move CSS and JS into separate files and load with wp_enqueue_script/style.

= 0.1.20260118 =
* Initial version.

== Upgrade Notice ==
None yet.

== Screenshots ==
1. The Downloads admin page allows you click to copy the custom download url.
2. Edit a download to change the uploaded file and/or the download title.
3. Create a new download.
4. A JavaScript alert is shown when the user is not logged in.

== Developer information ==

The access can be changed with the '*liudownload_check_perms*' filter, returning true to allow the download.
For example:
	<?php
	// If the download ID is 1 then allow the download.
	add_filter( 'liudownload_check_perms', 'my_download_perms_check', 10, 2 );
	function my_download_perms_check( $user_logged_in, $download_id ) {
		if ( 1 == $download_id ) { return true; }

		return $user_logged_in;
	}


After a file has been downloaded the '*liudownload_after_download*' action runs. This could allow tracking of downloads.
For example:
	<?php
	add_action( 'sliudownload_after_download', 'note_downloads' );
	function note_downloads( $download_id ) {
		$download_count = get_post_meta( $download_id, 'dl_count', true );
		if ( $download_count ) {
			$download_count++;
		} else {
			$download_count = 1;
		}
		update_post_meta( $download_id, 'dl_count', $download_count );
	}

