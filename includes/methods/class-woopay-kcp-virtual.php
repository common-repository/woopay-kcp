<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPVirtual' ) ) {
	class WooPayKCPVirtual extends WooPayKCPPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'kcp_virtual';
			$this->section					= 'woopaykcpvirtual';
			$this->method 					= '001000000000';
			$this->method_title 			= __( 'KCP Virtual Account', $this->woopay_domain );
			$this->title_default 			= __( 'Virtual Account', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via virtual account.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_kcp_virtual( $methods ) {
		$methods[] = 'WooPayKCPVirtual';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_kcp_virtual' );
}