<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Client;
use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;
use Illuminate\Console\Command;

class InvalidVatNumberException extends \Exception {};

/**
 * Class CheckEuVat.
 */
class CheckEuVat extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:check-eu-vat';

    /**
     * @var string
     */
    protected $description = 'Check validity of clients EU VAT IDs';

    protected static $euVatTerms = array(
        'cs' => 'Dodanie služby/tovaru podľa článku 193 až 196 smernice Rady 2006/112/ES z 28. novembra 2006 o spoločnom systéme dane z pridanej hodnoty v znení smernice Rady 2006/138/ES z 19. decembra 2006 je oslobodené od dane a ide o službu/tovar, ktorú je povinný zdaniť prijímateľ služby/tovaru.',
        'en' => 'Supply of a service/goods is, according to Articles 193 to 196 of Council Directive 2006/112/EC of 28 November 2006 on the common system of value added tax as amendet by Council Directive 2006/138/EC of 19 December, tax free and it is a supply of service/goods, which the person to whom the service/goods is/are supplied, is obliged to tax.',
    );

    public function fire()
    {
        $this->info(date('r') . ' Checking if VIES service is up...');

        $vies = new Vies();

        if (false === $vies->getHeartBeat()->isAlive()) {
            $this->error(date('r') . ' Service is not available at the moment, please try again later.');
            exit(1);
        }

        $this->info(date('r') . ' Checking VAT IDs...');

        // select accounts
        $accounts = Account::all();

        foreach($accounts as $account) {
            // select all except domestic clients
            $clients = Client::where('country_id', '!=', $account->country_id)->where('account_id', $account->id)->get();

            foreach($clients as $client)
            {
                // skip empty numbers
                if( ! $client->vat_number) continue;

                try
                {
                    $this->info(date('r') . ' Validating '.$client->vat_number . '...');

                    // parse number and validate
                    $vatNumber = self::parseVatNumber($client->vat_number);
                    $result = $vies->validateVat($vatNumber['country'], $vatNumber['number']);

                    // check if number is valid
                    if( ! $result->isValid())
                    {
                        $this->warn(date('r') .' Invalid VAT number: '.$client->vat_number);

                        // number is invalid, so update client public notes
                        $this->updateClientWithInvalidNumber($client);

                        continue;
                    }

                    $this->info(date('r') . ' '.$client->vat_number . ' is valid!');

                    // number is valid, so update client public notes
                    $this->updateClientWithValidNumber($client);
                }
                catch (ViesServiceException $e)
                {
                    $this->warn(date('r') . ' ' . $e->getMessage());
                }
                catch (InvalidVatNumberException | ViesException $e)
                {
                    $this->warn(date('r') . ' ' . $e->getMessage());

                    // number has invalid format, so update client notes
                    $this->updateClientWithInvalidNumberFormat($client, $e->getMessage());
                }
                catch (\Exception $e)
                {
                    $this->warn(date('r') . ' ' . $e->getMessage(). ' on ' . $client->vat_number);
                }
            }
        }
    }

    protected function updateClientWithValidNumber(Client $client)
    {
        $language = $client->language()->first();

        // if there is no lang, use CS
        if($language)
        {
            $terms = (isset(self::$euVatTerms[$language->locale])) ? self::$euVatTerms[$language->locale] : self::$euVatTerms['en'];
        }
        else
        {
            $terms = self::$euVatTerms['cs'];
        }

        $client->public_notes = $terms;
        $client->save();
    }

    protected function updateClientWithInvalidNumber(Client $client)
    {
        $client->public_notes = '';
        $client->save();
    }

    protected function updateClientWithInvalidNumberFormat(Client $client, $error)
    {
        $client->public_notes = '';
        $client->private_notes = $error;
        $client->save();
    }

    /**
     * @param $vatNumber
     * @return array
     * @throws InvalidVatNumberException
     */
    protected static function parseVatNumber($vatNumber)
    {
        $vatNumber = preg_replace('/\s/', '', $vatNumber);
        if( ! preg_match('/^([a-zA-Z]{2})[-]?([a-zA-Z0-9]+)$/', $vatNumber, $m))
        {
            throw new InvalidVatNumberException('Invalid VAT number format: '.$vatNumber);
        }

        return array('country' => strtoupper($m[1]), 'number' => $m[2]);
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
