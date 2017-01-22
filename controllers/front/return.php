<?php

/*
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2013 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.6.0
 */
class BillplzReturnModuleFrontController extends ModuleFrontController {

    public function initContent() {

        $this->display_column_left = false;
        parent::initContent();

        // Get Bills ID from GET Request
        if (isset($_GET['billplz']['id'])) {
            $id = filter_var($_GET['billplz']['id'], FILTER_SANITIZE_STRING);
        } else {
            die('Fake Request');
        }

        // Retrive API Key & Collection ID
        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $collection_id = Configuration::get('BILLPLZ_COLLECTIONID');

        // Retrieve Billplz Mode
        $mode = Configuration::get('BILLPLZ_MODE') ? 'Production' : 'Staging';

        require_once 'billplzapi.php';

        $billplz = new billplzapi;
        // Verify and get the GET Data
        $data = $billplz->check_bill($api_key, $id, $mode);

        if ($data['paid']) {

            $amount = number_format(($data['amount'] / 100), 2);
            // Check for possible fake form request. If fake, stop
            $signature = isset($_GET['signature']) ? $_GET['signature'] : 'No Valid Signature';
            $this->checkDataIntegrity($signature, $api_key, $collection_id, $data['name'], $amount);

            // Get Cart data from User Browser
            //$cart = new Cart($data['reference_1']);
            $cart = $this->context->cart;

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

            // Load customer data
            $customer = new Customer($cart->id_customer);

            if (!Validate::isLoadedObject($customer))
                die($this->module->l('Customer data cannot initialized.', 'validation'));

            // Get total cart data
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            // Get total amount paid
            $amount = (float) number_format(($data['amount'] / 100), 2);

            // Dapatkan currency ID berdasarkan ISO Code
            $currencyid = Currency::getIdByIsoCode($data['reference_2']);

            $sql_query = 'SELECT `billplz_bills_id` FROM `' . _DB_PREFIX_ . 'billplz_orders` WHERE `billplz_bills_id` = ' . $data['id'];
            $sql_result = Db::getInstance()->getRow($sql_query);

            // Save to Database
            $this->saveToDB($sql_result, $cart, $total, $data, $currencyid, $customer);
            // Redirect to specific order page
            // Tools::redirect('index.php?controller=order-detail&id_order=' . $this->module->currentOrder);
            //Redirect to history of payment
            Tools::redirect('index.php?controller=history');
        } elseif (!$data['paid']) {
            // Give user option to try again if they cancel the payment
            Tools::redirect('index.php?controller=order&step=1');
        } else {
            die('Error!');
        }
    }

    private function saveToDB($sql_result, $cart, $total, $data, $currencyid, $customer) {
        /*
         * 1. Cart ID
         * 2. Order State
         * 3. Amount Paid
         * 4. Payment Method
         * 5. Mesej
         * 6. Extra Vars (array)
         * 7. Currency Special
         * 8. Dont touch amount
         * 9. Secure Key
         * 10. Shop
         */

        // Only trigger if IPN Mode is Callback

        if (empty($sql_result) && !Configuration::get('BILLPLZ_IPNMODE')) {

            // Validate order and mark the order as Paid
            try {
                $this->module->validateOrder(
                        $cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, '(IPN Return) Billplz Bills URL: <a href="' . $data['url'] . '" target="_blank">' . $data['url'] . '</a>', null, (int) $currencyid, false, $customer->secure_key
                );
            } catch (Exception $e) {
                error_log($e);
            }
            // Insert to Billplz_Orders Database to prevent multiple callback
            Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('billplz_orders', array(
                'order_id' => pSQL((int) $cart->id), // PrestaShop standard is id_order
                'billplz_bills_id' => pSQL($data['id']),
            ));
        } elseif (!empty($sql_result) && !Configuration::get('BILLPLZ_IPNMODE')) {
            // Comment the below line if you want
            error_log('Possible of multiple return request are ignored.');
        }
    }

    /*
     * Signature using MD5, combination of API Key and Customer Email
     */

    private function checkDataIntegrity($signature, $api_key, $collection_id, $name, $amount) {

        $new_signature = md5($api_key . $collection_id . strtolower($name) . $amount);

        if ($signature != $new_signature)
            die('Invalid Request. Reason: Invalid Signature');
        else {
            // Sambung execution seperti biasa
        }
    }

}
