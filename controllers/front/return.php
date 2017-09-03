<?php

require_once __DIR__ . '/billplz-api.php';

class BillplzReturnModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        // Retrive API Key & Collection ID
        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $x_signature = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');

        try {
            $data = Billplz_API::getRedirectData($x_signature);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        if ($data['paid']) {

            $billplz = new Billplz_API($api_key);
            // Verify and get the GET Data
            $moreData = $billplz->check_bill($data['id']);

            $cart = $this->context->cart;
            //$cart = new Cart($moreData['reference_1']);

            // Test samada request tersebut valid atau tak
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
                Tools::redirect('index.php?controller=order&step=1');

            // Check this payment option is still available in case the customer changed his address just before the end of the checkout process
            $authorized = false;

            foreach (Module::getPaymentModules() as $module)
                if ($module['name'] == 'billplz') {
                    $authorized = true;
                    break;
                }
            if (!$authorized)
                die($this->module->l('This payment method is not available.', 'validation'));

            $sql_query = 'SELECT `billplz_bills_id` FROM `' . _DB_PREFIX_ . 'billplz_orders` WHERE `billplz_bills_id` = "' . $data['id'] . '"';
            $sql_result = Db::getInstance()->getRow($sql_query);

            // Save to Database
            $this->saveToDB($sql_result, $cart, $moreData);
            /*
             *  Load customer data
             */
            $customer = new Customer($cart->id_customer);
            Tools::redirect('index.php?controller=order-confirmation&status=paid&id_cart='. $cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
            //Tools::redirect('index.php?controller=history');
            //Tools::redirect('index.php?controller=order-detail&id_order=' . $moreData['reference_1']);
        } elseif (!$data['paid']) {
            // Give user option to try again if they cancel the payment
            Tools::redirect('index.php?controller=order&step=1');
        } else {
            die('Error!');
        }
    }

    private function saveToDB($sql_result, $cart, $moreData)
    {

        if (empty($sql_result)) {

            /*
             *  Get total paid
             */
            $total = (float) number_format(($moreData['amount'] / 100), 2);
            /*
             *  Dapatkan currency ID berdasarkan ISO Code
             */
            $currencyid = Currency::getIdByIsoCode($moreData['reference_2']);
            /*
             *  Load customer data
             */
            $customer = new Customer($cart->id_customer);

            if (!Validate::isLoadedObject($customer))
                die($this->module->l('Customer data cannot initialized.', 'validation'));

            // Validate order and mark the order as Paid

            try {
                $this->module->validateOrder(
                    $cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, '(IPN Return) Billplz Bills URL: <a href="' . $moreData['url'] . '" target="_blank">' . $moreData['url'] . '</a>', array('Bill' => $moreData['id']), (int) $currencyid, false, $customer->secure_key
                );
            } catch (Exception $e) {
                error_log($e);
            }
            // Insert to Billplz_Orders Database to prevent multiple callback
            Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('billplz_orders', array(
                'order_id' => pSQL((int) $cart->id), // PrestaShop standard is id_order
                'billplz_bills_id' => pSQL($moreData['id']),
            ));
        }
    }
}
