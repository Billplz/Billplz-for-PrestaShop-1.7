<?php
/**
 * 2007-2019 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2019 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Billplz extends \PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    protected $api_key;
    protected $collection_id;
    protected $x_signature;

    public function __construct()
    {
        $this->name = 'billplz';
        $this->tab = 'payments_gateways';
        $this->version = '3.2.0';
        $this->ps_versions_compliancy = array('min' => '1.7.4.4', 'max' => '1.7');
        //$this->limited_countries = array('my');
        $this->author = 'Billplz Sdn Bhd';
        $this->controllers = array('return', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'radio';

        $config = Configuration::getMultiple(array('BILLPLZ_IS_STAGING', 'BILLPLZ_API_KEY', 'BILLPLZ_COLLECTION_ID', 'BILLPLZ_X_SIGNATURE'));

        if (!empty($config['BILLPLZ_IS_STAGING'])) {
            $this->is_staging = $config['BILLPLZ_IS_STAGING'];
        }
        
        if (!empty($config['BILLPLZ_API_KEY'])) {
            $this->api_key = $config['BILLPLZ_API_KEY'];
        }

        if (!empty($config['BILLPLZ_COLLECTION_ID'])) {
            $this->collection_id = $config['BILLPLZ_COLLECTION_ID'];
        }

        if (!empty($config['BILLPLZ_X_SIGNATURE'])) {
            $this->x_signature = $config['BILLPLZ_X_SIGNATURE'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Billplz', array(), 'Modules.Billplz.Admin');
        $this->description = $this->trans('Accept payments by Billplz.', array(), 'Modules.Billplz.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Billplz.Admin');

        if (!isset($this->is_staging) || !isset($this->api_key) || !isset($this->collection_id) || !isset($this->x_signature)) {
            $this->warning = $this->trans('API Key, Collection ID and X Signature Key must be configured before using this module.', array(), 'Modules.Billplz.Admin');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.Billplz.Admin');
        }
    }

    public function install()
    {
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'billplz` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `cart_id` int(11) NOT NULL,
                `bill_id` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `bill_id` (`bill_id`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;'
        );

        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->installOrderState()) {
            return false;
        }
        return true;
    }

    public function installOrderState()
    {
        if (!Configuration::get('BILLPLZ_OS_WAITING') || !Validate::isLoadedObject(new OrderState(Configuration::get('BILLPLZ_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for Billplz payment';
            }

            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_ . 'billplz/logo.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('BILLPLZ_OS_WAITING', (int) $order_state->id);
        }
        return true;
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'billplz`;');
        if (!Configuration::deleteByName('BILLPLZ_IS_STAGING')
            || !Configuration::deleteByName('BILLPLZ_API_KEY')
            || !Configuration::deleteByName('BILLPLZ_COLLECTION_ID')
            || !Configuration::deleteByName('BILLPLZ_X_SIGNATURE')
            || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('BILLPLZ_IS_STAGING')) {
                $this->_postErrors[] = $this->trans('Billplz mode are required.', array(), 'Modules.Billplz.Admin');
            } elseif (!Tools::getValue('BILLPLZ_API_KEY')) {
                $this->_postErrors[] = $this->trans('API Key are required.', array(), 'Modules.Billplz.Admin');
            } elseif (!Tools::getValue('BILLPLZ_COLLECTION_ID')) {
                $this->_postErrors[] = $this->trans('Collection ID is required.', array(), "Modules.Billplz.Admin");
            } elseif (!Tools::getValue('BILLPLZ_X_SIGNATURE')) {
                $this->_postErrors[] = $this->trans('X Signature Key is required.', array(), "Modules.Billplz.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('BILLPLZ_IS_STAGING', Tools::getValue('BILLPLZ_IS_STAGING'));
            Configuration::updateValue('BILLPLZ_API_KEY', Tools::getValue('BILLPLZ_API_KEY'));
            Configuration::updateValue('BILLPLZ_COLLECTION_ID', Tools::getValue('BILLPLZ_COLLECTION_ID'));
            Configuration::updateValue('BILLPLZ_X_SIGNATURE', Tools::getValue('BILLPLZ_X_SIGNATURE'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->renderForm();
        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay by Billplz', array(), 'Modules.Billplz.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
    }

    public function checkCurrency($cart)
    {
        //$current_id_currency = Context::getContext()->currency->id;

        if (!$this->currencies) {
            return false;
        }

        $currencies_module = Currency::getPaymentCurrencies($this->id);
        $currency_order = new Currency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Billplz account details', array(), 'Modules.Billplz.Admin'),
                    'icon' => 'icon-gear',
                ),
                'input' => array(
                    array(
                        'type' => 'radio',
                        'label' => $this->trans('Account Type', array(), 'Modules.Billplz.Admin'),
                        'name' => 'BILLPLZ_IS_STAGING',
                        'values' => array(
                            array(
                                'id' => 'production',
                                'label' => $this->l('Production'),
                                'value' => 'no'
                            ),
                            array(
                              'id' => 'sandbox',
                              'label' => $this->l('Sandbox'),
                              'value' => 'yes'
                            )
                        ),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('API Secret Key', array(), 'Modules.Billplz.Admin'),
                        'name' => 'BILLPLZ_API_KEY',
                        'desc' => $this->trans('It can be from Production or Staging. It can be retrieved from Billplz Account Settings page.', array(), 'Modules.Billplz.Admin'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Collection ID', array(), 'Modules.Billplz.Admin'),
                        'name' => 'BILLPLZ_COLLECTION_ID',
                        'desc' => $this->trans('Enter your chosen specific Billing Collection ID. It can be retrieved from Billplz Billing page.', array(), 'Modules.Billplz.Admin'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('X Signature Key', array(), 'Modules.Billplz.Admin'),
                        'name' => 'BILLPLZ_X_SIGNATURE',
                        'desc' => $this->trans('It can be from Production or Staging. It can be retrieved from Billplz Account Settings page.', array(), 'Modules.Billplz.Admin'),
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
        . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'BILLPLZ_IS_STAGING' => Tools::getValue('BILLPLZ_IS_STAGING', Configuration::get('BILLPLZ_IS_STAGING')),
            'BILLPLZ_API_KEY' => Tools::getValue('BILLPLZ_API_KEY', Configuration::get('BILLPLZ_API_KEY')),
            'BILLPLZ_COLLECTION_ID' => Tools::getValue('BILLPLZ_COLLECTION_ID', Configuration::get('BILLPLZ_COLLECTION_ID')),
            'BILLPLZ_X_SIGNATURE' => Tools::getValue('BILLPLZ_X_SIGNATURE', Configuration::get('BILLPLZ_X_SIGNATURE')),
        );
    }
}
