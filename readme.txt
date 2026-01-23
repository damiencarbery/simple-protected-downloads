=== Simple Protected Downloads ===
Contributors: daymobrew
Tags: download, downloads, download manager
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.20260123
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Limit access to specified media files to logged in users. Very simple interface with no unnecessary features.


== Frequently Asked Questions ==
None yet.

== Changelog ==

= 0.1.20260118 =
* Initial version.

== Upgrade Notice ==
None yet.

== Screenshots ==
None yet.

== Developer information ==

The access can be changed with the '*spdownload_check_perms*' filter, returning true to allow the download.
For example:
`<?php
// If the download ID is 1 then allow the download.
add_filter( 'spdownload_check_perms', 'my_download_perms_check', 10, 2 );
function my_download_perms_check( $user_logged_in, $download_id ) {
	if ( 1 == $download_id ) { return true; }

	return $user_logged_in;
}
`

After a file has been downloaded the '*spdownload_after_download*' action runs. This could allow tracking of downloads.
For example:
`<?php
add_action( 'spdownload_after_download', 'note_downloads' );
function note_downloads( $download_id ) {
	$download_count = get_post_meta( $download_id, 'dl_count', true );
	if ( $download_count ) {
		$download_count++;
	} else {
		$download_count = 1;
	}
	update_post_meta( $download_id, 'dl_count', $download_count );
}
`
