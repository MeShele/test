<?php
class FreedomPayCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;
    public $content_only = true;
    private $logFile;

    public function __construct()
    {
        parent::__construct();
        $this->logFile = dirname(__FILE__).'/../../freedompay.log';
    }

    public function postProcess()
    {
        $this->log('Callback received: ' . print_r($_POST, true));
        
        // Get session token
        $session_token = Tools::getValue('session_token');
        if (!$session_token) {
            $this->log('Missing session token in callback', true);
            die('MISSING_SESSION_TOKEN');
        }
        
        // Find cart ID by session token
        $cart_id = (int)Db::getInstance()->getValue('
            SELECT cart_id
            FROM '._DB_PREFIX_.'freedompay_sessions
            WHERE session_token = "'.pSQL($session_token).'"
        ');
        
        if (!$cart_id) {
            $this->log('Invalid session token: ' . $session_token, true);
            die('INVALID_SESSION_TOKEN');
        }
        
        $this->log("Found cart ID: $cart_id for session token: $session_token");
        
        // Validate signature
        if (!$this->validateSignature($_POST)) {
            $this->log('Invalid signature', true);
            die('INVALID_SIGNATURE');
        }

        // Process payment
        if (isset($_POST['pg_result'])) {
            $result = (int)$_POST['pg_result'];
            
            $this->log("Processing payment for cart: $cart_id, result: $result");
            
            // Create order if successful
            if ($result === 1) {
                $orderId = $this->createOrder($cart_id);
                if ($orderId) {
                    // Clean up session token
                    Db::getInstance()->delete(
                        'freedompay_sessions',
                        'session_token = "'.pSQL($session_token).'"'
                    );
                    
                    // Redirect to confirmation page
                    $redirectUrl = $this->context->link->getPageLink(
                        'order-confirmation',
                        true,
                        null,
                        [
                            'id_cart' => $cart_id,
                            'id_module' => $this->module->id,
                            'id_order' => $orderId,
                            'key' => $this->context->customer->secure_key
                        ]
                    );
                    $this->log("Redirecting to confirmation: $redirectUrl");
                    Tools::redirect($redirectUrl);
                }
            } else {
                $this->log("Payment failed for cart: $cart_id");
                // Redirect to error page
                Tools::redirect($this->context->link->getModuleLink(
                    'freedompay',
                    'payment',
                    ['error' => 1],
                    true
                ));
            }
            
            $this->log("Payment processed for cart: $cart_id");
        } else {
            $this->log('Missing parameters in callback', true);
        }

        die('OK');
    }

    private function validateSignature($data)
    {
        if (empty($data['pg_sig'])) {
            $this->log('Missing pg_sig parameter', true);
            return false;
        }
        
        // Remove session_token from signature calculation
        unset($data['session_token']);
        
        $signature = $data['pg_sig'];
        unset($data['pg_sig']);
        
        $secret = Configuration::get('FREEDOMPAY_MERCHANT_SECRET');
        ksort($data);
        $signString = 'callback.php;' . implode(';', array_values($data)) . ';' . $secret;
        
        $this->log("Signature string: $signString");
        $this->log("Received signature: $signature");
        $this->log("Generated signature: " . md5($signString));
        
        return $signature === md5($signString);
    }
    
    private function createOrder($cartId)
    {
        $this->log("Creating order for cart: $cartId");
        
        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart)) {
            $this->log("Cart not found: $cartId", true);
            return false;
        }
        
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->log("Customer not found for cart: $cartId", true);
            return false;
        }
        
        // Check if order already exists
        $orderId = Order::getOrderByCartId($cartId);
        if ($orderId) {
            $this->log("Order already exists for cart: $cartId, order ID: $orderId");
            return $orderId;
        }
        
        // Create order
        $this->module->validateOrder(
            $cartId,
            Configuration::get('PS_OS_PAYMENT'),
            $cart->getOrderTotal(true, Cart::BOTH),
            'FreedomPay',
            null,
            [],
            $cart->id_currency,
            false,
            $customer->secure_key,
            null,
            null,
            null,
            false,
            true // Send confirmation email
        );
        
        $orderId = $this->module->currentOrder;
        $this->log("Order created successfully: $orderId");
        
        // Add transaction ID to order payment
        if (isset($_POST['pg_payment_id'])) {
            $order = new Order($orderId);
            if (Validate::isLoadedObject($order)) {
                $payments = $order->getOrderPayments();
                if (count($payments) > 0) {
                    $payment = $payments[0];
                    $payment->transaction_id = pSQL($_POST['pg_payment_id']);
                    $payment->save();
                    $this->log("Added transaction ID to payment: ".$_POST['pg_payment_id']);
                }
            }
        }
        
        return $orderId;
    }
    
    /**
     * Log messages to file
     */
    private function log($message, $isError = false)
    {
        $prefix = date('[Y-m-d H:i:s]') . ($isError ? ' [ERROR] ' : ' ');
        file_put_contents($this->logFile, $prefix . $message . PHP_EOL, FILE_APPEND);
    }
}