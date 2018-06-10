<?php
namespace Omnipay\FioBankPayeezy\Message;

/**
 * FioBankPayeezy CompletePurchase Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\CompletePurchaseResponse send()
 */

class CompletePurchaseResponse extends AbstractResponse
{
    public function isCancelled()
    {
        return false;
    }
}
