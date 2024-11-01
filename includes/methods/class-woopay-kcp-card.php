<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPCard' ) ) {
	class WooPayKCPCard extends WooPayKCPPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'kcp_card';
			$this->section					= 'woopaykcpcard';
			$this->method 					= '100000000000';
			$this->method_title 			= __( 'KCP Credit Card', $this->woopay_domain );
			$this->title_default 			= __( 'Credit Card', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via credit card.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'card';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_kcp_card( $methods ) {
		$methods[] = 'WooPayKCPCard';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_kcp_card' );
}