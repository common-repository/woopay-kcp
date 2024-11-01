var checkout_url = woopay_string.checkout_url;
var response_url = woopay_string.response_url;
var cart_url = woopay_string.cart_url;
var testmode = woopay_string.testmode;

var payForm = document.order_info;

function call_pay_form() {
	document.getElementById( 'layer_all' ).style.display = 'block';
	document.getElementById( 'kcp_background' ).style.display = 'block';

	payForm.target = 'frm_all';
	payForm.action = PayUrl;

	if ( payForm.encoding_trans.value == 'UTF-8' ) {
		payForm.action = PayUrl.substring( 0, PayUrl.lastIndexOf( '/' ) ) + '/jsp/encodingFilter/encodingFilter.jsp';
		payForm.PayUrl.value = PayUrl;
	} else {
		payForm.action = PayUrl;
	}

	if ( payForm.Ret_URL.value == '' ) {
		alert( woopay_string.returl_msg );
		return false;
	} else {
		payForm.submit();
		jQuery( 'html,body' ).scrollTop(1);
	}
}

function returnToCheckout() {
	payForm.action = checkout_url;
	payForm.target = '_self';
	payForm.submit();
}

function changeBackground() {
	var dHeight = jQuery( 'body' ).height();

	jQuery( '#kcp_background' ).css( 'height', dHeight + 'px' );
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			returnToCheckout();
		}
	}

	self.name = 'tar_opener';
	kcp_AJAX();
	changeBackground();
}

(function($,sr){
	var debounce = function (func, threshold, execAsap) {
		var timeout;
		return function debounced () {
			var obj = this, args = arguments;
			function delayed () {
				if (!execAsap)
					func.apply(obj, args);
				timeout = null;
			};
			if (timeout)
				clearTimeout(timeout);
			else if (execAsap)
				func.apply(obj, args);
			timeout = setTimeout(delayed, threshold || 100);
		};
	}
	jQuery.fn[sr] = function(fn){  return fn ? this.bind('resize', debounce(fn)) : this.trigger(sr); };
})(jQuery,'smartresize');

jQuery( window ).smartresize( function() {
	changeBackground();
});

jQuery( document ).ready(function() {
	jQuery( 'body' ).append( "<div id='kcp_background'></div><div id='layer_all' style='position:absolute; left:0px; top:0px; width:100%;height:100%; z-index:99999; display:none;'><iframe name='frm_all' frameborder='0' marginheight='0' marginwidth='0' border='0' width='100%' height='100%' scrolling='auto'></iframe></div>" );
	setTimeout( 'startWooPay();', 500 );
});