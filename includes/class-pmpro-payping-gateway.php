<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Include WordPress core
if (!defined('ABSPATH')) {
    /** Set up WordPress environment */
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
}

// Make sure PMPro is loaded
if (!class_exists('PMProGateway')) {
    return;
}

/**
 * PayPing Payment Gateway integration for Paid Memberships Pro
 */
class PMProGateway_payping extends PMProGateway {
    /**
     * @var string $gateway
     */
    protected $gateway;

    /**
     * @var string $gateway_environment
     */
    protected $gateway_environment;

    /**
     * Constructor
     */
    public function __construct($gateway = null) {
        $this->gateway = $gateway;
        $this->gateway_environment = function_exists('pmpro_getOption') ? pmpro_getOption('gateway_environment') : '';
        return $this->gateway;
    }

    public static function init() {
        // Check if WordPress core functions are available
        if (!function_exists('add_filter') || !function_exists('add_action')) {
            return;
        }

        //make sure PayPing is a gateway option
        add_filter('pmpro_gateways', [__CLASS__, 'pmpro_gateways']);

        //add fields to payment settings
        add_filter('pmpro_payment_options', [__CLASS__, 'pmpro_payment_options']);
        add_filter('pmpro_payment_option_fields', [__CLASS__, 'pmpro_payment_option_fields'], 10, 2);
        
        if (function_exists('pmpro_getOption')) {
            $gateway = pmpro_getOption('gateway');

            if ($gateway == 'payping') {
                add_action('pmpro_checkout_before_change_membership_level', [__CLASS__, 'pmpro_checkout_before_change_membership_level'], 10, 2);
                add_filter('pmpro_include_billing_address_fields', '__return_false');
                add_filter('pmpro_include_payment_information_fields', '__return_false');
                add_filter('pmpro_required_billing_fields', [__CLASS__, 'pmpro_required_billing_fields']);
            }
        }

        add_action('wp_ajax_nopriv_payping-ins', [__CLASS__, 'pmpro_wp_ajax_payping_ins']);
        add_action('wp_ajax_payping-ins', [__CLASS__, 'pmpro_wp_ajax_payping_ins']);
    }

    public static function pmpro_gateways($gateways) {
        if (empty($gateways['payping'])) {
            if (function_exists('pmpro_getOption') && pmpro_getOption('payping_name') != '') {
                $gateways['payping'] = pmpro_getOption('payping_name');
            } else {
                $gateways['payping'] = 'پی‌پینگ';
            }
        }
        return $gateways;
    }

    public static function getGatewayOptions() {
        $options = [
            'payping_merchantid',
            'payping_name',
            'currency',
            'tax_rate',
        ];
        return $options;
    }

    public static function pmpro_payment_options($options) {
        //get payping options
        $payping_options = self::getGatewayOptions();
        //merge with others.
        $options = array_merge($payping_options, $options);
        return $options;
    }

    public static function pmpro_required_billing_fields($fields) {
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

    public static function pmpro_payment_option_fields($values, $gateway) {
        ?>
        <tr class="pmpro_settings_divider gateway gateway_payping" <?php if ($gateway != 'payping') { ?>style="display: none;"<?php } ?>>
            <td colspan="2">
                <?php echo wp_kses_post('تنظیمات پی‌پینگ'); ?>
            </td>
        </tr>
        <tr class="gateway gateway_payping" <?php if ($gateway != 'payping') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="payping_merchantid">توکن اتصال به پی‌پینگ:</label>
            </th>
            <td>
                <input type="text" id="payping_merchantid" name="payping_merchantid" size="60" value="<?php echo esc_attr($values['payping_merchantid']); ?>" />
            </td>
        </tr>
        <tr class="gateway gateway_payping" <?php if ($gateway != 'payping') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="payping_name">عنوان درگاه:</label>
            </th>
            <td>
                <input type="text" id="payping_name" name="payping_name" size="60" value="<?php echo esc_attr($values['payping_name']); ?>" />
            </td>
        </tr>
        <?php
    }

    public static function payping_status_message($code) {
        switch ($code) {
            case 200:
                return 'عملیات با موفقیت انجام شد';
            case 400:
                return 'مشکلی در ارسال درخواست وجود دارد';
            case 500:
                return 'مشکلی در سرور رخ داده است';
            case 503:
                return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
            case 401:
                return 'عدم دسترسی';
            case 403:
                return 'دسترسی غیر مجاز';
            case 404:
                return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
            default:
                return 'خطای نامشخص';
        }
    }

    public static function pmpro_checkout_before_change_membership_level($user_id, $morder)
    {
        global $wpdb, $discount_code_id;

        //if no order, no need to pay
        if (empty($morder)) {
            return;
        }
        $morder->status = 'pending';
        $morder->user_id = $user_id;
        $morder->saveOrder();

        //save discount code use
        if (!empty($discount_code_id)) {
            // Prepare the SQL query with placeholders
            $query = $wpdb->prepare(
                "INSERT INTO {$wpdb->pmpro_discount_codes_uses} (code_id, user_id, order_id, timestamp) VALUES (%d, %d, %d, now())",
                $discount_code_id,
                $user_id,
                $morder->id
            );

            // Execute the prepared query
            $wpdb->query($query);
        }

        global $pmpro_currency;

        $amount = intval($morder->subtotal);
        if ($pmpro_currency == 'IRR') {
            $amount /= 10;
        }
        
        $Message = null;
        $data = array(
            'amount' => $amount,
            'returnUrl' => admin_url('admin-ajax.php') . "?action=payping-ins",
            'payerIdentity' => $morder->user_id,
            'payerName' => wp_get_current_user()->display_name,
            'description' => 'پرداخت حق عضویت شماره : ' . $morder->code,
            'clientRefId' => $morder->code,
            'nationalCode' => ''
        );

        $args = array(
            'body' => json_encode($data),
            'timeout' => '45',
            'redirection' => '5',
            'httpsversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'X-Platform' => 'paid-membership-pro',
                'X-Platform-Version' => '1.0.0',
                'Authorization' => 'Bearer ' . pmpro_getOption('payping_merchantid'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'cookies' => array()
        );

        $response = wp_remote_post('https://api.payping.ir/v3/pay', $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $Message = 'خطا در اتصال به درگاه: ' . $error;
            $morder->status = 'error';
            $morder->notes = $Message;
            $morder->saveOrder();
            wp_redirect(pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&error=' . urlencode($Message)));
            exit;
        }

        $http_status = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response));
        if ($http_status != 200 || empty($result)) {
            if ($http_status == 400 && !empty($result->metaData->errors)) {
                $Message = '';
                foreach ($result->metaData->errors as $error) {
                    $Message .= $error->message . '<br/>';
                }
            } else {
                $Message = self::payping_status_message($http_status);
            }
            $morder->status = 'error';
            $morder->notes = $Message;
            $morder->saveOrder();
            wp_redirect(pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&error=' . urlencode($Message)));
            exit;
        }
        
        
        if (!empty($result->url)) {
            wp_redirect($result->url);
            exit;
        }
    }

    public static function pmpro_wp_ajax_payping_ins()
    {
        $status = sanitize_text_field($_REQUEST['status']);
        $paypingResponse = stripslashes($_REQUEST['data']);
        
		$responseData = json_decode($paypingResponse, true);
        $order_code = $responseData['clientRefId'];
        $morder = new MemberOrder($order_code);
		$morder->getMembershipLevel();
		$morder->getUser();
        
        if ($status == '0') {
            $Message = 'تراکنش توسط کاربر لغو شد.';
            $morder->status = "error";
            $morder->notes = $Message;
            $morder->cancel();
            $morder->saveOrder();
            wp_redirect(add_query_arg('invoice', $order_code, site_url('/checkout-pmp/membership-orders/')));

            exit;
        } else if ($status == '1') {
            $verify_data = array(
                'paymentRefId' => $responseData['paymentRefId'],
                'amount' => $responseData['amount']
            );
            $response = wp_remote_post('https://api.payping.ir/v3/pay/verify', array(
                'body'        => wp_json_encode($verify_data),
                'headers'     => array(
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . pmpro_getOption('payping_merchantid'),
                    'Cache-Control' => 'no-cache',
                    'Content-Type'  => 'application/json'
                ),
                'timeout'     => 30,
                'redirection' => 10,
            ));
            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                $Message = 'خطا در تایید تراکنش: ' . $error;
                $morder->status = "error";
                $morder->notes = $Message;
                $morder->saveOrder();
                wp_redirect(add_query_arg('invoice', $order_code, site_url('/checkout-pmp/membership-orders/')));
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                
                if ($status_code == 200) {
                    if (isset($responseData['paymentRefId']) && $responseData['paymentRefId'] != '') {
                        if (self::do_level_up($morder, $responseData['paymentRefId'])) {
                            wp_redirect(pmpro_url('confirmation', '?level=' . $morder->membership_level->id));
                            exit;
                        }
                    } else {
                        $Message = 'متاسانه سامانه قادر به دریافت کد پیگیری نمی باشد!' ;
                    }
                    
                } elseif ($status_code == 409) {
                    $Message = 'این تراکنش قبلا تایید و پرداخت شده است - کد پیگیری: ' . $responseData['paymentRefId'];
                    $morder->status = "success";
                    $morder->notes = $Message;
                    $morder->saveOrder();
                    wp_redirect(pmpro_url('confirmation', '?level=' . $morder->membership_level->id));
                    exit;
                } else {
                    $Message = 'خطا در تایید تراکنش';
                    $morder->status = "error";
                    $morder->notes = $Message;
                    $morder->saveOrder();
                    wp_redirect(pmpro_url("checkout", "?level=" . $morder->membership_id . "&error=" . urlencode($Message)));
                    exit;
                }
            }
            
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
            'enddate'         => $enddate,
        ];
       
        global $pmpro_error;
        if (!empty($pmpro_error)) {
            echo $pmpro_error;
            inslog($pmpro_error);
        }
        
        if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
            //update order status and transaction ids
            $morder->status = 'success';
            $morder->payment_transaction_id = $txn_id;
            $morder->subscription_transaction_id = '';
            $morder->notes = 'تراکنش موفق - کد پیگیری: ' . $txn_id;
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
            //do_action('pmpro_after_checkout', $morder->user_id);

            //setup some values for the emails
            if (!empty($morder)) {
                $invoice = new MemberOrder($morder->id);
            } else {
                $invoice = null;
            }

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
