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
class BillplzCallbackModuleFrontController extends ModuleFrontController {

    public function __construct() {
        $this->controller_type = 'modulefront';

        $this->module = Module::getInstanceByName(Tools::getValue('module'));
        if (!$this->module->active) {
            error_log('Billplz Payment Gateway module not active');
        }
        $this->page_name = 'module-' . $this->module->name . '-' . Dispatcher::getInstance()->getController();

        parent::__construct();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent() {
        $this->display_column_left = false;
        parent::initContent();
    }

    public function process() {

        // Get Billplz Bills ID
        $id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);


        // Retrive API Key & Collection ID
        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $collection_id = Configuration::get('BILLPLZ_COLLECTIONID');

        // Retrieve Billplz Mode
        $mode = Configuration::get('BILLPLZ_MODE') ? 'Production' : 'Staging';

        require_once 'billplzapi.php';

        $billplz = new billplzapi;
        // Verify and get the Callback Data
        $data = $billplz->check_bill($api_key, $id, $mode);
        if ($data['paid']) {

            // Check for possible fake form request. If fake, stop
            $signature = isset($_GET['signature']) ? $_GET['signature'] : 'No Valid Signature';
            $this->checkDataIntegrity($signature, $api_key, $collection_id, $data['name']);

            // Tak boleh guna $this->context sebab ini server side
            $cart = new Cart($data['reference_1']);
            // Test samada request tersebut valid atau tak
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
                die();

            // Load customer data
            $customer = new Customer($cart->id_customer);

            if (!Validate::isLoadedObject($customer))
                die();

            // Get total cart data
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            // Get total amount paid
            $amount = (float) number_format(($data['amount'] / 100), 2);

            // Dapatkan currency ID berdasarkan ISO Code
            $currencyid = Currency::getIdByIsoCode($data['reference_2']);

            // Check wether the bills are in database yet or not
            $sql_query = 'SELECT `billplz_bills_id` FROM `' . _DB_PREFIX_ . 'billplz_orders` WHERE `billplz_bills_id` = ' . $data['id'];
            $sql_result = Db::getInstance()->getRow($sql_query);
            
            // Save the to Database
            $this->saveToDB($sql_result, $cart, $total, $data, $currencyid, $customer);
        } elseif (!$data['paid']) {
            // Nothing to do
        } else {
            error_log("Something strange on Callback Request. Please check.");
        }

        echo "ALL IS WELL";
        die();
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

        if (empty($sql_result) && Configuration::get('BILLPLZ_IPNMODE')) {

            // Validate order and mark the order as Paid
            try {
                $this->module->validateOrder(
                        (int) $cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, '(IPN Callback) Billplz Bills URL: <a href="' . $data['url'] . '" target="_blank">' . $data['url'] . '</a>', array(), (int) $currencyid, false, $customer->secure_key
                );
            } catch (Exception $e) {
                error_log($e);
            }
            // Insert to Billplz_Orders Database to prevent multiple callback
            Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('billplz_orders', array(
                'order_id' => pSQL((int) $cart->id), // PrestaShop standard is id_order
                'billplz_bills_id' => pSQL($data['id']),
            ));
        } elseif (!empty($sql_result) && Configuration::get('BILLPLZ_IPNMODE')) {
            // Comment the below line if you want
            error_log('Possible of multiple callback request are ignored.');
        }
    }

    /*
     * Signature using MD5, combination of API Key and Customer Email
     */

    private function checkDataIntegrity($signature, $api_key, $collection_id, $name) {

        $new_signature = md5($api_key . $collection_id . strtolower($name));

        if ($signature != $new_signature)
            die('Invalid Request. Reason: Invalid Signature');
        else {
            // Sambung execution seperti biasa
        }
    }

}
