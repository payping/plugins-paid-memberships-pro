<?php
/*
Plugin Name: افزونه پرداخت پی‌پینگ برای Paid Memberships Pro
Version: 1.0
Description:  افزونه درگاه پرداخت پی‌پینگ برای Paid Memberships Pro
Plugin URI: https://www.payping.ir/
Author: Erfan Ebrahimi
Author URI: http://erfanebrahimi.ir/
*/


add_action('plugins_loaded', 'load_payping_pmpro_class', 11);
add_action('plugins_loaded', ['PMProGateway_payping', 'init'], 12);

add_filter('pmpro_currencies', 'pmpro_add_currency');
function pmpro_add_currency($currencies) {
	$currencies['IRT'] =  array(
		'name' =>'تومان',
		'symbol' => ' تومان ',
		'position' => 'left'
	);
	$currencies['IRR'] = array(
		'name' => ریال,
		'symbol' => ' ریال ',
		'position' => 'left'
	);
	return $currencies;
}



function load_payping_pmpro_class()
{
	if (class_exists('PMProGateway')) {
		class PMProGateway_payping extends PMProGateway
		{
			public function PMProGateway_payping($gateway = null)
			{
				$this->gateway = $gateway;
				$this->gateway_environment = pmpro_getOption('gateway_environment');

				return $this->gateway;
			}

			public static function init()
			{
				//make sure Stripe is a gateway option
				add_filter('pmpro_gateways', ['PMProGateway_payping', 'pmpro_gateways']);

				//add fields to payment settings
				add_filter('pmpro_payment_options', ['PMProGateway_payping', 'pmpro_payment_options']);
				add_filter('pmpro_payment_option_fields', ['PMProGateway_payping', 'pmpro_payment_option_fields'], 10, 2);
				$gateway = pmpro_getOption('gateway');

				if ($gateway == 'payping') {
					add_filter('pmpro_checkout_before_change_membership_level', ['PMProGateway_payping', 'pmpro_checkout_before_change_membership_level'], 10, 2);
					add_filter('pmpro_include_billing_address_fields', '__return_false');
					add_filter('pmpro_include_payment_information_fields', '__return_false');
					add_filter('pmpro_required_billing_fields', ['PMProGateway_payping', 'pmpro_required_billing_fields']);
				}

				add_action('wp_ajax_nopriv_payping-ins', ['PMProGateway_payping', 'pmpro_wp_ajax_payping_ins']);
				add_action('wp_ajax_payping-ins', ['PMProGateway_payping', 'pmpro_wp_ajax_payping_ins']);
			}

			/**
			 * Make sure payping is in the gateways list.
			 *
			 * @since 1.0
			 */
			public static function pmpro_gateways($gateways)
			{
				if (empty($gateways['payping'])) {
					if ( pmpro_getOption('payping_name') != '' )
						$gateways['payping'] =   pmpro_getOption('payping_name') ;
					else
						$gateways['payping'] = 'پی‌پینگ';
				}

				return $gateways;
			}

			/**
			 * Get a list of payment options that the payping gateway needs/supports.
			 *
			 * @since 1.0
			 */
			public static function getGatewayOptions()
			{
				$options = [
					'payping_merchantid',
					'payping_name',
					'currency',
					'tax_rate',
				];

				return $options;
			}

			/**
			 * Set payment options for payment settings page.
			 *
			 * @since 1.0
			 */
			public static function pmpro_payment_options($options)
			{
				//get payping options
				$payping_options = self::getGatewayOptions();

				//merge with others.
				$options = array_merge($payping_options, $options);

				return $options;
			}




			/**
			 * Remove required billing fields.
			 *
			 * @since 1.8
			 */
			public static function pmpro_required_billing_fields($fields)
			{
				unset($fields['bfirstname']);
				unset($fields['blastname']);
				unset($fields['baddress1']);
				unset($fields['bcity']);
				unset($fields['bstate']);
				unset($fields['bzipcode']);
				unset($fields['bphone']);
				unset($fields['bemail']);
				unset($fields['bcountry']);
				unset($fields['CardType']);
				unset($fields['AccountNumber']);
				unset($fields['ExpirationMonth']);
				unset($fields['ExpirationYear']);
				unset($fields['CVV']);

				return $fields;
			}

			/**
			 * Display fields for payping options.
			 *
			 * @since 1.0
			 */
			public static function pmpro_payment_option_fields($values, $gateway)
			{
				?>
                <tr class="pmpro_settings_divider gateway gateway_payping" <?php if ($gateway != 'payping') {
				?>style="display: none;"<?php
				}
				?>>
                    <td colspan="2">
						<?php echo 'تنظیمات پی‌پینگ';
						?>
                    </td>
                </tr>
                <tr class="gateway gateway_payping" <?php if ($gateway != 'payping') {
				?>style="display: none;"<?php
				}
				?>>
                    <th scope="row" valign="top">
                        <label for="payping_merchantid">توکن اتصال به پی‌پینگ:</label>
                    </th>
                    <td>
                        <input type="text" id="payping_merchantid" name="payping_merchantid" size="60" value="<?php echo esc_attr($values['payping_merchantid']);
						?>" />
                    </td>
                </tr>
                <tr class="gateway gateway_payping" <?php if ($gateway != 'payping') {
				?>style="display: none;"<?php
				}
				?>>
                    <th scope="row" valign="top">
                        <label for="payping_name">عنوان درگاه:</label>
                    </th>
                    <td>
                        <input type="text" id="payping_name" name="payping_name" size="60" value="<?php echo esc_attr($values['payping_name']);
						?>" />
                    </td>
                </tr>

				<?php

			}

			public static  function payping_status_message($code) {
				switch ($code){
					case 200 :
						return 'عملیات با موفقیت انجام شد';
						break ;
					case 400 :
						return 'مشکلی در ارسال درخواست وجود دارد';
						break ;
					case 500 :
						return 'مشکلی در سرور رخ داده است';
						break;
					case 503 :
						return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
						break;
					case 401 :
						return 'عدم دسترسی';
						break;
					case 403 :
						return 'دسترسی غیر مجاز';
						break;
					case 404 :
						return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
						break;
				}
			}

			/**
			 * Instead of change membership levels, send users to payping to pay.
			 *
			 * @since 1.8
			 */
			public static function pmpro_checkout_before_change_membership_level($user_id, $morder)
			{
				global $wpdb, $discount_code_id;

				//if no order, no need to pay
				if (empty($morder)) {
					return;
				}

				$morder->user_id = $user_id;
				$morder->saveOrder();

				//save discount code use
				if (!empty($discount_code_id)) {
					$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('".$discount_code_id."', '".$user_id."', '".$morder->id."', now())");
				}

				//$morder->Gateway->sendToTwocheckout($morder);
				global $pmpro_currency;

				$amount = intval($morder->subtotal);
				if ($pmpro_currency == 'IRR') {
					$amount /= 10;
				}

				$Message = null ;
				$data = array( 'Amount' => $amount, 'returnUrl' => admin_url('admin-ajax.php')."?action=payping-ins", 'Description' => 'پرداخت حق عضویت شماره : '.$morder->code  , 'clientRefId' => $morder->code  );
				try {
					$curl = curl_init();
					curl_setopt_array($curl, array(CURLOPT_URL => "https://api.payping.ir/v1/pay", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => array("accept: application/json", "authorization: Bearer " .pmpro_getOption('payping_merchantid'), "cache-control: no-cache", "content-type: application/json"),));
					$response = curl_exec($curl);
					$header = curl_getinfo($curl);
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
						echo "cURL Error #:" . $err;
					} else {
						if ($header['http_code'] == 200) {
							$response = json_decode($response, true);
							if (isset($response["code"]) and $response["code"] != '') {
								wp_redirect(sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]));
								exit;
							} else {
								$Message = ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';
							}
						} elseif ($header['http_code'] == 400) {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true))) ;
						} else {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . self::payping_status_message($header['http_code']) . '(' . $header['http_code'] . ')';
						}
					}
				} catch (Exception $e){
					$Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
				}

				if ( $Message != null ) {
					$morder->status = 'cancelled';
					$morder->notes = $Message;
					$morder->saveOrder();
					die($Message);
				}
			}

			public static function pmpro_wp_ajax_payping_ins()
			{
				global $gateway_environment;
				if (!isset($_GET['clientrefid']) || is_null($_GET['clientrefid'])) {
					die('تراکنش نامعتبر !!');
				}

				$oid = $_GET['clientrefid'];

				$morder = null;
				try {
					$morder = new MemberOrder($oid);
					$morder->getMembershipLevel();
					$morder->getUser();
				} catch (Exception $exception) {
					die('شماره تراکنش نامعتبر می باشد!');
				}

				$current_user_id = get_current_user_id();

				if ($current_user_id !== intval($morder->user_id)) {
					die('لطفا مجددا وارد شوید. هزینه واریز شده به حساب شما برشته می شود.');
				}


				global $pmpro_currency;
				$Authority = $_GET['refid'];
				$Amount = intval($morder->subtotal);
				if ($pmpro_currency == 'IRR') {
					$Amount /= 10;
				}


				$Message = null ;
				$data = array('refId' => $_GET['refid'], 'amount' => $Amount);
				try {
					$curl = curl_init();
					curl_setopt_array($curl, array(
						CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_POSTFIELDS => json_encode($data),
						CURLOPT_HTTPHEADER => array(
							"accept: application/json",
							"authorization: Bearer ".pmpro_getOption('payping_merchantid'),
							"cache-control: no-cache",
							"content-type: application/json",
						),
					));
					$response = curl_exec($curl);
					$err = curl_error($curl);
					$header = curl_getinfo($curl);
					curl_close($curl);
					if ($err) {
						$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$err;
					} else {
						if ($header['http_code'] == 200) {
							$response = json_decode($response, true);
							if (isset($_GET["refid"]) and $_GET["refid"] != '') {
								if (self::do_level_up($morder, $_GET["refid"])) {
									wp_redirect(pmpro_url('confirmation', '?level=' . $morder->membership_level->id));
									exit;
								}
							} else {
								$Message = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $this->status_message($header['http_code']) . '(' . $header['http_code'] . ')' ;
							}
						} elseif ($header['http_code'] == 400) {
							$Message = 'تراکنش ناموفق بود- شرح خطا : ' .  implode('. ',array_values (json_decode($response,true))) ;
						}  else {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . $this->status_message($header['http_code']) . '(' . $header['http_code'] . ')';
						}
					}
				} catch (Exception $e){
					$Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
				}
				if ( $Message != null ) {
					$morder->status = 'cancelled';
					$morder->notes = $Message .' , '.$_GET['refid'];
					$morder->saveOrder();
					wp_redirect( pmpro_url());
					exit;
				}
			}

			public static function do_level_up(&$morder, $txn_id)
			{
				global $wpdb;
				//filter for level
				$morder->membership_level = apply_filters('pmpro_inshandler_level', $morder->membership_level, $morder->user_id);

				//fix expiration date
				if (!empty($morder->membership_level->expiration_number)) {
					$enddate = "'".date('Y-m-d', strtotime('+ '.$morder->membership_level->expiration_number.' '.$morder->membership_level->expiration_period, current_time('timestamp')))."'";
				} else {
					$enddate = 'NULL';
				}

				//get discount code
				$morder->getDiscountCode();
				if (!empty($morder->discount_code)) {
					//update membership level
					$morder->getMembershipLevel(true);
					$discount_code_id = $morder->discount_code->id;
				} else {
					$discount_code_id = '';
				}

				//set the start date to current_time('mysql') but allow filters
				$startdate = apply_filters('pmpro_checkout_start_date', "'".current_time('mysql')."'", $morder->user_id, $morder->membership_level);

				//custom level to change user to
				$custom_level = [
					'user_id'         => $morder->user_id,
					'membership_id'   => $morder->membership_level->id,
					'code_id'         => $discount_code_id,
					'initial_payment' => $morder->membership_level->initial_payment,
					'billing_amount'  => $morder->membership_level->billing_amount,
					'cycle_number'    => $morder->membership_level->cycle_number,
					'cycle_period'    => $morder->membership_level->cycle_period,
					'billing_limit'   => $morder->membership_level->billing_limit,
					'trial_amount'    => $morder->membership_level->trial_amount,
					'trial_limit'     => $morder->membership_level->trial_limit,
					'startdate'       => $startdate,
					'enddate'         => $enddate, ];

				global $pmpro_error;
				if (!empty($pmpro_error)) {
					echo $pmpro_error;
					inslog($pmpro_error);
				}

				if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
					//update order status and transaction ids
					$morder->status = 'success';
					$morder->payment_transaction_id = $txn_id;
					//if( $recurring )
					//    $morder->subscription_transaction_id = $txn_id;
					//else
					$morder->subscription_transaction_id = '';
					$morder->saveOrder();

					//add discount code use
					if (!empty($discount_code) && !empty($use_discount_code)) {
						$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('".$discount_code_id."', '".$morder->user_id."', '".$morder->id."', '".current_time('mysql')."')");
					}

					//save first and last name fields
					if (!empty($_POST['first_name'])) {
						$old_firstname = get_user_meta($morder->user_id, 'first_name', true);
						if (!empty($old_firstname)) {
							update_user_meta($morder->user_id, 'first_name', $_POST['first_name']);
						}
					}
					if (!empty($_POST['last_name'])) {
						$old_lastname = get_user_meta($morder->user_id, 'last_name', true);
						if (!empty($old_lastname)) {
							update_user_meta($morder->user_id, 'last_name', $_POST['last_name']);
						}
					}

					//hook
					do_action('pmpro_after_checkout', $morder->user_id);

					//setup some values for the emails
					if (!empty($morder)) {
						$invoice = new MemberOrder($morder->id);
					} else {
						$invoice = null;
					}

					//inslog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");

					$user = get_userdata(intval($morder->user_id));
					if (empty($user)) {
						return false;
					}

					$user->membership_level = $morder->membership_level;  //make sure they have the right level info
					//send email to member
					$pmproemail = new PMProEmail();
					$pmproemail->sendCheckoutEmail($user, $invoice);

					//send email to admin
					$pmproemail = new PMProEmail();
					$pmproemail->sendCheckoutAdminEmail($user, $invoice);

					return true;
				} else {
					return false;
				}
			}
		}
	}
}
