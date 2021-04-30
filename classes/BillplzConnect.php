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

class BillplzConnect
{
    private $api_key;
    private $x_signature_key;
    private $collection_id;

    private $process;
    public $is_production;
    public $detect_mode = false;
    public $url;
    public $webhook_rank;

    public $header;

    const PRODUCTION_URL = 'https://www.billplz.com/api/';
    const STAGING_URL = 'https://www.billplz-sandbox.com/api/';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;

        $this->process = curl_init();

        $this->header = $api_key . ':';
        curl_setopt($this->process, CURLOPT_HEADER, 0);
        curl_setopt($this->process, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->process, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->process, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->process, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->process, CURLOPT_USERPWD, $this->header);
    }

    public function setStaging($is_staging = false)
    {
        $this->is_staging = $is_staging;
        if ($is_staging) {
            $this->url = self::STAGING_URL;
        } else {
            $this->url = self::PRODUCTION_URL;
        }
    }

    public function createCollection($title, $optional = array())
    {
        $url = $this->url . 'v4/collections';

        $body = http_build_query(['title' => $title]);
        if (isset($optional['split_header'])) {
            $split_header = http_build_query(array('split_header' => $optional['split_header']));
        }

        $split_payments = [];
        if (isset($optional['split_payments'])) {
            foreach ($optional['split_payments'] as $param) {
                $split_payments[] = http_build_query($param);
            }
        }

        if (!empty($split_payments)) {
            $body .= '&' . implode('&', $split_payments);

            if (!empty($split_header)) {
                $body .= '&' . $split_header;
            }
        }

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, $body);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getCollectionIndex($parameter = array())
    {
        $url = $this->url . 'v4/collections?' . http_build_query($parameter);

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function createOpenCollection($parameter, $optional = array())
    {
        $url = $this->url . 'v4/open_collections';

        $body = http_build_query($parameter);
        if (isset($optional['split_header'])) {
            $split_header = http_build_query(array('split_header' => $optional['split_header']));
        }

        $split_payments = [];
        if (isset($optional['split_payments'])) {
            foreach ($optional['split_payments'] as $param) {
                $split_payments[] = http_build_query($param);
            }
        }

        if (!empty($split_payments)) {
            unset($optional['split_payments']);
            $body .= '&' . implode('&', $split_payments);
            if (!empty($split_header)) {
                unset($optional['split_header']);
                $body .= '&' . $split_header;
            }
        }

        if (!empty($optional)) {
            $body .= '&' . http_build_query($optional);
        }

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, $body);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getCollection($id)
    {
        $url = $this->url . 'v4/collections/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getOpenCollection($id)
    {
        $url = $this->url . 'v4/open_collections/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getOpenCollectionIndex($parameter = array())
    {
        $url = $this->url . 'v4/open_collections?' . http_build_query($parameter);

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);
        
        return $return;
    }

    public function createMPICollection($title)
    {
        $url = $this->url . 'v4/mass_payment_instruction_collections';

        $data = ['title' => $title];

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, http_build_query($data));
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);
        
        return $return;
    }

    public function getMPICollection($id)
    {
        $url = $this->url . 'v4/mass_payment_instruction_collections/' . $id;
        
        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);
        
        return $return;
    }

    public function createMPI($parameter, $optional = array())
    {
        $url = $this->url . 'v4/mass_payment_instructions';

        //if (sizeof($parameter) !== sizeof($optional) && !empty($optional)){
        //    throw new \Exception('Optional parameter size is not match with Required parameter');
        //}

        $data = array_merge($parameter, $optional);

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, http_build_query($data));
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getMPI($id)
    {
        $url = $this->url . 'v4/mass_payment_instructions/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public static function buildSourceString($data, $prefix = '')
    {
        uksort($data, function ($a, $b) {
            $a_len = strlen($a);
            $b_len = strlen($b);
            $result = strncasecmp($a, $b, min($a_len, $b_len));
            if ($result === 0) {
                $result = $b_len - $a_len;
            }
            return $result;
        });
        $processed = [];
        foreach ($data as $key => $value) {
            if ($key === 'x_signature') {
                continue;
            }

            if (is_array($value)) {
                $processed[] = self::buildSourceString($value, $key);
            } else {
                $processed[] = $prefix . $key . stripslashes($value);
            }
        }
        return implode('|', $processed);
    }

    public static function getXSignature($x_signature_key)
    {
        $data = array();

        if (isset($_GET['billplz']['x_signature'])) {
            $keys = array('id', 'paid_at', 'paid', 'transaction_id', 'transaction_status', 'x_signature');

            foreach ($keys as $key){
                if (isset($_GET['billplz'][$key])){
                    $data['billplz'][$key] = $_GET['billplz'][$key];
                }
            } 
            $type = 'redirect';
        } elseif (isset($_POST['x_signature'])) {
            $keys = array('amount', 'collection_id', 'due_at', 'email', 'id', 'mobile', 'name', 'paid_amount', 'transaction_id', 'transaction_status', 'paid_at', 'paid', 'state', 'url', 'x_signature');
            foreach ($keys as $key){
                if (isset($_POST[$key])){
                    $data[$key] = $_POST[$key];
                }
            }
            $type = 'callback';
        } else {
            throw new \Exception('X Signature on Payment Completion not activated.');
        }

        $signing = self::buildSourceString($data);

        if ($type == 'redirect'){
            $data = $data['billplz'];
        }

        /*
         * Convert paid status to boolean
         */
        $data['paid'] = $data['paid'] === 'true' ? true : false;

        $signed = hash_hmac('sha256', $signing, $x_signature_key);

        if ($data['x_signature'] === $signed) {
            $data['type'] = $type;
            return $data;
        }

        throw new \Exception('X Signature Calculation Mismatch!');
    }

    public function deactivateCollection($title, $option = 'deactivate')
    {
        $url = $this->url . 'v3/collections/' . $title . '/' . $option;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 1);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, http_build_query(array()));
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function createBill($parameter, $optional = array())
    {
        $url = $this->url . 'v3/bills';

        //if (sizeof($parameter) !== sizeof($optional) && !empty($optional)){
        //    throw new \Exception('Optional parameter size is not match with Required parameter');
        //}

        $data = array_merge($parameter, $optional);

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POSTFIELDS, http_build_query($data));
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function getBill($id)
    {
        $url = $this->url . 'v3/bills/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);

        return $return;
    }

    public function deleteBill($id)
    {
        $url = $this->url . 'v3/bills/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_CUSTOMREQUEST, "DELETE");
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);
 
        return $return;
    }

    public function bankAccountCheck($id)
    {
        $url = $this->url . 'v3/check/bank_account_number/' . $id;

        curl_setopt($this->process, CURLOPT_URL, $url);
        curl_setopt($this->process, CURLOPT_POST, 0);
        $body = curl_exec($this->process);
        $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);
        $return = array($header, $body);
        return $return;
    }

    public function getPaymentMethodIndex($id)
    {
        $url = $this->url . 'v3/collections/' . $id . '/payment_methods';

        $body = $this->process->get($url);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);
 
        return $return;
    }

    public function getTransactionIndex($id, $parameter)
    {
        $url = $this->url . 'v3/bills/' . $id . '/transactions';

        $body = $this->process->get($url, $parameter);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);

        return $return;
    }

    public function updatePaymentMethod($parameter)
    {
        if (!isset($parameter['collection_id'])) {
            throw new \Exception('Collection ID is not passed on updatePaymethodMethod');
        }
        $url = $this->url . 'v3/collections/' . $parameter['collection_id'] . '/payment_methods';

        unset($parameter['collection_id']);
        $data = $parameter;

        $body = [];
        foreach ($data['payment_methods'] as $param) {
            $body[] = http_build_query($param);
        }

        $body = $this->process->put($url, $body);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);

        return $return;
    }

    public function getBankAccountIndex($parameter)
    {
        if (!is_array($parameter['account_numbers'])) {
            throw new \Exception('Not valid account numbers.');
        }

        $parameter = http_build_query($parameter);
        $parameter = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $parameter);

        $url = $this->url . 'v3/bank_verification_services?' . $parameter;

        $body = $this->process->get($url);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);

        return $return;
    }

    public function getBankAccount($id)
    {
        $url = $this->url . 'v3/bank_verification_services/' . $id;

        $body = $this->process->get($url);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);

        return $return;
    }

    public function createBankAccount($parameter)
    {
        $url = $this->url . 'v3/bank_verification_services';

        $body = $this->process->post($url, $parameter);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);
        return $return;
    }

    public function getFpxBanks()
    {
        $url = $this->url . 'v3/fpx_banks';

        $body = $this->process->get($url);
        $header = $this->process->info['http_code'];
        $return = array($header, $body);

        return $return;
    }

    public function toArray($json)
    {
        return array($json[0], \json_decode($json[1], true));
    }
}