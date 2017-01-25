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
 * @copyright  2007-2013 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *
 * Author : wan@wanzul-hosting.com
 */

// Respect to PrestaShop 1.7 requirements
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;

class Billplz extends PaymentModule {

    public function __construct() {
        $this->name = 'billplz';
        $this->tab = 'payments_gateways';
        $this->version = '3.0';
        $this->author = 'Wan Zulkarnain';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        //$this->controllers = array('callback', 'process', 'return');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Billplz Payment Gateway');
        $this->description = $this->l('Fair Payment Software. Accept FPX payment.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('Billplz'))
            $this->warning = $this->l('No name provided');
    }

    public function install() {

        // Create tables to store status data to prevent multiple callback
        Db::getInstance()->execute('
				CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'billplz_orders` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
                                `order_id` int(11) NOT NULL,
				`billplz_bills_id` varchar(255) NOT NULL,
				PRIMARY KEY (`id`)
                                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
				');

        // Pre-set the default values
        Configuration::updateValue('BILLPLZ_MODE', true);
        Configuration::updateValue('BILLPLZ_IPNMODE', true);
        Configuration::updateValue('BILLPLZ_BILLNOTIFY', false);

        return parent::install() &&
                Configuration::updateValue('Billplz', 'Billplz MODULE') &&
                $this->registerHook('paymentOptions') &&
                Configuration::updateValue('PS_OS_BILLPLZ', $this->_create_order_state('Billplz Payment Gateway', null, 'blue'));
    }

    public function uninstall() {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'billplz_orders`;');
        return parent::uninstall() &&
                Configuration::deleteByName('BILLPLZ_APIKEY') &&
                Configuration::deleteByName('BILLPLZ_COLLECTIONID') &&
                Configuration::deleteByName('BILLPLZ_MODE') &&
                Configuration::deleteByName('BILLPLZ_IPNMODE') &&
                Configuration::deleteByName('BILLPLZ_BILLNOTIFY');
    }

    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {

            // Update value bila tekan Submit dekat Back Office

            Configuration::updateValue('BILLPLZ_APIKEY', Tools::getValue('BILLPLZ_APIKEY'));
            Configuration::updateValue('BILLPLZ_COLLECTIONID', Tools::getValue('BILLPLZ_COLLECTIONID'));
            Configuration::updateValue('BILLPLZ_MODE', Tools::getValue('BILLPLZ_MODE'));
            Configuration::updateValue('BILLPLZ_IPNMODE', Tools::getValue('BILLPLZ_IPNMODE'));
            Configuration::updateValue('BILLPLZ_BILLNOTIFY', Tools::getValue('BILLPLZ_BILLNOTIFY'));

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        return $output . $this->displayForm();
    }

    public function displayForm() {
        // Get default Language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API Secret Key'),
                    'name' => 'BILLPLZ_APIKEY',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Collection ID'),
                    'name' => 'BILLPLZ_COLLECTIONID',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Production Mode'),
                    'name' => 'BILLPLZ_MODE',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'Production',
                            'value' => true,
                            'label' => $this->l('Production')
                        ),
                        array(
                            'id' => 'Staging',
                            'value' => '0', // False
                            'label' => $this->l('Staging')
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('IPN Type (Yes: Callback, No: Return)'),
                    'name' => 'BILLPLZ_IPNMODE',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'callback',
                            'value' => true,
                            'label' => $this->l('Callback')
                        ),
                        array(
                            'id' => 'return',
                            'value' => '0', // False
                            'label' => $this->l('Return')
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Billplz Notification'),
                    'name' => 'BILLPLZ_BILLNOTIFY',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'yes',
                            'value' => true,
                            'label' => $this->l('Notify')
                        ),
                        array(
                            'id' => 'no',
                            'value' => '0', // False
                            'label' => $this->l('No Notification')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['BILLPLZ_APIKEY'] = Configuration::get('BILLPLZ_APIKEY');
        $helper->fields_value['BILLPLZ_COLLECTIONID'] = Configuration::get('BILLPLZ_COLLECTIONID');
        $helper->fields_value['BILLPLZ_MODE'] = Configuration::get('BILLPLZ_MODE');
        $helper->fields_value['BILLPLZ_IPNMODE'] = Configuration::get('BILLPLZ_IPNMODE');
        $helper->fields_value['BILLPLZ_BILLNOTIFY'] = Configuration::get('BILLPLZ_BILLNOTIFY');

        return $helper->generateForm($fields_form);
    }

    /*
     *  PrestaShop 1.7 requirements
     */

    public function hookPaymentOptions($params) {

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
    }

    /*
     *  PrestaShop 1.7 requirements
     */

    public function getExternalPaymentOption($params) {

        $signature = md5(Configuration::get('BILLPLZ_APIKEY') . Configuration::get('BILLPLZ_COLLECTIONID') . strtolower($this->context->cookie->customer_firstname) . number_format($this->context->cart->getOrderTotal(true, Cart::BOTH), 2));

        $array = array(
            'cartid' => $this->context->cart->id,
            'amount' => number_format($this->context->cart->getOrderTotal(true, Cart::BOTH), 2),
            'currency' => $this->context->currency->iso_code,
            'proddesc' => $this->getProductDesc($params),
            'name' => $this->context->cookie->customer_firstname,
            'email' => $this->context->cookie->email,
            'mobile' => $this->getPhoneNumber($this->context->customer->id),
            'logoURL' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/images/logo.jpg',
            'logoBillplz' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/logo.png',
            'processurl' => $this->context->link->getModuleLink($this->name, 'process', array(), true),
            'redirecturl' => isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=return&signature=' . $signature,
            'callbackurl' => isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=callback&signature=' . $signature,
            'signature' => $signature,
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        );

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Billplz'))
                ->setAction($array['processurl'])
                ->setAdditionalInformation($this->context->smarty->fetch('module:billplz/views/templates/front/payment_infos.tpl'))
                ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        // Prepare array data
        $arraydata = array();
        foreach ($array as $nama => $data) {
            $arraydata[$nama] = array(
                'name' => $nama,
                'type' => 'hidden',
                'value' => $data,
            );
        }

        // Letak array data dalam tu
        $externalOption->setInputs($arraydata);

        return $externalOption;
    }

    public function hookPaymentReturn($params) {
        // Hah. NTAH
    }

    public function getPhoneNumber($id_customer) {
        $sql = '
			SELECT a.phone
			FROM ' . _DB_PREFIX_ . 'address AS a
			WHERE id_customer = ' . $id_customer . '
			AND a.phone <> ""
			GROUP BY a.id_customer
			ORDER BY a.id_address
		';

        $results = Db::getInstance()->executeS($sql);

        $tel = 0;
        foreach ($results as $result) :
            $tel = $result['phone'];
        endforeach;

        return $tel;
    }

    public function getProductDesc($params) {
        $products = $params['cart']->getProducts(true);

        return $products[0]['name'];
    }

    public function checkCurrency($cart) {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

    private function _create_order_state($label, $template = null, $color = 'Blue') {
        //Create the new status
        $os = new OrderState();
        $os->name = array(
            '1' => $label,
            '2' => '',
            '3' => ''
        );

        $os->invoice = true;
        $os->unremovable = true;
        $os->color = $color;
        $os->template = $template;
        $os->send_email = false;

        $os->save();

        return $os->id;
    }

}
