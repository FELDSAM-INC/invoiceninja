<?php
namespace Omnipay\FioBankPayeezy\Message;

use DB;

/**
 * FioBankPayeezy Refund Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\RefundRequest send()
 */
class RefundRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('transactionReference', 'amount');

        $data = array(
            'command'  => 'r',
            'amount'   => $this->getAmountInteger(),
            'trans_id' => $this->getTransactionReference(),
        );

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->sendRequest($data);

        $this->response = new RefundResponse($this, $httpResponse);

        $this->saveResponseToDb($httpResponse);

        return $this->response;
    }

    protected function saveResponseToDb($raw_response_data)
    {
        // success
        if($this->response->isSuccessful()) {
            DB::table('gateway_fiobankpayeeze_transactions')->where(array('trans_id' => $this->getTransactionReference()))
                ->update(array(
                    'result'          => $this->response->getDataItem('RESULT'),
                    'result_code'     => $this->response->getDataItem('RESULT_CODE'),
                    'reversal_amount' => $this->getAmountInteger(),
                    'response'        => $raw_response_data,
                    'updated_at'      => date('Y-m-d H:i:s'),
                ));

            return true;
        }

        // error
        DB::table('gateway_fiobankpayeeze_errors')->insert(array(
            'action'     => 'reverse',
            'response'   => $raw_response_data,
            'created_at' => date('Y-m-d H:i:s'),
        ));

        return false;
    }
}
