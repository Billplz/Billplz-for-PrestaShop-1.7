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

class BillplzApi
{
    private $connect;

    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    public function setConnect($connect)
    {
        $this->connect = $connect;
    }

    public function getCollectionIndex($parameter = array())
    {
        $response = $this->connect->getCollectionIndex($parameter);
        return $response;
    }

    public function createCollection($parameter, $optional = array())
    {
        $response = $this->connect->createCollection($parameter, $optional);
        return $response;
    }

    public function getCollection($parameter)
    {
        $response = $this->connect->getCollection($parameter);
        return $response;
    }

    public function createOpenCollection($parameter, $optional = array())
    {
        $parameter['title'] = substr($parameter['title'], 0, 49);
        $parameter['description'] = substr($parameter['description'], 0, 199);

        if (intval($parameter['amount']) > 999999999) {
            throw new \Exception("Amount Invalid. Too big");
        }

        $response = $this->connect->createOpenCollection($parameter, $optional);
        return $response;
    }

    public function getOpenCollection($parameter)
    {
        $response = $this->connect->getOpenCollection($parameter);
        return $response;
    }

    public function getOpenCollectionIndex($parameter = array())
    {
        $response = $this->connect->getOpenCollectionIndex($parameter);
        return $response;
    }

    public function createMPICollection($parameter)
    {
        $response = $this->connect->createMPICollection($parameter);
        return $response;
    }

    public function getMPICollection($parameter)
    {
        $response = $this->connect->getMPICollection($parameter);
        return $response;
    }

    public function createMPI($parameter, $optional = array())
    {
        $response = $this->connect->createMPI($parameter, $optional);
        return $response;
    }

    public function getMPI($parameter)
    {
        $response = $this->connect->getMPI($parameter);
        return $response;
    }

    public function deactivateCollection($parameter)
    {
        $response = $this->connect->deactivateCollection($parameter);
        return $response;
    }

    public function activateCollection($parameter)
    {
        $response = $this->connect->deactivateCollection($parameter, 'activate');
        return $response;
    }

    public function createBill($parameter, $optional = array(), $sendCopy = '')
    {
        /* Email or Mobile must be set */
        if (empty($parameter['email']) && empty($parameter['mobile'])) {
            throw new \Exception("Email or Mobile must be set!");
        }

        /* Manipulate Deliver features to allow Email/SMS Only copy */
        if ($sendCopy === '0') {
            $optional['deliver'] = 'false';
        } elseif ($sendCopy === '1' && !empty($parameter['email'])) {
            $optional['deliver'] = 'true';
            unset($parameter['mobile']);
        } elseif ($sendCopy === '2' && !empty($parameter['mobile'])) {
            $optional['deliver'] = 'true';
            unset($parameter['email']);
        } elseif ($sendCopy === '3') {
            $optional['deliver'] = 'true';
        }

        /* Validate Mobile Number first */
        if (!empty($parameter['mobile'])) {
            /* Strip all unwanted character */
            $parameter['mobile'] = preg_replace('/[^0-9]/', '', $parameter['mobile']);

            /* Add '6' if applicable */
            $parameter['mobile'] = $parameter['mobile'][0] === '0' ? '6' . $parameter['mobile'] : $parameter['mobile'];

            /* If the number doesn't have valid formatting, reject it */
            /* The ONLY valid format '<1 Number>' + <10 Numbers> or '<1 Number>' + <11 Numbers> */
            /* Example: '60141234567' or '601412345678' */
            if (!preg_match('/^[0-9]{11,12}$/', $parameter['mobile'], $m)) {
                $parameter['mobile'] = '';
            }
        }

        /* Create Bills */
        return $this->connect->createBill($parameter, $optional);
    }

    public function deleteBill($parameter)
    {
        $response = $this->connect->deleteBill($parameter);
        
        return $response;
    }

    public function getBill($parameter)
    {
        $response = $this->connect->getBill($parameter);
        return $response;
    }

    public function bankAccountCheck($parameter)
    {
        $response = $this->connect->bankAccountCheck($parameter);
        
        return $response;
    }

    public function getTransactionIndex($id, $parameter = array('page' => '1'))
    {
        $response = $this->connect->getTransactionIndex($id, $parameter);
        return $response;
    }

    public function getPaymentMethodIndex($parameter)
    {
        $response = $this->connect->getPaymentMethodIndex($parameter);
        return $response;
    }

    public function updatePaymentMethod($parameter)
    {
        $response = $this->connect->updatePaymentMethod($parameter);
        return $response;
    }

    public function getBankAccountIndex($parameter = array('account_numbers' => ['0', '1']))
    {
        $response = $this->connect->getBankAccountIndex($parameter);
        return $response;
    }

    public function getBankAccount($parameter)
    {
        $response = $this->connect->getBankAccount($parameter);
        return $response;
    }

    public function createBankAccount($parameter)
    {
        $response = $this->connect->createBankAccount($parameter);
        return $response;
    }

    public function bypassBillplzPage($bill)
    {
        $bills = \json_decode($bill, true);
        if ($bills['reference_1_label'] !== 'Bank Code') {
            return \json_encode($bill);
        }

        $fpxBanks = $this->toArray($this->getFpxBanks());
        if ($fpxBanks[0] !== 200) {
            return \json_encode($bill);
        }

        $found = false;
        foreach ($fpxBanks[1]['banks'] as $bank) {
            if ($bank['name'] === $bills['reference_1']) {
                if ($bank['active']) {
                    $found = true;
                    break;
                }
                return \json_encode($bill);
            }
        }

        if ($found) {
            $bills['url'] .= '?auto_submit=true';
        }

        return json_encode($bills);
    }

    public function getFpxBanks()
    {
        $response = $this->connect->getFpxBanks();
        return $response;
    }

    public function toArray($json)
    {
        return $this->connect->toArray($json);
    }
}