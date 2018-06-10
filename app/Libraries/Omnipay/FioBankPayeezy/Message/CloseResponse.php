<?php
namespace Omnipay\FioBankPayeezy\Message;

/**
 * FioBankPayeezy Refund Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\CloseResponse send()
 */

class CloseResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return (bool) $this->getDataItem('RESULT');
    }
}
