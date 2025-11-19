<?php
/**
 * Plugin Name:       PayPing Gateway for Paid Memberships Pro
 * Description:       درگاه پرداخت پی‌پینگ برای افزونه Paid Memberships Pro
 * Version:           1.3.1
 * Author:            Your Name
 * Text Domain:       payping-pmpro
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PMProGateway_payping extends PMProGateway {

    const GATEWAY_ID = 'payping';

    public function __construct( $gateway = null ) {
        $this->gateway = self::GATEWAY_ID;
        parent::__construct( $this->gateway );
        $this->gateway_environment = pmpro_getOption( 'gateway_environment' );
    }

    /* ------------------------------------------------------------------ */
    /*  ثبت درگاه و تنظیمات                                              */
    /* ------------------------------------------------------------------ */
    public static function init() {
        if ( ! function_exists( 'add_filter' ) ) {
            return;
        }

        // اضافه کردن به لیست درگاه‌ها
        add_filter( 'pmpro_gateways', [ __CLASS__, 'pmpro_gateways' ] );
        add_filter( 'pmpro_payment_options', [ __CLASS__, 'pmpro_payment_options' ] );
        add_filter( 'pmpro_payment_option_fields', [ __CLASS__, 'pmpro_payment_option_fields' ], 10, 2 );

        // مخفی کردن فیلدهای کارت اعتباری
        add_filter( 'pmpro_include_billing_address_fields', '__return_false' );
        add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
        add_filter( 'pmpro_required_billing_fields', [ __CLASS__, 'pmpro_required_billing_fields' ] );

        // هندل کردن بازگشت از درگاه
        add_action( 'wp_ajax_payping-ins', [ __CLASS__, 'handle_callback' ] );
        add_action( 'wp_ajax_nopriv_payping-ins', [ __CLASS__, 'handle_callback' ] );
    }

    public static function pmpro_gateways( $gateways ) {
        $gateways[ self::GATEWAY_ID ] = pmpro_getOption( 'payping_name' ) ?: __( 'پی‌پینگ', 'payping-pmpro' );
        return $gateways;
    }

    public static function pmpro_payment_options( $options ) {
        return array_merge( $options, [ 'payping_merchantid', 'payping_name' ] );
    }

    public static function pmpro_payment_option_fields( $values, $gateway ) {
        if ( $gateway !== self::GATEWAY_ID ) {
            return;
        }
        ?>
        <tr class="pmpro_settings_divider">
            <td colspan="2"><strong><?php esc_html_e( 'تنظیمات درگاه پی‌پینگ', 'payping-pmpro' ); ?></strong></td>
        </tr>
        <tr>
            <th scope="row"><label for="payping_merchantid"><?php esc_html_e( 'توکن پی‌پینگ:', 'payping-pmpro' ); ?></label></th>
            <td>
                <input type="text" id="payping_merchantid" name="payping_merchantid" size="60"
                       value="<?php echo esc_attr( $values['payping_merchantid'] ?? '' ); ?>" />
                <p class="description"><?php esc_html_e( 'توکن Bearer را از پنل پی‌پینگ دریافت کنید.', 'payping-pmpro' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="payping_name"><?php esc_html_e( 'عنوان درگاه:', 'payping-pmpro' ); ?></label></th>
            <td>
                <input type="text" id="payping_name" name="payping_name" size="60"
                       value="<?php echo esc_attr( $values['payping_name'] ?? '' ); ?>" />
            </td>
        </tr>
        <?php
    }

    public static function pmpro_required_billing_fields( $fields ) {
        $remove = [ 'bfirstname', 'blastname', 'baddress1', 'bcity', 'bstate', 'bzipcode', 'bphone', 'bemail', 'bcountry', 'CardType', 'AccountNumber', 'ExpirationMonth', 'ExpirationYear', 'CVV' ];
        foreach ( $remove as $key ) {
            unset( $fields[ $key ] );
        }
        return $fields;
    }

    /* ------------------------------------------------------------------ */
    /*  پردازش پرداخت در زمان تسویه حساب                                  */
    /* ------------------------------------------------------------------ */
    public function process( &$order ) {
        if ( empty( $order ) || ! $order->code ) {
            return false;
        }

        $token = trim( pmpro_getOption( 'payping_merchantid' ) );
        if ( empty( $token ) ) {
            $order->error = __( 'توکن پی‌پینگ تنظیم نشده است.', 'payping-pmpro' );
            return false;
        }

        $amount = $this->convert_to_rials( $order->InitialPayment );

        $payload = [
            'amount'        => (int) $amount,
            'payerIdentity' => (string) $order->user_id,
            'payerName'     => trim( $order->FirstName . ' ' . $order->LastName ),
            'description'   => sprintf( __( 'پرداخت عضویت – سفارش %s', 'payping-pmpro' ), $order->code ),
            'clientRefId'   => $order->code,
            'returnUrl'     => admin_url( 'admin-ajax.php?action=payping-ins' ),
            'isReversible'  => true,
        ];

        $response = wp_remote_post( 'https://api.payping.ir/v3/pay', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            $order->error = 'خطا در ارتباط با پی‌پینگ: ' . $response->get_error_message();
            pmpro_log( 'PayPing connection error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ) );

        if ( $code !== 200 || empty( $body->paymentCode ) || empty( $body->url ) ) {
            $msg = $body->metaData->errors[0]->message ?? __( 'خطای نامشخص از پی‌پینگ', 'payping-pmpro' );
            $order->error = $msg;
            pmpro_log( "PayPing create failed (HTTP $code): " . wp_json_encode( $body ) );
            return false;
        }

        // ذخیره کد پرداخت برای تأیید در callback
        $order->notes .= "\n##PAYPING_CODE##:" . sanitize_text_field( $body->paymentCode ) . "\n";
        $order->saveOrder();

        wp_redirect( $body->url );
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  بازگشت از درگاه (Callback)                                        */
    /* ------------------------------------------------------------------ */
    public static function handle_callback() {
        if ( empty( $_REQUEST['status'] ) || empty( $_REQUEST['data'] ) ) {
            wp_die( 'داده‌های بازگشت نامعتبر است.' );
        }

        $status = sanitize_text_field( $_REQUEST['status'] );
        $data   = json_decode( wp_unslash( $_REQUEST['data'] ), true );

        if ( ! $data || empty( $data['clientRefId'] ) ) {
            wp_die( 'اطلاعات پرداخت معتبر نیست.' );
        }

        $order_code = sanitize_text_field( $data['clientRefId'] );
        $order      = pmpro_getOrderByCode( $order_code );

        if ( ! $order ) {
            wp_die( 'سفارش یافت نشد.' );
        }

        // بررسی تطابق paymentCode
        preg_match( '/##PAYPING_CODE##:(.*?)\n/', $order->notes, $m );
        if ( ( $m[1] ?? '' ) !== ( $data['paymentCode'] ?? '' ) ) {
            $order->status = 'error';
            $order->notes .= "\n[PayPing] کد پرداخت مطابقت ندارد.";
            $order->saveOrder();
            wp_redirect( pmpro_url( 'invoice', [ 'invoice' => $order_code ] ) );
            exit;
        }

        // کاربر لغو کرده
        if ( $status !== '1' ) {
            $order->status = 'cancelled';
            $order->notes .= "\n[PayPing] پرداخت لغو شد.";
            $order->saveOrder();
            wp_redirect( pmpro_url( 'invoice', [ 'invoice' => $order_code ] ) );
            exit;
        }

        // تأیید تراکنش با سرور پی‌پینگ
        $token = trim( pmpro_getOption( 'payping_merchantid' ) );
        $verify = wp_remote_post( 'https://api.payping.ir/v3/pay/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'paymentRefId' => $data['paymentRefId'],
                'amount'       => $data['amount'],
                'paymentCode'  => $data['paymentCode'],
            ] ),
            'timeout' => 30,
        ] );

        $verified = false;
        if ( ! is_wp_error( $verify ) ) {
            $vcode = wp_remote_retrieve_response_code( $verify );
            $vbody = json_decode( wp_remote_retrieve_body( $verify ), true );
            if ( $vcode === 200 || ( $vcode === 409 && ( $vbody['metaData']['code'] ?? 0 ) == 110 ) ) {
                $verified = true;
            }
        }

        if ( ! $verified ) {
            $order->status = 'error';
            $order->notes .= "\n[PayPing] تأیید پرداخت ناموفق بود.";
            $order->saveOrder();
            wp_redirect( pmpro_url( 'checkout', [ 'level' => $order->membership_level->id, 'error' => 'پرداخت تأیید نشد.' ] ) );
            exit;
        }

        // پرداخت موفق
        $order->status                 = 'success';
        $order->payment_transaction_id = sanitize_text_field( $data['paymentRefId'] );
        $order->notes                 .= "\n[PayPing] پرداخت موفق – کد پیگیری: " . $data['paymentRefId'];
        $order->saveOrder();

        // تغییر سطح عضویت کاربر
        $level_id = $order->membership_level->id;
        pmpro_changeMembershipLevel( $level_id, $order->user_id, 'success', $order->id );

        // ارسال ایمیل‌های PMPro
        do_action( 'pmpro_after_checkout', $order->user_id, $order );

        wp_redirect( pmpro_url( 'confirmation', [ 'level' => $level_id ] ) );
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  تبدیل مبلغ به ریال (PayPing فقط ریال قبول می‌کند)                 */
    /* ------------------------------------------------------------------ */
    private function convert_to_rials( $amount ) {
        $currency = strtoupper( pmpro_getOption( 'currency' ) );
        if ( $currency === 'IRR' ) {
            return (int) round( $amount );           // ریال
        }
        return (int) round( $amount * 10 );          // تومان → ریال
    }
}

/* ------------------------------------------------------------------ */
/*  ثبت نهایی درگاه                                                    */
/* ------------------------------------------------------------------ */
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'PMProGateway' ) ) {
        PMProGateway_payping::init();

        // ثبت کلاس درگاه (مهم برای PMPro ≥2.0)
        add_filter( 'pmpro_gateways_classes', function ( $classes ) {
            $classes[ PMProGateway_payping::GATEWAY_ID ] = 'PMProGateway_payping';
            return $classes;
        } );
    }
} );
