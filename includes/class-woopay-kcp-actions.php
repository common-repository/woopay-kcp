<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPActions' ) ) {
	class WooPayKCPActions extends WooPayKCP {
		function api_action( $type ) {
			@ob_clean();
			header( 'HTTP/1.1 200 OK' );
			switch ( $type ) {
				case 'check_api' :
					$this->do_check_api( $_REQUEST );
					exit;
					break;
				case 'response' :
					$this->do_response( $_REQUEST );
					exit;
					break;
				case 'mobile_approval' :
					$this->do_mobile_approval( $_REQUEST );
					exit;
					break;
				case 'cas_response' :
					$this->do_cas_response( $_REQUEST );
					exit;
					break;
				case 'refund_request' :
					$this->do_refund_request( $_REQUEST );
					exit;
					break;
				case 'escrow_request' :
					$this->do_escrow_request( $_REQUEST );
					exit;
					break;
				case 'delete_log' :
					$this->do_delete_log( $_REQUEST );
					exit;
					break;
				default :
					exit;
			}
		}

		private function do_check_api( $params ) {
			$result = array(
				'result'	=> 'success',
			);

			echo json_encode( $result );
		}

		private function do_response( $params ) {
			if ( isset( $params[ 'res_cd' ] ) && ( $params[ 'res_cd' ] == 'a3001' || $params[ 'res_cd' ] == '3001' ) ) {
				if ( isset( $params[ 'ordr_idxx' ] ) ) {
					$orderid = $params[ 'ordr_idxx' ];
					$this->woopay_user_cancelled( $orderid );
				}
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( empty( $params[ 'req_tx' ] ) || empty( $params[ 'ordr_idxx' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'ordr_idxx' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			if ( $params[ 'res_cd' ] == 'a3001' ) {
				$this->woopay_user_cancelled( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting response process.', $this->woopay_domain ), $orderid );

			require_once $this->woopay_plugin_basedir . '/bin/lib/kcp_lib.php';

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

			$site_cd		= $g_conf_site_cd;
			$req_tx         = $params[ 'req_tx' ];
			$tran_cd        = $params[ 'tran_cd' ];
			$cust_ip        = $this->get_client_ip();
			$ordr_idxx      = $orderid;
			$good_name      = iconv( 'utf-8', 'euc-kr', $params[ 'good_name' ] );
			$good_mny       = $params[ 'good_mny' ];

			$res_cd         = '';
			$res_msg        = '';
			$res_en_msg     = '';
			$tno            = isset( $params[ 'tno' ] ) ? $params[ 'tno' ] : '';

			$buyr_name      = iconv( 'utf-8', 'euc-kr', $params[ 'buyr_name' ] );
			$buyr_tel1      = $params[ 'buyr_tel1' ];
			$buyr_tel2      = $params[ 'buyr_tel2' ];
			$buyr_mail      = $params[ 'buyr_mail' ];

			$use_pay_method = $params[ 'use_pay_method' ];
			$bSucc          = '';

			$app_time       = '';
			$amount         = '';
			$total_amount   = 0;
			$coupon_mny     = '';

			$card_cd        = '';
			$card_name      = '';
			$app_no         = '';
			$noinf          = '';
			$quota          = '';
			$partcanc_yn    = '';
			$card_bin_type_01 = '';
			$card_bin_type_02 = '';
			$card_mny       = '';

			$bank_name      = '';
			$bank_code      = '';
			$bk_mny         = '';

			$bankname       = '';
			$depositor      = '';
			$account        = '';
			$va_date        = '';

			$pnt_issue      = '';
			$pnt_amount     = '';
			$pnt_app_time   = '';
			$pnt_app_no     = '';
			$add_pnt        = '';
			$use_pnt        = '';
			$rsv_pnt        = '';

			$commid         = '';
			$mobile_no      = '';

			$shop_user_id   = isset( $params[ 'shop_user_id' ] ) ? $params[ 'shop_user_id' ] : '';
			$tk_van_code    = '';
			$tk_app_no      = '';

			$cash_yn        = isset( $params[ 'cash_yn' ] ) ? $params[ 'cash_yn' ] : '';
			$cash_authno    = '';
			$cash_tr_code   = isset( $params[ 'cash_tr_code' ] ) ? $params[ 'cash_tr_code' ] : '';
			$cash_id_info   = isset( $params[ 'cash_id_info' ] ) ? $params[ 'cash_id_info' ] : '';

			$trace_no		= $params[ 'trace_no' ];

			$c_PayPlus = new C_PP_CLI;

			$c_PayPlus->mf_clear();

			if ( $req_tx == 'pay' ) {   
				$c_PayPlus->mf_set_ordr_data( 'ordr_mony', $good_mny );
				$c_PayPlus->mf_set_encx_data( $params[ 'enc_data' ], $params[ 'enc_info' ] );
			}

			if ( $tran_cd != '' ) {
				$c_PayPlus->mf_do_tx( $trace_no, $g_conf_home_dir, $g_conf_site_cd, $g_conf_site_key, $tran_cd, '', $g_conf_gw_url, $g_conf_gw_port, 'payplus_cli_slib', $ordr_idxx, $cust_ip, $g_conf_log_level, 0, 0, $g_conf_key_dir, $g_conf_log_dir );

				$res_cd  = $c_PayPlus->m_res_cd;
				$res_msg = $c_PayPlus->m_res_msg;
				$res_msg = iconv( 'euc-kr', 'utf-8', $res_msg );
			} else {
				$res_cd = '9562';
				$res_msg = __( 'Payplus Plugin is not installed, or tran_cd value is not set.', $this->woopay_domain );
			}

			$tid = $c_PayPlus->mf_get_res_data( 'tno' );

			$this->log( __( 'Result Code: ', $this->woopay_domain ) . $res_cd, $orderid );
			$this->log( __( 'Result Message: ', $this->woopay_domain ) . $res_msg, $orderid );

			$paySuccess = false;

			if ( $res_cd == '0000' ) $paySuccess = true;

			if ( (int)$good_mny != (int)$order->get_total() ) {
				$paySuccess = false;

				$this->woopay_payment_integrity_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( $paySuccess == true ) {
				if ( $use_pay_method == '001000000000' ) {
					$bankname  = iconv( 'euc-kr', 'utf-8', $c_PayPlus->mf_get_res_data( 'bankname' ) );
					$account   = $c_PayPlus->mf_get_res_data( 'account' );
					$va_date   = $c_PayPlus->mf_get_res_data( 'va_date' );

					$this->woopay_payment_awaiting( $orderid, $tid, $use_pay_method, $bankname, $account, $va_date );
				} else {
					$this->woopay_payment_complete( $orderid, $tid, $use_pay_method );
				}

				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			} else {
				$this->woopay_payment_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}
		}

		private function do_mobile_approval( $params ) {
			if ( empty( $params[ 'ordr_idxx' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'ordr_idxx' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile approval received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting mobile approval process.', $this->woopay_domain ), $orderid );

			require_once $this->woopay_plugin_basedir . '/bin/lib/kcp_mobile_lib.php';

			$charSetType      = 'utf-8';

			$siteCode         = ( $this->testmode ) ? 'T0000' : $this->site_cd;
			$orderid          = $params[ 'ordr_idxx' ];
			$paymentMethod    = $params[ 'pay_method' ];
			$escrow           = $this->escw_yn;
			$productName      = $params[ 'good_name' ];
			$paymentAmount    = $params[ 'good_mny' ];
			$returnUrl        = $params[ 'Ret_URL' ];

			$accessLicense    = '';
			$signature        = '';
			$timestamp        = '';

			$detailLevel = '0';
			$requestApp = 'WEB';
			$requestID = $orderid;
			$userAgent = $_SERVER[ 'HTTP_USER_AGENT' ];
			$version = '0.1';

			$g_wsdl = ( $this->testmode ) ? $this->woopay_plugin_basedir . '/bin/bin/KCPPaymentService.wsdl' : $this->woopay_plugin_basedir . '/bin/bin/real_KCPPaymentService.wsdl';

			try {
				$payService = new PayService( $g_wsdl );

				$payService->setCharSet( $charSetType );
				
				$payService->setAccessCredentialType( $accessLicense, $signature, $timestamp );
				$payService->setBaseRequestType( $detailLevel, $requestApp, $requestID, $userAgent, $version );
				$payService->setApproveReq( $escrow, $orderid, $paymentAmount, $paymentMethod, $productName, $returnUrl, $siteCode );

				$approveRes = $payService->approve();

				printf( '%s,%s,%s,%s', $payService->resCD,  $approveRes->approvalKey, $approveRes->payUrl, $payService->resMsg );
				exit;
			} catch ( SoapFault $ex ) {
				printf( '%s,%s,%s,%s', '95XX', '', '', __( 'PHP SOAP Module is needed.', $this->woopay_domain ) );
				exit;
			}
		}

		private function do_cas_response( $params ) {
			if ( empty( $params[ 'tx_cd' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'order_no' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'CAS response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting CAS response process.', $this->woopay_domain ), $orderid );

			$tid = $params[ 'tno' ];
			$tx_cd = $params[ 'tx_cd' ];
			$tx_tm = $params[ 'tx_tm' ];

			if ( $tx_cd == 'TX00' ) {
				$this->woopay_cas_payment_complete( $orderid, $tid, 'VBANK' );

				echo '<html><body><form><input type="hidden" name="result" value="0000"></form></body></html>';
				exit;
			} else {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}
		}

		private function do_refund_request( $params ) {
			if ( ! isset( $params[ 'orderid' ] ) || ! isset( $params[ 'tid' ] ) || ! isset( $params[ 'type' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'orderid' ];
			$tid			= $params[ 'tid' ];

			$woopay_refund = new WooPayKCPRefund();
			$return = $woopay_refund->do_refund( $orderid, null, __( 'Refund request by customer', $this->woopay_domain ), $tid, 'customer' );

			if ( $return[ 'result' ] == 'success' ) {
				wc_add_notice( $return[ 'message' ], 'notice' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			} else {
				wc_add_notice( $return[ 'message' ], 'error' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			}
			exit;
		}

		private function do_escrow_request( $params ) {
			exit;
		}

		private function do_delete_log( $params ) {
			if ( ! isset( $params[ 'file' ] ) ) {
				$return = array(
					'result' => 'failure',
				);
			} else {
				$file = trailingslashit( WC_LOG_DIR ) . $params[ 'file' ];

				if ( file_exists( $file ) ) {
					unlink( $file );
				}

				$return = array(
					'result' => 'success',
					'message' => __( 'Log file has been deleted.', $this->woopay_domain )
				);
			}

			echo json_encode( $return );

			exit;
		}
	}
}