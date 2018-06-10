<?php

namespace Omnipay\FioBankPayeezy;

use Omnipay\Common\AbstractGateway;

/**
 * FIO Banka a.s. Payment Gateway by FirstData Payeezy
 *
 * @link https://www.fio.cz/docs/cz/Vytah_z_dokumentace_ecommerce.pdf
 */
class Gateway extends AbstractGateway
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'FioBankPayeezy';
    }

    /**
     * @return array
     */
    public function getDefaultParameters()
    {
        return array(
            'cert' => '',
            'certPass' => '',
            'testMode' => 0
        );
    }

    /**
     * @return string
     */
    public function getCert()
    {
        return $this->getParameter('cert');
    }

    /**
     * @param  string $value
     * @return $this
     */
    public function setCert($value)
    {
        return $this->setParameter('cert', $value);
    }

    /**
     * @return string
     */
    public function getCertPass()
    {
        return $this->getParameter('certPass');
    }

    /**
     * @param  string $value
     * @return $this
     */
    public function setCertPass($value)
    {
        return $this->setParameter('certPass', $value);
    }

    /**
     * @param  array $parameters
     * @return \Omnipay\FioBankPayeezy\Message\PurchaseRequest
     */
    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\FioBankPayeezy\Message\PurchaseRequest', $parameters);
    }

    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\FioBankPayeezy\Message\CompletePurchaseRequest', $parameters);
    }

    /**
     * @param  array $parameters
     * @return \Omnipay\FioBankPayeezy\Message\RefundRequest
     */
    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\FioBankPayeezy\Message\RefundRequest', $parameters);
    }

    /**
     * @param  array $parameters
     * @return \Omnipay\FioBankPayeezy\Message\CloseRequest
     */
    public function close(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\FioBankPayeezy\Message\CloseRequest', $parameters);
    }
}
