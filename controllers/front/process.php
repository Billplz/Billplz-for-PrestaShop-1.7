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

class BillplzProcessModuleFrontController extends ModuleFrontController
{

    public $php_self = 'process';

    public function initContent()
    {
        $this->display_column_left = false;

        // Get Configuration Data

        $api_key = Configuration::get('BILLPLZ_APIKEY');
        $collection_id = Configuration::get('BILLPLZ_COLLECTIONID');
        $deliver = Configuration::get('BILLPLZ_BILLNOTIFY');

        // If data not available, put dummy data

        $amount = isset($_POST['amount']) ? $_POST['amount'] : 300;
        $description = isset($_POST['proddesc']) ? $_POST['proddesc'] : 'Test Payment';
        $email = isset($_POST['email']) ? $_POST['email'] : 'wan@wanzul-hosting.com';
        $mobile = isset($_POST['mobile']) ? $_POST['mobile'] : '60145356443';
        $name = isset($_POST['name']) ? $_POST['name'] : 'Ahmad';
        $hash = isset($_POST['hash']) ? $_POST['hash'] : 'No Valid Hash';
        $redirect_url = isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://';
        $callback_url = isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://';
        $reference_1 = isset($_POST['cartid']) ? $_POST['cartid'] : '5';
        $reference_2 = isset($_POST['currency']) ? $_POST['currency'] : '';

        $redirect_url .= $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=return';
        $callback_url .= $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=callback';

        // Check for possible fake form request. If fake, stop
        $this->checkDataIntegrity($hash, $reference_1, $amount);

        $billplz = new Billplz_API(trim($api_key));
        $billplz
            ->setCollection($collection_id)
            ->setName($name)
            ->setAmount($amount)
            ->setDeliver($deliver)
            ->setMobile($mobile)
            ->setEmail($email)
            ->setDescription($description)
            ->setReference_1($reference_1)
            ->setReference_1_Label('Cart ID')
            ->setReference_2($reference_2)
            ->setPassbackURL($callback_url, $redirect_url)
            ->create_bill(true);

        $url = $billplz->getURL();


        if (empty($url)) {
            exit('Something went wrong! ' . $billplz->getErrorMessage());
        } else {
            Tools::redirect($url);
        }
    }

    private function checkDataIntegrity($old_hash, $cart_id, $amount)
    {
        $x_signature = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');
        $raw_string = $cart_id . $amount;
        $filtered_string = preg_replace("/[^a-zA-Z0-9]+/", "", $raw_string);
        $hash = hash_hmac('sha256', $filtered_string, $x_signature);

        if ($hash != $old_hash)
            die('Invalid Request. Reason: Input has been tempered');
    }
}
