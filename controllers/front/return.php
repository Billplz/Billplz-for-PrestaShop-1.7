<?php

require_once _PS_MODULE_DIR_ . 'billplz/classes/BillplzConnect.php';

class BillplzReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $x_signature = trim(Configuration::get('BILLPLZ_X_SIGNATURE'));
        try {
            $data = BillplzConnect::getXSignature($x_signature);
        } catch (Exception $e) {
            header('HTTP/1.1 403 X Signature matching failed', true, 403);
            exit();
        }

        $sql = 'SELECT `cart_id` FROM `' . _DB_PREFIX_ . 'billplz` WHERE `bill_id` = "' . $data['id'] . '"';
        $result = Db::getInstance()->getRow($sql);

        if (empty($result)) {
            exit('No valid order');
        }

        $cart_id = $result['cart_id'];
        $cart = new Cart($cart_id);
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php');
        }

        if ($data['type'] === 'redirect') {
            if (!$data['paid']) {
                Tools::redirect('index.php');
            } else {
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$cart_id.'&key='.$customer->secure_key);
            }
        } else {
            if ($data['paid']) {
                if (Tools::version_compare(_PS_VERSION_, '1.7.1.0', '>')) {
                    $order = Order::getByCartId($cart_id);
                } else {
                    $order_id = Order::getOrderByCartId($cart_id);
                    $order = new Order($order_id);
                }
                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {
                    $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                }
            }
            exit;
        }
    }
}
