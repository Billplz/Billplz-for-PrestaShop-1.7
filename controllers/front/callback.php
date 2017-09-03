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

require_once __DIR__ . '/billplz-api.php';

class BillplzCallbackModuleFrontController extends ModuleFrontController
{

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        /*
         * Due to Callback is working strangely on this Prestashop version, 
         * Callback features is disabled. Uncomment exit; with // to enable
         */
        exit;
        
        $this->display_column_left = false;
        parent::initContent();

        /*
         *  Retrive API Key & Collection ID
         */
        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $x_signature = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');

        $data = Billplz_API::getCallbackData($x_signature);

        if ($data['paid']) {

            $billplz = new Billplz_API($api_key);
            // Verify and get the Callback Data
            $moreData = $billplz->check_bill($data['id']);

            // Tak boleh guna $this->context sebab ini server side
            $cart = new Cart($moreData['reference_1']);
            // Test samada request tersebut valid atau tak
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
                die();

            // Check wether the bills are in database yet or not
            $sql_query = 'SELECT `billplz_bills_id` FROM `' . _DB_PREFIX_ . 'billplz_orders` WHERE `billplz_bills_id` = "' . $data['id'] . '"';
            $sql_result = Db::getInstance()->getRow($sql_query);

            // Save the to Database
            $this->saveToDB($sql_result, $cart, $moreData);
        } elseif (!$data['paid']) {
            // Nothing to do
        } else {
            error_log("Something strange on Callback Request. Please check.");
        }

        echo "ALL IS WELL";
        exit;
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
                die();

            // Validate order and mark the order as Paid
            try {
                $this->module->validateOrder(
                    $cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, '(IPN Callback) Billplz Bills URL: <a href="' . $moreData['url'] . '" target="_blank">' . $moreData['url'] . '</a>', array('Bill' => $moreData['id']), (int) $currencyid, false, $customer->secure_key
                );
            } catch (\Exception $e) {
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
