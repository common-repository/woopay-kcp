<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPMobile' ) ) {
	class WooPayKCPMobile extends WooPayKCPPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'kcp_mobile';
			$this->section					= 'woopaykcpmobile';
			$this->method 					= '000010000000';
			$this->method_title 			= __( 'KCP Mobile Payment', $this->woopay_domain );
			$this->title_default 			= __( 'Mobile Payment', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via mobile payment.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'mobile';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= false;
		}

	}

	function add_kcp_mobile( $methods ) {
		$methods[] = 'WooPayKCPMobile';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_kcp_mobile' );
}