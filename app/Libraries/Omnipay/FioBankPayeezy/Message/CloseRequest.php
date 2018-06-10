<?php
namespace Omnipay\FioBankPayeezy\Message;

use DB;

/**
 * FioBankPayeezy Refund Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\CloseRequest send()
 */
class CloseRequest extends AbstractRequest
{
    public function getData()
    {
        $data = array(
            'command'  => 'b',
        );

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->sendRequest($data);

        $this->response = new CloseResponse($this, $httpResponse);

        $this->saveResponseToDb($httpResponse);

        return $this->response;
    }

    protected function saveResponseToDb($raw_response_data)
    {
        // success
        if($this->response->isSuccessful()) {
            DB::table('gateway_fiobankpayeeze_batches')->insert(array(
                    'result'             => $this->response->getDataItem('RESULT'),
                    'result_code'        => $this->response->getDataItem('RESULT_CODE'),
                    'count_reversal'     => $this->response->getDataItem('FLD_075'),
                    'count_transaction'  => $this->response->getDataItem('FLD_076'),
                    'amount_reversal'    => $this->response->getDataItem('FLD_087'),
                    'amount_transaction' => $this->response->getDataItem('FLD_088'),
                    'response'           => $raw_response_data,
                    'closed_at'          => date('Y-m-d H:i:s'),
                ));

            return true;
        }

        // error
        DB::table('gateway_fiobankpayeeze_errors')->insert(array(
            'action'     => 'closeDay',
            'response'   => $raw_response_data,
            'created_at' => date('Y-m-d H:i:s'),
        ));

        return false;
    }
}
