<?php

namespace Omnipay\FioBankPayeezy\Message;

use DB;

/**
 * FioBankPayeezy Complete Purchase Request
 */
class CompletePurchaseRequest extends AbstractRequest
{
    public function initialize(array $parameters = array())
    {
        parent::initialize($parameters);

        $this->setParameter('transId', $this->httpRequest->request->get('trans_id'));

        return $this;
    }

    public function getData()
    {
        $this->validate('transId');

        $data = array(
            'command'        => 'c',
            'trans_id'       => $this->getParameter('transId'),
            'client_ip_addr' => $this->getParameter('clientIp'),
        );

        return $data;
    }

    protected function createResponse($data)
    {
        $httpResponse = $this->sendRequest($data);

        $this->response = new CompletePurchaseResponse($this, $httpResponse);

        $this->response->setTransactionReference($this->getParameter('transId'));

        $this->saveResponseToDb($httpResponse);

        return $this->response;
    }

    protected function saveResponseToDb($raw_response_data)
    {
        DB::table('gateway_fiobankpayeeze_transactions')->where(array('trans_id' => $this->getParameter('transId')))
            ->update(array(
            'result'          => $this->response->getDataItem('RESULT'),
            'result_code'     => $this->response->getDataItem('RESULT_CODE'),
            'result_3dsecure' => $this->response->getDataItem('3DSECURE'),
            'card_number'     => $this->response->getDataItem('CARD_NUMBER'),
            'response'        => $raw_response_data,
            'updated_at'      => date('Y-m-d H:i:s'),
        ));

        return true;
    }
}
