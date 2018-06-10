<?php
namespace Omnipay\FioBankPayeezy\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * FioBankPayeezy Purchase Request
 *
 * @method \Omnipay\FioBankPayeezy\Message\PurchaseResponse send()
 */

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    protected $checkoutEndpoint = array(
        'test' => 'https://secureshop-test.firstdata.lv/ecomm/ClientHandler',
        'prod' => 'https://secureshop.firstdata.lv/ecomm/ClientHandler',
    );

    public function isSuccessful()
    {
        return false;
    }

    public function isRedirect()
    {
        return (bool) $this->getTransactionReference();
    }

    public function getRedirectUrl()
    {
        return $this->getCheckoutEndpoint().'?'.http_build_query($this->getRedirectQueryParameters(), '', '&');
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return null;
    }

    protected function getRedirectQueryParameters()
    {
        return array(
            'trans_id' => $this->getTransactionReference(),
        );
    }

    protected function getCheckoutEndpoint()
    {
        return $this->getRequest()->getTestMode() ? $this->checkoutEndpoint['test'] : $this->checkoutEndpoint['prod'];
    }
}
