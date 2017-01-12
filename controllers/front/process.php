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
 * 
 * To prevent bills automatically created without user confirmation.
 * 
 * @since 3.0.1
 */
class BillplzProcessModuleFrontController extends ModuleFrontController {

    public $php_self = 'process';

    public function initContent() {
        $this->display_column_left = false;

        // Get Configuration Data

        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $collection_id = Configuration::get('BILLPLZ_COLLECTIONID');
        $deliver = Configuration::get('BILLPLZ_BILLNOTIFY');
        $mode = Configuration::get('BILLPLZ_MODE') ? 'Production' : 'Staging';

        // If data not available, put dummy data

        $amount = isset($_POST['amount']) ? $_POST['amount'] : 300;
        $description = isset($_POST['proddesc']) ? $_POST['proddesc'] : 'Test Payment';
        $email = isset($_POST['email']) ? $_POST['email'] : 'wan@wanzul-hosting.com';
        $mobile = isset($_POST['mobile']) ? $_POST['mobile'] : '60145356443';
        $name = isset($_POST['name']) ? $_POST['name'] : 'Ahmad';
        $signature = isset($_POST['signature']) ? $_POST['signature'] : 'No Valid Signature';
        $redirect_url = isset($_POST['redirecturl']) ? $_POST['redirecturl'] : 'http://fb.com/billplzplugin';
        $callback_url = isset($_POST['callbackurl']) ? $_POST['callbackurl'] : 'http://google.com';
        $reference_1 = isset($_POST['cartid']) ? $_POST['cartid'] : '5';
        $reference_2_label = "Currency";
        $reference_2 = isset($_POST['currency']) ? $_POST['currency'] : 'MYR';


        // Check for possible fake form request. If fake, stop

        $this->checkDataIntegrity($signature, $api_key, $collection_id, $name);

        // Buat verification sikit kat sini

        require_once 'billplzapi.php';
        $billplz = new billplzapi;
        $billplz->setAmount($amount)
                ->setCollection($collection_id)
                ->setDeliver($deliver)
                ->setDescription($description)
                ->setEmail($email)
                ->setMobile($mobile)
                ->setName($name)
                ->setPassbackURL($redirect_url, $callback_url)
                ->setReference_1($reference_1)
                ->setReference_1_Label("ID")
                ->setReference_2_Label("ISO")
                ->setReference_2($reference_2)
                ->create_bill($api_key, $mode);


        $url = $billplz->getURL();

        if (empty($url)) {
            error_log(var_export($billplz, true));
            Tools::redirect('http://fb.com/billplzplugin');
        } else {
            Tools::redirect($url);
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
