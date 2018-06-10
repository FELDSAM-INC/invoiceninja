<?php

namespace Omnipay\FioBankPayeezy\Message;

use DB;
use Omnipay\Common\Message\RequestInterface;

/**
 * FioBankPayeezy Response
 */
class AbstractResponse extends \Omnipay\Common\Message\AbstractResponse
{
    protected $resultCodes = array(
        129 => 'Nesprávná platnost karty nebo CVC2/CVV2 kód',
    );

    public function __construct(RequestInterface $request, $data)
    {
        $this->request = $request;

        if(preg_match_all('/^(.+):\s(.+)$/m', $data, $matches)){
            foreach($matches[1] as $key => $val){
                $this->data[$val] = $matches[2][$key];
            }
        }
    }

    public function isSuccessful()
    {
        return $this->getDataItem('RESULT') === 'OK';
    }

    public function getTransactionReference()
    {
        return $this->getDataItem('TRANSACTION_ID');
    }

    public function setTransactionReference($transId)
    {
        $this->data['TRANSACTION_ID'] = $transId;
    }

    public function getCode()
    {
        return $this->getDataItem('RESULT_CODE');
    }

    public function getMessage()
    {
        if($this->isSuccessful())
        {
            return null;
        }

        if($this->getDataItem('3DSECURE') !== 'NOTPARTICIPATED' && $this->getDataItem('3DSECURE') !== 'AUTHENTICATED')
        {
            return 'Problém s autorizací přes systém 3D Secure (špatně zadaný kód z SMS nebo interní systémový problém)';
        }

        if(isset($this->resultCodes[$this->getDataItem('RESULT_CODE')]))
        {
            return $this->resultCodes[$this->getDataItem('RESULT_CODE')];
        }

        return null;
    }

    public function getDataItem($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
}
