<?php

namespace App\Console\Commands;

use App\Libraries\CurlUtils;
use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\NotLoadedException;

/**
 * Class ImportFioBankPayments.
 */
class ImportFioBankExchangeRates extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:import-fio-bank-exchange-rates';

    /**
     * @var string
     */
    protected $description = 'Import exchange rates from Fio Bank';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        try {
            $this->downloadRates();
        }
        catch(\Exception $e)
        {
            $this->error(date('r') . ' ' . $e->getMessage());
            $this->error(date('r') . ' ' . $e->getTraceAsString());

            // send info about transactions that can not be paired
            if ($errorEmail = env('ERROR_EMAIL'))
            {
                $body = 'Failed to import exchange rates:' . "\n\n";
                $body .= date('r') . ' ' . $e->getMessage() . "\n\n";
                $body .= date('r') . ' ' . $e->getTraceAsString() . "\n\n";

                Mail::raw($body, function ($message) use ($errorEmail) {
                    $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject("ImportFioBankExchangeRates: Failed to import exchange rates");
                });
            }

            exit(1);
        }
    }

    /**
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\CurlException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     * @throws \Exception
     */
    private function downloadRates() {
        $dom = new Dom;
        $dom->loadFromUrl('https://www.fio.cz/akcie-investice/dalsi-sluzby-fio/devizove-konverze', [
            'cleanupInput' => false,
            'enforceEncoding' => 'UTF-8',
        ]);
        $rates = html_entity_decode($dom->find('#settings')->getAttribute('data-kurzy'));
        $rates = json_decode($rates);

        // check if json is valid
        if ($rates === null) {
            throw new \Exception('Error: failed to load exchange rates - failed to decode JSON');
        }

        $base = config('ninja.exchange_rates_base');
        foreach($rates as $rate)
        {
            if($rate->c == $base)
            {
                Currency::whereCode($rate->z)->update(['exchange_rate' => $rate->n]);
            }
        }

        CurlUtils::get(SITE_URL . '?clear_cache=true');
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
