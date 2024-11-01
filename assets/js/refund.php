<?php
$woopay_plugin_name = $_REQUEST[ 'woopay_plugin_name' ];
?>
jQuery( '.<?php echo $woopay_plugin_name; ?>-cancel' ).click( function() {
	var ask_msg = confirm( jQuery( '#<?php echo $woopay_plugin_name; ?>-ask-refund-msg' ).val() );

	if ( ! ask_msg ) {
		return false;
	}
});
