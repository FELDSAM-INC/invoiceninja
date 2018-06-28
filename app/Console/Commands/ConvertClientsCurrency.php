<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use \DB;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ConvertClientsCurrency.
 */
class ConvertClientsCurrency extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:convert-clients-currency';

    /**
     * @var string
     */
    protected $description = 'Converts Slovakia clients currency to EUR';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $this->info(date('r') . ' Searching for Slovakia clients...');

        $clients = Client::where('is_deleted', '=', false)
            ->where('country_id', '=', 703) // Slovakia
            ->where('currency_id', '!=', 3) // EUR
            ->get();

        $this->info(date('r') . ' ' . $clients->count() . ' Slovakia clients found');

        foreach($clients as $client)
        {
            $this->convertClient($client);
        }
    }

    /**
     * @param Client $client
     */
    protected function convertClient(Client $client)
    {
        // search client invoices
        $invoices = Invoice::where('account_id', '=', $client->account_id)
            ->where('client_id', '=', $client->id)
            ->where('deleted_at', '=', null)
            ->get();

        $this->info(date('r') . ' Client ' . $client->getDisplayName() . ' - ' . $invoices->count() . ' invoices found');

        foreach ($invoices as $invoice)
        {
            $this->convertInvoice($invoice);
        }

        // set client currency to EUR
        DB::table('clients')
            ->where('id', $client->id)
            ->update(['currency_id' => 3]);

//        $client->paid_to_date = '';
//        $client->balance = '';
    }

    /**
     * @param Invoice $invoice
     */
    protected function convertInvoice(Invoice $invoice)
    {
        // check if there is exchange rate filled
        if( ! $exchange_rate = $invoice->custom_text_value2)
        {
            $this->warn(date('r') . ' Invoice number ' . $invoice->invoice_number . ' has no exchange rate defined! Skipping');
            return;
        }

        // sanatize exchange rate
        $exchange_rate = str_replace(',', '.', $exchange_rate);

        // get items
        $items = $invoice->invoice_items()->get();

        $this->info(date('r') . ' ' . $items->count() . ' invoice items found');

        $invoiceAmount = 0;
        foreach ($items as $item)
        {
            $cost = round($item->cost / $exchange_rate, 2);
            $invoiceAmount += $item->cost / $exchange_rate * $item->qty;

            DB::table('invoice_items')
                ->where('id', $item->id)
                ->update(['cost' => $cost]);
        }

        // calculate invoice discount and amount
        $discount      = round($invoice->discount / $exchange_rate, 2);
        $invoiceAmount = round($invoiceAmount * (1 + $invoice->tax_rate1 / 100) - $discount, 2);

        // update payments
        $totalAmountPaid = $this->convertPayments($invoice->id, $exchange_rate, $invoiceAmount);

        // update invoice
        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'custom_text_value2' => $exchange_rate,
                'discount' => $discount,
                'amount' => $invoiceAmount,
                'balance' => ($invoiceAmount - $totalAmountPaid),
            ]);
    }

    /**
     * @param int   $invoice
     * @param float $exchange_rate
     * @return float total amount paid
     */
    protected function convertPayments($invoiceId, $exchange_rate, $invoiceAmount)
    {
        $payments = Payment::where('invoice_id', $invoiceId)
            ->where('deleted_at', '=', null)
            ->get();

        $this->info(date('r') . ' ' . $payments->count() . ' payments found');

        $totalAmount = 0;
        foreach ($payments as $payment)
        {
            $amount = round($payment->amount / $exchange_rate, 2);

            $totalAmount += $amount;

            DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'amount' => $amount,
                    'exchange_rate' => $exchange_rate,
                    'exchange_currency_id' => 51, // CZK - base currency
                ]);
        }

        // correct small diffs
        $diff = $invoiceAmount - $totalAmount;
        if(1 > $diff && $diff > 0 || 0 > $diff && $diff > -1)
        {
            // update last payment
            DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'amount' => ($amount + $diff)
                ]);

            $totalAmount += $diff;
        }

        return $totalAmount;
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
