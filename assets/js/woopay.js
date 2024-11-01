var checkout_url = woopay_string.checkout_url;
var response_url = woopay_string.response_url;
var cart_url = woopay_string.cart_url;
var testmode = woopay_string.testmode;

var payForm = document.order_info;

function jsf__pay( form ) {
	try { 
		KCP_Pay_Execute( form );
	} catch (e) { 
	} 
}


function m_Completepayment( FormOrJson, closeEvent ) {
	GetField( payForm, FormOrJson );
	if ( payForm.res_cd.value == 'a3001' || payForm.res_cd.value == '3001' ) {
		alert( woopay_string.cancel_msg );
		returnToCart();
	} else {
		payForm.action = response_url;
		payForm.target = '_self';
		payForm.submit();
	}
}

function onload_pay() {
	jsf__pay( payForm );
}

function returnToCheckout() {
	payForm.action = checkout_url;
	payForm.submit();
}

function returnToCart() {
	payForm.action = cart_url;
	payForm.submit();
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			returnToCheckout();
		} else {
			setTimeout( 'onload_pay();', 500 );
		}
	} else {
		setTimeout( 'onload_pay();', 500 );
	}
}

kcpTx_install();

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 1000 );
});