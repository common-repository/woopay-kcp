<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayKCPPayment' ) ) {
	class WooPayKCPPayment extends WooPayKCP {
		public $title_default;
		public $desc_default;
		public $default_checkout_img;
		public $allowed_currency;
		public $allow_other_currency;
		public $allow_testmode;

		function __construct() {
			parent::__construct();

			$this->method_init();
			$this->init_settings();
			$this->init_form_fields();

			$this->get_woopay_settings();

			// Actions
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_virtual_information' ) );
			add_action( 'woocommerce_view_order', array( $this, 'get_virtual_information' ), 9 );

			if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
				if ( ! $this->allow_other_currency ) {
					$this->enabled = 'no';
				}
			}

			if ( $this->get_file_permission( $this->woopay_plugin_basedir . '/bin/bin/pp_cli' ) >= 755 ) {
				if ( ! $this->testmode ) {
					if ( $this->site_cd == '' || $this->site_key == '' || $this->site_name == '' ) {
						$this->enabled = 'no';
					}
				} else {
					$this->title		= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->title;
					$this->description	= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->description;
				}
			} else {
				$this->enabled = 'no';
			}
		}

		function method_init() {
		}

		function pg_scripts() {
			if ( is_checkout() ) {
				if ( $this->testmode ) {
					$script_url = 'https://testpay.kcp.co.kr/plugin/payplus_web.jsp';
				} else {
					$script_url = 'https://pay.kcp.co.kr/plugin/payplus_web.jsp';
				}

				if ( $this->check_mobile() ) {
					$script_url = $this->woopay_plugin_url . 'assets/js/approval-key.js';

					wp_register_script( 'kcp_mobile_script', $script_url, array( 'jquery' ), null, false );
					wp_enqueue_script( 'kcp_mobile_script' );
				} else {
					wp_register_script( 'kcp_script', $script_url, array( 'jquery' ), null, false );
					wp_enqueue_script( 'kcp_script' );
				}
			}
		}

		function receipt( $orderid ) {
			$this->pg_scripts();

			$order = new WC_Order( $orderid );

			if ( $this->checkout_img ) {
				echo '<div class="p8-checkout-img"><img src="' . $this->checkout_img . '"></div>';
			}

			echo '<div class="p8-checkout-txt">' . str_replace( "\n", '<br>', $this->checkout_txt ) . '</div>';

			if ( $this->show_chrome_msg == 'yes' ) {
				if ( $this->get_chrome_version() >= 42 && $this->get_chrome_version() < 45 ) {
					echo '<div class="p8-chrome-msg">';
					echo __( 'If you continue seeing the message to install the plugin, please enable NPAPI settings by following these steps:', $this->woopay_domain );
					echo '<br>';
					echo __( '1. Enter <u>chrome://flags/#enable-npapi</u> on the address bar.', $this->woopay_domain );
					echo '<br>';
					echo __( '2. Enable NPAPI.', $this->woopay_domain );
					echo '<br>';
					echo __( '3. Restart Chrome and refresh this page.', $this->woopay_domain );
					echo '</div>';
				}
			}

			$currency_check = $this->currency_check( $order, $this->allowed_currency );

			if ( $currency_check ) {
				echo $this->woopay_form( $orderid );
			} else {
				$currency_str = $this->get_currency_str( $this->allowed_currency );

				echo sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_post_meta( $order->id, '_order_currency', true ), $currency_str );
			}
		}

		function get_woopay_args( $order ) {
			$orderid = $order->id;

			$this->billing_phone = $order->billing_phone;

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item[ 'qty' ] ) {
						$item_name = $item[ 'name' ];
					}
				}
			}

			$price			= ( int )$order->order_total;

			if ( ! $this->check_mobile() ) {
				$woopay_args =
					array(
						'pay_method'			=> $this->method,
						'ordr_idxx'				=> $order->id,
						'good_name'				=> sanitize_text_field( $item_name ),
						'good_mny'				=> $price,
						'buyr_name'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'buyr_mail' 			=> $order->billing_email,
						'buyr_tel1'				=> $order->billing_phone,
						'buyr_tel2'				=> $order->billing_phone,
						'req_tx'				=> 'pay',
						'site_cd'				=> ( $this->testmode ) ? 'T0000' : $this->site_cd,
						'site_name'				=> ( $this->testmode ) ? '[TEST] ' . $this->site_name : $this->site_name,
						'escw_yn'				=> ( $this->escw_yn ) ? 'Y' : 'N',
						'quotaopt'				=> '12',
						'vcnt_expire_term'		=> $this->expiry_time,
						'currency'				=> 'WON',
						'module_type'			=> '01',
						'res_cd'				=> '',
						'res_msg'				=> '',
						'tno'					=> '',
						'trace_no'				=> '',
						'enc_info'				=> '',
						'enc_data'				=> '',
						'ret_pay_method'		=> '',
						'tran_cd'				=> '',
						'bank_name'				=> '',
						'bank_issu'				=> '',
						'use_pay_method'		=> '',
						'cash_tsdtime'			=> '',
						'cash_yn'				=> '',
						'cash_authno'			=> '',
						'cash_tr_code'			=> '',
						'cash_id_info'			=> '',
						'good_expr'				=> '0',
						'shop_user_id'			=> '',
						'pt_memcorp_cd'			=> '',
						'Ret_URL'				=> $this->get_api_url( 'response' ),
						'rcvr_name'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'rcvr_tel1'				=> $order->billing_phone,
						'rcvr_tel2'				=> $order->billing_phone,
						'rcvr_mail'				=> $order->billing_email,
						'rcvr_zipx'				=> '',
						'rcvr_add1'				=> '',
						'rcvr_add2'				=> '',
						'pay_mod'				=> ( $this->escw_yn ) ? 'Y' : 'N',
						'bask_cntx'				=> $this->get_bask_cntx( $order ),
						'deli_term'				=> ( $this->escw_yn ) ? '2' : '',
						'good_info'				=> $this->get_good_info( $order ),
						'used_card_YN'			=> ( $this->specific_option == 'all' ) ? 'N' : 'Y',
						'used_card'				=> ( $this->specific_option == 'specific' ) ? $this->get_used_card( $this->specific_cards ) : '',
						'skin_indx'				=> $this->skin_indx,
						'site_logo'				=> $this->site_logo,
					);
			} else {
				$woopay_args =
					array(
						'ActionResult'			=> $this->get_action_result( $this->method ),
						'pay_method'			=> $this->get_mobile_method( $this->method ),
						'ordr_idxx'				=> $order->id,
						'good_name'				=> sanitize_text_field( $item_name ),
						'good_mny'				=> $price,
						'buyr_name'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'buyr_mail' 			=> $order->billing_email,
						'buyr_tel1'				=> $order->billing_phone,
						'buyr_tel2'				=> $order->billing_phone,
						'req_tx'				=> 'pay',
						'site_cd'				=> ( $this->testmode ) ? 'T0000' : $this->site_cd,
						'shop_name'				=> ( $this->testmode ) ? '[TEST] ' . $this->site_name : $this->site_name,
						'currency'				=> '410',
						'eng_flag'				=> 'N',
						'escw_used'				=> ( $this->escw_yn )?'Y':'N',
						'van_code'				=> '',
						'quotaopt'				=> '12',
						'ipgm_date'				=> $this->get_expirytime( $this->expiry_time ),
						'shop_user_id'			=> '',
						'pt_memcorp_cd'			=> '',
						'disp_tax_yn'			=> 'Y',
						'Ret_URL'				=> $this->get_api_url( 'response' ),
						'tablet_size'			=> '1.0',
						'approval_url'			=> $this->get_api_url( 'mobile_approval' ),
						'approval_key'			=> '',
						'encoding_trans'		=> 'UTF-8',
						'PayUrl'				=> '',
						'mobile_pay_method'		=> $this->method,
						'rcvr_name'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'rcvr_tel1'				=> $order->billing_phone,
						'rcvr_tel2'				=> $order->billing_phone,
						'rcvr_mail'				=> $order->billing_email,
						'rcvr_zipx'				=> '',
						'rcvr_add1'				=> '',
						'rcvr_add2'				=> '',
						'pay_mod'				=> ( $this->escw_yn )? 'Y' : 'N',
						'bask_cntx'				=> $this->get_bask_cntx( $order ),
						'deli_term'				=> '',
						'good_info'				=> $this->get_good_info( $order ),
						'used_card_YN'			=> ( $this->specific_option == 'all' ) ? 'N' : 'Y',
						'used_card'				=> ( $this->specific_option == 'specific' ) ? $this->get_used_card( $this->specific_cards ) : '',
						'checkout_url'			=> WC()->cart->get_checkout_url(),
						'testmode'				=> $this->testmode,
					);
			}

			$woopay_args = apply_filters( 'woocommerce_woopay_args', $woopay_args );

			return $woopay_args;
		}

		function get_bask_cntx( $order ) {
			$bask_cntx = 0;

			foreach( $order->get_items() as $item ) {
				$bask_cntx++;
			}

			return $bask_cntx;
		}

		function get_good_info( $order ) {
			global $woocommerce;

			$seq = 1;
			$good_info = '';
			foreach( $order->get_items() as $item ) {
				if ( $seq > 1 ) {
					$good_info .= chr(30) . 'seq=' . $seq . chr(31) . 'ordr_numb=' . $order->id . '_' . substr( '0000' . $seq, -4 ) . chr(31) . 'good_name=' . $item[ 'name' ] . chr(31) . 'good_cntx=' . $item[ 'qty' ] . chr(31) . 'good_amtx=' . $item[ 'line_total' ];
				} else {
					$good_info .= 'seq=' . $seq . chr(31) . 'ordr_numb=' . $order->id . '_' . substr( '0000' . $seq, -4 ) . chr(31) . 'good_name=' . $item[ 'name' ] . chr(31) . 'good_cntx=' . $item[ 'qty' ] . chr(31) . 'good_amtx=' . $item[ 'line_total' ];
				}
				$seq++;
			}

			return $good_info;
		}

		function get_action_result( $method ) {
			switch ( $method ) {
				case '100000000000' :
					return 'card';
				break;
				case '010000000000' :
					return 'acnt';
				break;
				case '001000000000' :
					return 'vcnt';
				break;
				case '000010000000' :
					return 'mobx';
				break;
				default :
					return '';
			}
		}

		function get_mobile_method( $method ) {
			switch ( $method ) {
				case '100000000000' :
					return 'CARD';
				break;
				case '010000000000' :
					return 'BANK';
				break;
				case '001000000000' :
					return 'VCNT';
				break;
				case '000010000000' :
					return 'MOBX';
				break;
				default :
					return '';
			}
		}

		function get_used_card( $cards ) {
			$card = '';

			if ( is_array( $cards ) ) {
				$i = 0;
				foreach ( $cards as $key => $value ) {
					if ( $i > 0 ) {
						$card .= ':';
					}

					$card .= $value;
					$i++;
				}
			}

			return $card;
		}

		function woopay_form( $orderid ) {
			$order = new WC_Order( $orderid );

			$woopay_args = $this->get_woopay_args( $order );

			$woopay_args_array = array();

			foreach ( $woopay_args as $key => $value ) {
				$woopay_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" id="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			$woopay_form = "<form method='post' id='order_info' name='order_info'>" . implode( '', $woopay_args_array ) . " </form>";

			if ( $this->check_mobile() ) {
				$woopay_form = $woopay_form."
				<form name='pay_form' method='post'>
					<input type='hidden' name='req_tx'			value=''>
					<input type='hidden' name='res_cd'			value=''>
					<input type='hidden' name='tran_cd'			value=''>
					<input type='hidden' name='ordr_idxx'		value='" . $woopay_args[ 'ordr_idxx' ] . "'>
					<input type='hidden' name='good_mny'		value='" . $woopay_args[ 'good_mny' ] . "'>
					<input type='hidden' name='good_name'		value='" . $woopay_args[ 'good_name' ] . "'>
					<input type='hidden' name='buyr_name'		value='" . $woopay_args[ 'buyr_name' ] . "'>
					<input type='hidden' name='buyr_tel1'		value='" . $woopay_args[ 'buyr_tel1' ] . "'>
					<input type='hidden' name='buyr_tel2'		value='" . $woopay_args[ 'buyr_tel2' ] . "'>
					<input type='hidden' name='buyr_mail'		value='" . $woopay_args[ 'buyr_mail' ] . "'>
					<input type='hidden' name='cash_yn'			value=''>
					<input type='hidden' name='enc_info'		value=''>
					<input type='hidden' name='enc_data'		value=''>
					<input type='hidden' name='use_pay_method'	value='" . $woopay_args[ 'mobile_pay_method' ] . "'>
					<input type='hidden' name='cash_tr_code'	value=''>
					<input type='hidden' name='param_opt_1'		value=''>
					<input type='hidden' name='param_opt_2'		value=''>
					<input type='hidden' name='param_opt_3'		value=''>
				</form>
				";
			} else {
				$woopay_form = "<form method='post' id='order_info' name='order_info'>".implode( '', $woopay_args_array )." </form>";
			}


			if ( ! $this->check_mobile() ) {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay.js';
			} else {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay-mobile.js';
			}

			wp_register_script( $this->woopay_api_name . 'woopay_script', $woopay_script_url, array( 'jquery' ), '1.0.0', true );

			$translation_array = array(
				'payment_title'			=> __( 'KCP Payment', $this->woopay_domain ),
				'testmode_msg'			=> __( 'Test mode is enabled. Continue?', $this->woopay_domain ),
				'cancel_msg'			=> __( 'You have cancelled your transaction. Returning to cart.', $this->woopay_domain ),
				'amount_msg'			=> __( 'You cannot pay for more than 300,000 Won. Returning to cart.', $this->woopay_domain ),
				'returl_msg'			=> __( 'You must set your return URL.', $this->woopay_domain ),
				'checkout_url'			=> WC()->cart->get_checkout_url(),
				'response_url'			=> $this->get_api_url( 'response' ),
				'cart_url'				=> WC()->cart->get_cart_url(),
				'testmode'				=> $this->testmode,
			);

			wp_localize_script( $this->woopay_api_name . 'woopay_script', 'woopay_string', $translation_array );
			wp_enqueue_script( $this->woopay_api_name . 'woopay_script' );

			return $woopay_form;
		}

		public function process_payment( $orderid ) {
			$order = new WC_Order( $orderid );

			$this->woopay_start_payment( $orderid );

			if ( $this->testmode ) {
				wc_add_notice( __( '<strong>Test mode is enabled!</strong> Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ), 'error' );
			}

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		public function process_refund( $orderid, $amount = null, $reason = '' ) {
			$woopay_refund = new WooPayKCPRefund();
			$return = $woopay_refund->do_refund( $orderid, $amount, $reason );

			if ( $return[ 'result' ] == 'success' ) {
				return true;
			} else {
				return false;
			}
		}

		function admin_options() {
			$currency_str = $this->get_currency_str( $this->allowed_currency );

			echo '<h3>' . $this->method_title . '</h3>';

			$this->get_woopay_settings();

			$hide_form = "";

			if ( ! $this->woopay_check_api() ) {
				echo '<div class="inline error"><p><strong>' . sprintf( __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please check your permalink settings. You must use a permalink structure other than \'General\'. Click <a href="%s">here</a> to change your permalink settings.', $this->woopay_domain ), $this->get_url( 'admin', 'options-permalink.php' ) ) . '</p></div>';

				$hide_form = "display:none;";
			} else {
				if ( $this->get_file_permission( $this->woopay_plugin_basedir . '/bin/bin/pp_cli' ) < 755 ) {
					echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . sprintf( __( 'Your pp_cli file does not have proper permission. It needs at least 755 permission. File is located at: <code>%s</code>.', $this->woopay_domain ) , $this->woopay_plugin_basedir . '/bin/bin/pp_cli' ) . '</p></div>';
				} else {
					if ( ! $this->testmode ) {
						if ( $this->site_cd == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Site Code.', $this->woopay_domain ) . '</p></div>';
						} else if ( $this->site_key == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Site Key.', $this->woopay_domain ) . '</p></div>';
						} else if ( $this->site_name == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Site Name.', $this->woopay_domain ) . '</p></div>';
						}
					} else {
						echo '<div class="inline error"><p><strong>' . __( 'Test mode is enabled!', $this->woopay_domain ) . '</strong> ' . __( 'Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ) . '</p></div>';
					}
				}

				if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
					if ( ! $this->allow_other_currency ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					} else {
						echo '<div class="inline notice notice-info"><p><strong>' . __( 'Please Note', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not recommended by this payment method. This payment method recommeds the following currency: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					}
				}
			}

			echo '<div id="' . $this->woopay_plugin_name . '" style="' . $hide_form . '">';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
			echo '</div>';

			wc_enqueue_js( "
				jQuery('select.specific_option').change(function() {

					var val = jQuery( this ).val();

					if ( val == 'all' )
						jQuery('.specific_cards').hide();

					if ( val == 'specific' )
						jQuery('.specific_cards').show();

				}).change();
			" );
		}

		function init_form_fields() {
			// General Settings
			$general_array = array(
				'general_title' => array(
					'title' => __( 'General Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable this method.', $this->woopay_domain ),
					'default' => 'yes'
				),
				'testmode' => array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable test mode.', $this->woopay_domain ),
					'description' => '',
					'default' => 'no'
				),
				'log_enabled' => array(
					'title' => __( 'Enable/Disable Logs', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging.', $this->woopay_domain ),
					'description' => __( 'Logs will be automatically created when in test mode.', $this->woopay_domain ),
					'default' => 'no'
				),
				'log_control' => array(
					'title' => __( 'View/Delete Log', $this->woopay_domain ),
					'type' => 'log_control',
					'description' => '',
					'desc_tip' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Title that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->title_default,
				),
				'description' => array(
					'title' => __( 'Description', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Description that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->desc_default,
				),
				'site_cd' => array(
					'title' => __( 'Site Code', $this->woopay_domain ),
					'type' => 'text',
					'class' => 'kcp_site_cd',
					'description' => __( 'Please enter your Site Code.', $this->woopay_domain ),
					'default' => ''
				),
				'site_key' => array(
					'title' => __( 'Site Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Site Key.', $this->woopay_domain ),
					'default' => ''
				),
				'site_name' => array(
					'title' => __( 'Site Name', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Site Name.', $this->woopay_domain ),
					'default' => ''
				),
				'expiry_time' => array(
					'title' => __( 'Expiry time in days', $this->woopay_domain ),
					'type'=> 'select',
					'description' => __( 'Select the virtual account transfer expiry time in days.', $this->woopay_domain ),
					'options'	=> array(
						'1'			=> __( '1 day', $this->woopay_domain ),
						'2'			=> __( '2 days', $this->woopay_domain ),
						'3'			=> __( '3 days', $this->woopay_domain ),
						'4'			=> __( '4 days', $this->woopay_domain ),
						'5'			=> __( '5 days', $this->woopay_domain ),
						'6'			=> __( '6 days', $this->woopay_domain ),
						'7'			=> __( '7 days', $this->woopay_domain ),
						'8'			=> __( '8 days', $this->woopay_domain ),
						'9'			=> __( '9 days', $this->woopay_domain ),
						'10'		=> __( '10 days', $this->woopay_domain ),
					),
					'default' => ( '5' ),
				),
				'escw_yn' => array(
					'title' => __( 'Escrow Settings', $this->woopay_domain ),
					'type' => 'checkbox',
					'description' => __( 'Force escrow settings.', $this->woopay_domain ),
					'default' => 'no',
				),
			);

			// Refund Settings
			$refund_array = array(
				'refund_title' => array(
					'title' => __( 'Refund Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'refund_btn_txt' => array(
					'title' => __( 'Refund Button Text', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Text for refund button that users will see.', $this->woopay_domain ),
					'default' => __( 'Refund', $this->woopay_domain ),
				),
				'customer_refund' => array (
					'title' => __( 'Refundable Satus for Customer', $this->woopay_domain ),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'description' => __( 'Select the order status for allowing refund.', $this->woopay_domain ),
					'options' => $this->get_status_array(),
				)
			);

			// Design Settings
			$design_array = array(
				'design_title' => array(
					'title' => __( 'Design Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'skin_indx' => array(
					'title' => __( 'Skin Type', $this->woopay_domain ),
					'type' => 'select',
					'description' => __( 'Select the skin type for your KCP form.', $this->woopay_domain ),
					'options' => array(
						'1' => __( 'Light Blue', $this->woopay_domain ),
						'2' => __( 'Purple', $this->woopay_domain ),
						'3' => __( 'Brown', $this->woopay_domain ),
						'4' => __( 'Magenta', $this->woopay_domain ),
						'5' => __( 'Dark Blue', $this->woopay_domain ),
						'6' => __( 'Turquoise', $this->woopay_domain ),
						'7' => __( 'Gold', $this->woopay_domain ),
						'8' => __( 'Orange', $this->woopay_domain ),
						'9' => __( 'Green', $this->woopay_domain ),
						'10' => __( 'Red', $this->woopay_domain ),
						'11' => __( 'Grey', $this->woopay_domain ),
					)
				),
				'site_logo' => array(
					'title' => __( 'Logo Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your logo. The size should be 150*50. You can use GIF/JPG.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Logo', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Logo', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'checkout_img' => array(
					'title' => __( 'Checkout Processing Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the checkout processing page. Leave blank to show no image.', $this->woopay_domain ),
					'default' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
					'btn_name' => __( 'Select/Upload Image', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Image', $this->woopay_domain ),
					'default_btn_name' => __( 'Use Default', $this->woopay_domain ),
					'default_btn_url' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
				),	
				'checkout_txt' => array(
					'title' => __( 'Checkout Processing Text', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Text that users will see on the checkout processing page. You can use some HTML tags as well.', $this->woopay_domain ),
					'default' => __( "<strong>Please wait while your payment is being processed.</strong>\nIf you see this page for a long time, please try to refresh the page.", $this->woopay_domain )
				),
				'show_chrome_msg' => array(
					'title' => __( 'Chrome Message', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Show steps to enable NPAPI for Chrome users using less than v45.', $this->woopay_domain ),
					'description' => '',
					'default' => 'yes'
				)
			);

			if ( $this->id == 'kcp_card' ) {
				$general_array = array_merge( $general_array,
					array(
						'specific_option' => array(
							'title' => __( 'Specific Card Option', $this->woopay_domain ),
							'type' => 'select_adaptive',
							'class' => 'wc-enhanced-select',
							'description' => __( 'If you want to use specific cards for payment, change this option.', $this->woopay_domain ),
							'options'       => array(
								'all' => __( 'Use All Cards', $this->woopay_domain ),
								'specific' => __( 'Use Specific Cards', $this->woopay_domain ),
							),
							'default' => 'all',
						),
						'specific_cards' => array(
							'title' => __( 'Specific Cards', $this->woopay_domain ),
							'type' => 'multiselect_adaptive',
							'class' => 'chosen_select',
							'description' => __( 'Select the cards available for payment.', $this->woopay_domain ),
							'options' => array(
								'CCLG' => __( 'Shinhan', $this->woopay_domain ),
								'CCDI' => __( 'Hyundai', $this->woopay_domain ),
								'CCLO' => __( 'Lotte', $this->woopay_domain ),
								'CCKE' => __( 'KEB', $this->woopay_domain ),
								'CCSS' => __( 'Samsung', $this->woopay_domain ),
								'CCKM' => __( 'Kookmin', $this->woopay_domain ),
								'CCBC' => __( 'BC', $this->woopay_domain ),
								'CCNH' => __( 'Nong Hyup', $this->woopay_domain ),
								'CCHN' => __( 'Hana SK', $this->woopay_domain ),
								'CCCT' => __( 'Citi', $this->woopay_domain ),
								'CCPH' => __( 'Woori', $this->woopay_domain ),
								'CCKJ' => __( 'Kwangju', $this->woopay_domain ),
								'CCSU' => __( 'Su Hyup', $this->woopay_domain ),
								'CCJB' => __( 'Jeonbuk', $this->woopay_domain ),
								'CCCJ' => __( 'Jeju', $this->woopay_domain ),
								'CCKD' => __( 'KDB', $this->woopay_domain ),
								'CCSB' => __( 'FSB', $this->woopay_domain ),
								'CCCU' => __( 'Shin Hyup', $this->woopay_domain ),
								'CCPB' => __( 'Post Bank', $this->woopay_domain ),
								'CCSM' => __( 'MG Saemaeul', $this->woopay_domain ),
								'CCXX' => __( 'International', $this->woopay_domain ),
								'CCUF' => __( 'Union Pay', $this->woopay_domain ),
								'BC81' => __( 'Hana BC', $this->woopay_domain ),
							),
							'default' => array( 'CCLG', 'CCDI', 'CCLO', 'CCKE', 'CCSS', 'CCKM', 'CCBC', 'CCNH', 'CCHN', 'CCCT', 'CCPH', 'CCKJ', 'CCSU', 'CCJB', 'CCCJ', 'CCKD', 'CCSB', 'CCCU', 'CCPB', 'CCSM', 'CCXX', 'CCUF', 'BC81' ),
						),
						'quotabase' => array(
							'title' => __( 'Installments Setting', $this->woopay_domain ),
							'type' => 'multiselect',
							'class' => 'chosen_select',
							'description' => __( 'Select installments.', $this->woopay_domain ),
							'options'       => array(
								'2개월' => __( '2 Months', $this->woopay_domain ),
								'3개월' => __( '3 Months', $this->woopay_domain ),
								'4개월' => __( '4 Months', $this->woopay_domain ),
								'5개월' => __( '5 Months', $this->woopay_domain ),
								'6개월' => __( '6 Months', $this->woopay_domain ),
								'7개월' => __( '7 Months', $this->woopay_domain ),
								'8개월' => __( '8 Months', $this->woopay_domain ),
								'9개월' => __( '9 Months', $this->woopay_domain ),
								'10개월' => __( '10 Months', $this->woopay_domain ),
								'11개월' => __( '11 Months', $this->woopay_domain ),
								'12개월' => __( '12 Months', $this->woopay_domain ),
							),
						),
						'nointerest' => array(
							'title' => __( 'No Interest Setting', $this->woopay_domain ),
							'type' => 'checkbox',
							'description' => __( 'Allow no interest settings.', $this->woopay_domain ),
							'default' => 'no',
						),
					)
				);
			}

			if ( $this->id == 'kcp_virtual' ) {
				$general_array = array_merge( $general_array,
					array(
						'callback_url' => array(
							'title' => __( 'Callback URL', $this->woopay_domain ),
							'type' => 'txt_info',
							'txt' => $this->get_api_url( 'cas_response' ),
							'description' => __( 'Callback URL used for payment notice from KCP.', $this->woopay_domain )
						)
					)
				);
			}

			if ( ! $this->allow_testmode ) {
				$general_array[ 'testmode' ] = array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'txt_info',
					'txt' => __( 'You cannot test this payment method.', $this->woopay_domain ),
					'description' => '',
				);
			}

			if ( $this->id == 'kcp_card' || $this->id == 'kcp_mobile' ) {
				unset( $general_array[ 'escw_yn' ] );
			}

			if ( $this->id != 'kcp_virtual' ) {
				unset( $general_array[ 'expiry_time' ] );
			}

			if ( ! in_array( 'refunds', $this->supports ) ) {
				unset( $refund_array[ 'refund_btn_txt' ] );
				unset( $refund_array[ 'customer_refund' ] );

				$refund_array[ 'refund_title' ][ 'description' ] = __( 'This payment method does not support refunds. You can refund each transaction using the merchant page.', $this->woopay_domain );
			}

			$form_array = array_merge( $general_array, $refund_array );
			$form_array = array_merge( $form_array, $design_array );

			$this->form_fields = $form_array;

			$kcp_mid_bad_msg = __( 'This Merchant ID is not from Planet8. Please visit the following page for more information: <a href="http://www.planet8.co/woopay-kcp-change-mid/" target="_blank">http://www.planet8.co/woopay-kcp-change-mid/</a>', $this->woopay_domain );

			if ( is_admin() ) {
				if ( $this->id != '' ) {
					wc_enqueue_js( "
						function checkKCP( payment_id, mid ) {
							var bad_mid = '<span style=\"color:red;font-weight:bold;\">" . $kcp_mid_bad_msg . "</span>';
							var mids = [ '' ];

							if ( mid == '' || mid == undefined ) {
								jQuery( '#woocommerce_' + payment_id + '_site_cd' ).closest( 'tr' ).css( 'background-color', 'transparent' );
								jQuery( '#kcp_mid_bad_msg' ).html( '' );
							} else {
								if ( mid.substring( 0, 2 ) == 'PE' ) {
									jQuery( '#woocommerce_' + payment_id + '_site_cd' ).closest( 'tr' ).css( 'background-color', 'transparent' );
									jQuery( '#kcp_mid_bad_msg' ).html( '' );
								} else if ( jQuery.inArray( mid, mids ) > 0 ) {
									jQuery( '#woocommerce_' + payment_id + '_site_cd' ).closest( 'tr' ).css( 'background-color', 'transparent' );
									jQuery( '#kcp_mid_bad_msg' ).html( '' );
								} else {
									jQuery( '#woocommerce_' + payment_id + '_site_cd' ).closest( 'tr' ).css( 'background-color', '#FFC1C1' );
									jQuery( '#kcp_mid_bad_msg' ).html( bad_mid );
								}
							}
						}

						jQuery( '.kcp_site_cd' ).on( 'blur', function() {
							var val = jQuery( this ).val();

							checkKCP( '" . $this->id . "', val );
						});

						jQuery( document ).ready( function() {
							jQuery( '#woocommerce_" . $this->id . "_site_cd' ).closest( 'td' ).append( '<div id=\"kcp_mid_bad_msg\"></div>' );

							var val = jQuery( '.kcp_site_cd' ).val();

							checkKCP( '" . $this->id . "', val );
						});
					" );
				}
			}
		}
	}

	return new WooPayKCPPayment();
}