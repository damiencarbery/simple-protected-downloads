jQuery(document).ready(function( $ ) {
	// Add a class and remove it a few seconds later.
	classToAdd = 'info-copied';
	timeoutDelay = '1000';  // 1000 milliseconds == 1 second.

	$( '.spd-copy-url' ).on( 'click', function() {
		spDownloadIcon = $(this);
		spDownloadUrl = spDownloadIcon.data('spd_url');

		// Add the url to the clipboard.
		navigator.clipboard.writeText(spDownloadUrl).then(function() {
			spDownloadIcon.addClass( classToAdd );
			setTimeout(() => { spDownloadIcon.removeClass( classToAdd ); }, timeoutDelay )
		}, function(err) {
			console.error('Async: Could not copy text: ', err);
		});
	});
});