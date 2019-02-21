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

        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'billplz') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Billplz.Shop'));
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
                $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, "Payment success. Bill ID: {$data['id']}", array('transaction_id' => $data['id']), (int)$cart->id_currency, false, $customer->secure_key);
            }
            header('HTTP/1.1 200 OK');
        }
    }
}
