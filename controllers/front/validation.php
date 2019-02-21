<?php

require_once _PS_MODULE_DIR_ . 'billplz/classes/BillplzAPI.php';
require_once _PS_MODULE_DIR_ . 'billplz/classes/BillplzConnect.php';

class BillplzValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
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
            Tools::redirect('index.php?controller=order&step=1');
        }

        $id_address = (int) $cart->id_address_delivery;
        if (($id_address == 0) && ($customer)) {
            $id_address = Address::getFirstCustomerAddressId($customer->id);
        }

        $address = new Address($id_address);

        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $config = Configuration::getMultiple(array('BILLPLZ_API_KEY', 'BILLPLZ_COLLECTION_ID'));

        $products = $cart->getProducts();
        $product_description = '';
        foreach ($products as $product) {
            $product_description.="{$product['name']} ";
        }

        $parameter = array(
            'collection_id' => trim($config['BILLPLZ_COLLECTION_ID']),
            'email' => trim($customer->email),
            'mobile'=> trim((empty($address->phone)) ? $address->phone_mobile : $address->phone),
            'name' => trim($customer->firstname . " " . $customer->lastname),
            'amount' => strval($total * 100),
            'callback_url' => $this->context->link->getModuleLink($module['name'], 'return', array(), true),
            'description' => mb_substr($product_description, 0, 200)
        );

        if (empty($parameter['mobile']) && empty($parameter['email'])) {
            $parameter['email'] = 'noreply@billplz.com';
        }

        if (empty($parameter['name'])) {
            $parameter['name'] =  !empty($blog_name) ? $blog_name : 'Payer Name Unavailable';
        }
        
        $optional = array(
            'redirect_url' => $parameter['callback_url']
        );

        $connect = new BillplzConnect(trim($config['BILLPLZ_API_KEY']));
        $connect->detectMode();
        
        $billplz = new BillplzApi($connect);
        
        list($rheader, $rbody) = $billplz->toArray($billplz->createBill($parameter, $optional, '0'));

        if ($rheader !== 200) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $this->module->validateOrder($cart->id, Configuration::get('BILLPLZ_OS_WAITING'), $total, $this->module->displayName, "Payment pending. Bill ID: {$rbody['id']}", $rbody, (int)$currency->id, false, $customer->secure_key);

        Db::getInstance()->insert(
            'billplz',
            array(
                'cart_id' => pSQL((int) $cart->id),
                'bill_id' => pSQL($rbody['id']),
            )
        );

        Tools::redirect($rbody['url']);
    }
}
