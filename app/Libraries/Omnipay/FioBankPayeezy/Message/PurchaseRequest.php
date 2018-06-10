<?php
namespace Omnipay\FioBankPayeezy\Message;

use DB;

/**
 * FioBankPayeezy Purchase Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\PurchaseRequest send()
 */
class PurchaseRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('cert', 'certPass', 'amount', 'description', 'returnUrl');

        $data = array(
            'command'        => 'v',
            'amount'         => $this->getAmountInteger(),
            'currency'       => $this->currencyCodes[$this->getCurrency()],
            'client_ip_addr' => $this->getClientIp(),
            'description'    => $this->getDescription(),
            'language'       => 'cz',
        );

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->sendRequest($data);

        $this->response = new PurchaseResponse($this, $httpResponse);

        $this->saveResponseToDb($httpResponse);

        return $this->response;
    }

    protected function saveResponseToDb($raw_response_data)
    {
        $transId = $this->response->getTransactionReference();

        // transId exists, so save it
        if($transId) {
            DB::table('gateway_fiobankpayeeze_transactions')->insert(array(
                'invoice_number' => $this->getTransactionId(),
                'trans_id'       => $transId,
                'amount'         => $this->getAmountInteger(),
                'currency'       => $this->currencyCodes[$this->getCurrency()],
                'client_ip_addr' => $this->getClientIp(),
                'description'    => $this->getDescription(),
                'language'       => 'cz',
                'response'       => $raw_response_data,
                'created_at'     => date('Y-m-d H:i:s'),
            ));

            return true;
        }

        // trans id doesn't exists, so save error
        DB::table('gateway_fiobankpayeeze_errors')->insert(array(
            'action'     => 'startsmstrans',
            'response'   => $raw_response_data,
            'created_at' => date('Y-m-d H:i:s'),
        ));

        return false;
    }
}
