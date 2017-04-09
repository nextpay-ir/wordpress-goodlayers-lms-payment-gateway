<?php
/**
 * Created by NextPay.ir
 * author: Nextpay Company
 * ID: @nextpay
 * Date: 09/22/2016
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: info@nextpay.ir
 * @copyright 2017
 * @package NextPay_Gateway
 * @version 1.0
 */

    include_once('../../../wp-load.php');

    global $gdlr_lms_option, $wpdb;

	if( !empty($_GET['payment-method']) && $_GET['payment-method'] == 'nextpay' && !empty($_GET['invoice']))  {

        try {
            $client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', ['encoding' => 'UTF-8']);
        } catch (SoapFault $ex) {
            die('System Error: connection error');
        }

        try {

            $callback = plugin_dir_url(__FILE__) . 'single-nextpay.php?response=1';
            $api_key = $gdlr_lms_option['nextpay-api-key'];
            $order_id = $_GET['invoice'];
            $currency = $gdlr_lms_option['nextpay-currency-code'];

            $temp_sql = "SELECT * FROM " . $wpdb->prefix . "gdlrpayment ";
            $temp_sql .= "WHERE id = " . $_GET['invoice'];
            $result = $wpdb->get_row($temp_sql);


            $amount = round($result->price);

            if ($currency == 'IRR'){
                $amount = $amount / 10;
            }

            $parameters = [
                'api_key' => $api_key,
                'amount' => $amount,
                'order_id' => $_GET['invoice'],
                'callback_uri' => $callback,
            ];

            $result = $client->TokenGenerator($parameters);
            $result = $result->TokenGeneratorResult;
            if (intval($result->code) == -1) {
                header('Location: https://api.nextpay.org/gateway/payment/' . $result->trans_id);
            } else {
                echo "<p align=center>Bank Error $result->code.<br />Order UNSUCCSESSFUL!</p>";
                exit();
            }
        } catch (SoapFault $ex) {
            die('System Error: error in get data from bank');
        }

    }else if( !empty($_GET['response']) && $_GET['response'] == 1 ) {

        $api_key = $gdlr_lms_option['nextpay-api-key'];
        $currency = $gdlr_lms_option['nextpay-currency-code'];
        $trans_id = $_POST['trans_id'];
        $order_id = $_POST['order_id'];


        $temp_sql = "SELECT * FROM " . $wpdb->prefix . "gdlrpayment ";
        $temp_sql .= "WHERE id = " . $order_id;
        $result = $wpdb->get_row($temp_sql);


        $amount = round($result->price);

        if ($currency == 'IRR'){
            $amount = $amount / 10;
        }

        try {
            $client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', ['encoding' => 'UTF-8']);
        } catch (SoapFault $ex) {
            die('System Error: connection error');
        }

        try {
            $parameters = [
                'api_key' => $api_key,
                'trans_id'  => $trans_id,
                'amount'     => $amount,
                'order_id' => $order_id,
            ];
            $result = $client->PaymentVerification($parameters);
            $result = $result->PaymentVerificationResult;
            if(intval($result->code) == 0){
                $wpdb->update( $wpdb->prefix . 'gdlrpayment',
					array('payment_status'=>'paid', 'attachment'=>serialize($parameters), 'payment_date'=>date('Y-m-d H:i:s')),
					array('id'=>$order_id),
					array('%s', '%s', '%s'),
					array('%d')
				);

				$temp_sql  = "SELECT * FROM " . $wpdb->prefix . "gdlrpayment ";
				$temp_sql .= "WHERE id = " . $order_id;
				$result = $wpdb->get_row($temp_sql);

				$redirect_url = get_permalink($result->course_id);
            } else {
                $redirect_url = add_query_arg(array('payment-method'=> 'nextpay', 'response'=>2));
            }

            header('Location: ' . $redirect_url);

        } catch (SoapFault $ex) {
            die('System Error2: error in get data from bank');
        }
    }else if( !empty($_GET['response']) && $_GET['response'] == 2 ) {
        get_header();
        echo "<div id=\"primary\" class=\"content-area gdlr-lms-primary-wrapper\">
                    <div id=\"content\" class=\"site-content\" role=\"main\">
                        <div class=\"gdlr-lms-content\">
            <div class=\"gdlr-lms-container gdlr-lms-container\">
                <div style=\"text-align:center;\" class=\"gdlr-lms-item gdlr-lms-nextpay-payment\">
                        <span style=\"color: red;\">تراکنش ناموفق </span>
                    </div>
            </div>
            </div>
         </div>
        </div> ";
        if( !empty($gdlr_lms_option['show-sidebar']) && $gdlr_lms_option['show-sidebar'] == 'enable' ){
            get_sidebar( 'content' );
            get_sidebar();
        }

        get_footer();
    }else{
        die('Not Valid Request!');
    }
?>