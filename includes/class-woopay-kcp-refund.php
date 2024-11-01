<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPRefund' ) ) {
	class WooPayKCPRefund extends WooPayKCP {
		public function __construct() {
			parent::__construct();

			$this->init_refund();
		}

		function init_refund() {
			// For Customer Refund
			add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'add_customer_refund' ), 10, 2 );
		}

		public function do_refund( $orderid, $amount = null, $reason = '', $rcvtid = null, $type = null, $acctname = null, $bankcode= null, $banknum = null ) {
			$order			= wc_get_order( $orderid );

			if ( $order == null ) {
				$message = __( 'Refund request received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting refund process.', $this->woopay_domain ), $orderid );

			if ( $amount == null ) {
				$amount = $order->get_total();
			}

			$tid = get_post_meta( $orderid, '_' . $this->woopay_api_name . '_tid', true );

			if ( $tid == '' ) {
				$message = __( 'No TID found.', $this->woopay_domain );
				$this->log( $message, $orderid );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			if ( $this->testmode ) {
				$g_conf_gw_url	= 'testpaygw.kcp.co.kr';
				$g_conf_gw_port	= '8090';
			} else {
				$g_conf_gw_url	= 'paygw.kcp.co.kr';
				$g_conf_gw_port	= '8080';
			}

			$g_conf_home_dir	= $this->woopay_plugin_basedir . '/bin';
			$g_conf_key_dir		= $this->woopay_plugin_basedir . '/bin/bin';
			$g_conf_site_cd		= ( $this->testmode ) ? 'T0000' : $this->site_cd;
			$g_conf_site_key	= ( $this->testmode ) ? '3grptw1.zW0GSo4PQdaGvsF__' : $this->site_key;
			$g_conf_site_name	= ( $this->testmode ) ? '[TEST]' . $this->site_name : $this->site_name;
			$g_conf_log_dir		= $this->woopay_plugin_basedir . '/bin/log';
			$g_conf_log_level	= '0';

			$tran_cd        = '00200000';
			$cust_ip        = $this->get_client_ip();

			if ( $reason == '' ) {
				$reason = '--';
			}

			require_once $this->woopay_plugin_basedir . '/bin/lib/kcp_lib.php';

			$c_PayPlus = new C_PP_CLI;

			$c_PayPlus->mf_clear();

			$c_PayPlus->mf_set_modx_data( 'tno', $tid );
			$c_PayPlus->mf_set_modx_data( 'mod_type', 'STSC' );
			$c_PayPlus->mf_set_modx_data( 'mod_ip', $cust_ip );
			$c_PayPlus->mf_set_modx_data( 'mod_desc', iconv( 'utf-8', 'euc-kr', $reason ) );

			$c_PayPlus->mf_do_tx( '', $g_conf_home_dir, $g_conf_site_cd, $g_conf_site_key, $tran_cd, '', $g_conf_gw_url, $g_conf_gw_port, 'payplus_cli_slib', '', $cust_ip, $g_conf_log_level, 0, 0, $g_conf_log_dir );

			$res_cd  = $c_PayPlus->m_res_cd;
			$res_msg = iconv( 'euc-kr', 'utf-8', $c_PayPlus->m_res_msg );

			if ( $type == 'customer' ) {
				$refunder = __( 'Customer', $this->woopay_domain );
			} else {
				$refunder = __( 'Administrator', $this->woopay_domain );
			}

			if ( $res_cd == '0000' ) {
				$message = sprintf( __( 'Refund process complete. Refunded by %s. Reason: %s.', $this->woopay_domain ), $refunder, $reason );

				$this->log( $message, $orderid );

				$message = sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() );

				$order->update_status( 'refunded', $message );

				return array(
					'result' 	=> 'success',
					'message'	=> __( 'Your refund request has been processed.', $this->woopay_domain )
				);
			} else {
				$message = __( 'An error occurred while processing the refund.', $this->woopay_domain );

				$this->log( $message, $orderid );
				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $res_cd, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $res_msg, $orderid );

				$order->add_order_note( sprintf( __( '%s Code: %s. Message: %s. Timestamp: %s.', $this->woopay_domain ), $message, $res_cd, $res_msg, $this->get_timestamp() ) );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}
		}
	}

	return new WooPayKCPRefund();
}