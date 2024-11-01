var checkout_url = woopay_string.checkout_url;
var response_url = woopay_string.response_url;
var cart_url = woopay_string.cart_url;
var testmode = woopay_string.testmode;

var payForm = document.order_info;

function call_pay_form() {
	//document.getElementById( 'layer_all' ).style.display  = 'block';

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

function fluidDialog() {
    var $visible = jQuery( '.ui-dialog:visible' );

    $visible.each( function () {
        var $this = jQuery( this );
        var dialog = $this.find( '.ui-dialog-content' ).data( 'ui-dialog' );

		if ( dialog.options.fluid ) {
            var wWidth = jQuery( window ).width();
            var hHeight = jQuery( window ).height();

			//if ( wWidth < ( parseInt( dialog.options.maxWidth ) + 200 ) )  {
                $this.css( 'max-width', '100%' );
				$this.css( 'max-height', '100%' );

				$this.css( 'width', '100%' );
				$this.css( 'height', '100%' );
				jQuery( '#frm_all' ).css( 'height', '100%' );
				jQuery( '#KCPPaymentWindow' ).css( 'height', hHeight - 56 + 'px' );
				jQuery( '#KCPPaymentWindow' ).css( 'overflow-y', 'hidden' );
				jQuery( '#wpadminbar' ).css( 'display', 'none' );
				jQuery( '#frm_all' ).css( 'min-height', '650px' );
            /*} else {
				$this.css( 'height', dialog.options.maxHeight + 'px' );
				$this.css( 'width', dialog.options.maxWidth + 'px' );
                $this.css( 'max-width', dialog.options.maxWidth + 'px' );
				jQuery( '#KCPPaymentWindow' ).css( 'height', '650px' );
				jQuery( '#wpadminbar' ).css( 'display', '' );
            }*/
            dialog.option( 'position', dialog.options.position );
        }
    });
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
	fluidDialog();
});

jQuery( document ).on( 'dialogopen', '.ui-dialog', function ( event, ui ) {
	fluidDialog();
	jQuery( 'body' ).addClass( 'p8-hide-body-scroll' );
});

jQuery( document ).on( 'dialogclose', function ( event, ui ) {
	jQuery( 'body' ).removeClass( 'p8-hide-body-scroll' );
	jQuery( '#wpadminbar' ).css( 'display', '' );
	alert( woopay_string.cancel_msg );
	returnToCheckout();
});

var scrollHandler = function() {
	//jQuery( 'body' ).scrollTop( scrolltop );
}

function doIframe() {
	jQuery( 'body' ).prepend( '<div id="KCPPaymentWindow" name="KCPPaymentWindow" style="display:none;"><iframe id="frm_all" name="frm_all" width="100%" height="650" frameborder="0"></iframe></div>' );

	jQuery( '#KCPPaymentWindow' ).dialog({
		autoOpen: false,
		autoResize: true,
		modal: true,
		fluid: true,
		resizable: false,
		draggable: false,
		height: 500,
		maxHeight: 500,
		width: 'auto',
		maxWidth: 680,
		title: woopay_string.payment_title,
	});

	jQuery( '#KCPPaymentWindow' ).dialog( 'open' );

	jQuery( '#KCPPaymentWindow' ).css( 'position', 'absolute' );

	jQuery( '.ui-dialog-titlebar-close' ).blur();

	self.name = 'tar_opener';
	kcp_AJAX();
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			payForm.action = checkoutURL;
			payForm.submit();
			return false;
		}
	}

	doIframe();
}

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 500 );
});