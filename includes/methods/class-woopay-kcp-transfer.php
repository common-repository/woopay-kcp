<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPTransfer' ) ) {
	class WooPayKCPTransfer extends WooPayKCPPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'kcp_transfer';
			$this->section					= 'woopaykcptransfer';
			$this->method 					= '010000000000';
			$this->method_title 			= __( 'KCP Account Transfer', $this->woopay_domain );
			$this->title_default 			= __( 'Account Transfer', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via account transfer.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_kcp_transfer( $methods ) {
		$methods[] = 'WooPayKCPTransfer';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_kcp_transfer' );
}