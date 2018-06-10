<?php
namespace Omnipay\FioBankPayeezy\Message;

/**
 * FioBankPayeezy Refund Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\RefundResponse send()
 */

class RefundResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return ($this->getDataItem('RESULT') === 'OK' || $this->getDataItem('RESULT') === 'REVERSED');
    }
}
