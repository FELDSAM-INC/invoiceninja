<?php

namespace Omnipay\FioBankPayeezy\Message;

abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    protected $endpoint = array(
        'test' => 'https://secureshop-test.firstdata.lv:8443/ecomm/MerchantHandler',
        'prod' => 'https://secureshop.firstdata.lv:8443/ecomm/MerchantHandler',
    );

    protected $baseCurrencyCode = 'CZK';

    protected $currencyCodes = array(
        'CZK' => '203',
        'EUR' => '978',
        'USD' => 840,
    );

    public function getCurrencyCodes()
    {
        return $this->currencyCodes;
    }

    /**
     * @return string
     */
    public function getCert()
    {
        $cert = $this->getParameter('cert');

        $cert = strtr($cert, array('{n} ' => "\n", '{n}' => "\n", '{indent}' => '    '));

        return $cert;
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
     * @return bool
     */
    public function getConvertCurrency()
    {
        return $this->getParameter('convertCurrency');
    }

    /**
     * @param  bool $value
     * @return $this
     */
    public function setConvertCurrency($value)
    {
        return $this->setParameter('convertCurrency', $value);
    }

    /**
     * @return bool
     */
    public function getBaseCurrencyCode()
    {
        return $this->getParameter('baseCurrencyCode');
    }

    /**
     * @param  bool $value
     * @return $this
     */
    public function setBaseCurrencyCode($value)
    {
        return $this->setParameter('baseCurrencyCode', $value);
    }

    protected function sendRequest($data)
    {
        // save cert to file
        $certPath = tempnam(sys_get_temp_dir(), 'invoiceNinja_');
        file_put_contents($certPath, $this->getCert());

        // set endpoint
        $endpoint = ($this->getTestMode()) ? $this->endpoint['test'] : $this->endpoint['prod'];

        // prepare post fields
        $post = http_build_query($data, '', '&', PHP_QUERY_RFC3986);

        // init curl
        $curl = curl_init();

        if($this->getTestMode()){
            curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
        }

        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSLCERT, $certPath);
        curl_setopt($curl, CURLOPT_CAINFO, $certPath);
        curl_setopt($curl, CURLOPT_SSLKEYPASSWD, $this->getCertPass());
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);

        if(curl_error($curl)){
            $result = curl_error($curl);
            error_log($result);
        }

        curl_close($curl);

        // delete cet file
        unlink($certPath);

        return $result;
    }

    public function sendData($data)
    {
        return $this->createResponse($data);
    }
}
