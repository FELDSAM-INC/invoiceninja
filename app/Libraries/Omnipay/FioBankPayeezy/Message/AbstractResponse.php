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
        100 => 'Decline (general, no comments)',
        101 => 'Vašej karte vypršala platnost. Použijte jinou kartu a platbu opakujte.<br> Your card has expired. Please use a different card and try again.',
        102 => 'Decline, suspected fraud',
        103 => 'Decline, card acceptor contact acquirer',
        104 => 'Decline, restricted card',
        105 => 'Decline, card acceptor call acquirer&apos;s security department',
        106 => 'Decline, allowable PIN tries exceeded',
        107 => 'Decline, refer to card issuer',
        108 => 'Decline, refer to card issuer&apos;s special conditions',
        109 => 'Decline, invalid merchant',
        110 => 'Decline, invalid amount',
        111 => 'Zadali jste nesprávné číslo karty. Platbu opakujte.<br> You entered the card number incorrectly. Repeat your payment.',
        112 => 'Decline, PIN data required',
        113 => 'Decline, unacceptable fee',
        114 => 'Decline, no account of type requested',
        115 => 'Decline, requested function not supported',
        116 => 'Na vaší kartě je nedostatek prostředků. Použijte jinou kartu a platbu opakujte.<br> There is a lack of funds on your card. Please use a different card and try again.',
        117 => 'Decline, incorrect PIN',
        118 => 'Decline, no card record',
        119 => 'Decline, transaction not permitted to cardholder',
        120 => 'Vámi použitá karta nemá povolené platby na internetu. Kontaktujte svou banku nebo použijte jinou kartu.<br> The card you are using does not allow payments on the Internet. Contact your bank or use a different card.',
        121 => 'Na vaší kartě byl vyčerpán denní nebo měsíční limit. Použijte jinou kartu nebo si navyšte limity a platbu opakujte.<br> A daily or monthly limit has been exhausted on your card. Use a different card or raise the limits and try again.',
        122 => 'Decline, security violation',
        123 => 'Na vaší kartě byl vyčerpán denní nebo měsíční limit počtu plateb. Použijte jinou kartu nebo si navyšte limity a platbu opakujte.<br> The daily or monthly limit of your payments count has been exhausted on your card. Use a different card or raise the limits and try again.',
        124 => 'Decline, violation of law',
        125 => 'Decline, card not effective',
        126 => 'Decline, invalid PIN block',
        127 => 'Decline, PIN length error',
        128 => 'Decline, PIN key sync error',
        129 => 'Zadali jste nesprávnú platnost karty nebo CVC2/CVV2 kód. Platbu opakujte.<br> You entered invalid card expiration or incorrect CVC2 / CVV2 code. Repeat your payment.',
        198 => 'Decline, call Card Processing Centre',
        197 => 'Decline, call AmEx'
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

        if($this->getDataItem('RESULT') === 'TIMEOUT')
        {
            return 'Transakce vypršela, opakujte platbu znovu.<br> Transaction expired, repeat payment again.';
        }

        if($this->getDataItem('3DSECURE') !== 'NOTPARTICIPATED' && $this->getDataItem('3DSECURE') !== 'AUTHENTICATED')
        {
            return 'Problém s autorizací přes systém 3D Secure (špatně zadaný kód z SMS nebo interní systémový problém).<br> Problem with authorization via 3D Secure (bad code from SMS or internal system issue).';
        }

        if(isset($this->resultCodes[$this->getDataItem('RESULT_CODE')]))
        {
            return $this->resultCodes[$this->getDataItem('RESULT_CODE')];
        }

        return 'Nastala blíže nespecifikovaná chyba, opakujte platbu znovu.<br> There was an unspecified error, repeat the payment again.';
    }

    public function getDataItem($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
}
