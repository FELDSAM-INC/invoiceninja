<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;
use Barracuda\ArchiveStream\Archive;
use App\Models\TaxRate;

class RecurringQuoteReport extends AbstractReport
{
    public function getColumns()
    {
        $columns = [
            'client' => [],
            'quote_number' => [],
            'quote_date' => [],
            'frequency' => [],
            'amount' => [],
            'status' => [],
            'private_notes' => ['columnSelector-false'],
            'user' => ['columnSelector-false'],
        ];

        if (TaxRate::scope()->count()) {
            $columns['tax'] = ['columnSelector-false'];
        }

        $account = auth()->user()->account;

        if ($account->customLabel('invoice_text1')) {
            $columns[$account->present()->customLabel('invoice_text1')] = ['columnSelector-false', 'custom'];
        }
        if ($account->customLabel('invoice_text2')) {
            $columns[$account->present()->customLabel('invoice_text2')] = ['columnSelector-false', 'custom'];
        }

        return $columns;
    }

    public function run()
    {
        $account = Auth::user()->account;
        $statusIds = $this->options['status_ids'];
        $exportFormat = $this->options['export_format'];
        $hasTaxRates = TaxRate::scope()->count();
        $group = $this->options['group'];
        $subgroup = $this->options['subgroup'];

        $clients = Client::scope()
                        ->orderBy('name')
                        ->withArchived()
                        ->with('contacts', 'user')
                        ->with(['invoices' => function ($query) use ($statusIds) {
                            $query->recurringQuote()
                                  ->withArchived()
                                  ->statusIds($statusIds)
                                  ->where('start_date', '>=', $this->startDate)
                                  ->where('start_date', '<=', $this->endDate)
                                  ->with(['invoice_items', 'invoice_status', 'frequency']);
                        }]);

        if ($this->isExport && $exportFormat == 'zip') {
            if (! extension_loaded('GMP')) {
                die(trans('texts.gmp_required'));
            }

            $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.quote_documents')));
            foreach ($clients->get() as $client) {
                foreach ($client->invoices as $invoice) {
                    foreach ($invoice->documents as $document) {
                        $name = sprintf('%s_%s_%s', $invoice->invoice_date ?: date('Y-m-d'), $invoice->present()->titledName, $document->name);
                        $name = str_replace(' ', '_', $name);
                        $zip->add_file($name, $document->getRaw());
                    }
                }
            }
            $zip->finish();
            exit;
        }

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {
                $row = [
                    $this->isExport ? $client->getDisplayName() : $client->present()->link,
                    $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                    $this->isExport ? $invoice->start_date : $invoice->present()->start_date,
                    $invoice->frequency->name,
                    $account->formatMoney($invoice->amount, $client),
                    $invoice->present()->status(),
                    $invoice->private_notes,
                    $invoice->user->getDisplayName(),
                ];

                if ($hasTaxRates) {
                    $row[] = $account->formatMoney($invoice->getTaxTotal(), $client);
                }

                if ($account->customLabel('invoice_text1')) {
                    $row[] = $invoice->custom_text_value1;
                }
                if ($account->customLabel('invoice_text2')) {
                    $row[] = $invoice->custom_text_value2;
                }

                $this->data[] = $row;

                // calculate totals by group
                $amount = $invoice->amount;
                switch ($group) {
                    case 'day':
                        switch ($invoice->frequency->date_interval) {
                            case '2 years':
                                $amount /= 730;
                                break;
                            case '1 year':
                                $amount /= 365;
                                break;
                            case '6 months':
                                $amount /= 183;
                                break;
                            case '4 months':
                                $amount /= 122;
                                break;
                            case '3 months':
                                $amount /= 91;
                                break;
                            case '2 months':
                                $amount /= 61;
                                break;
                            case '1 month':
                                $amount /= 30;
                                break;
                            case '2 weeks':
                                $amount /= 14;
                                break;
                            case '1 week':
                                $amount /= 7;
                                break;
                        }
                        break;
                    case 'monthyear':
                        switch ($invoice->frequency->date_interval) {
                            case '2 years':
                                $amount /= 24;
                                break;
                            case '1 year':
                                $amount /= 12;
                                break;
                            case '6 months':
                                $amount /= 6;
                                break;
                            case '4 months':
                                $amount /= 4;
                                break;
                            case '3 months':
                                $amount /= 3;
                                break;
                            case '2 months':
                                $amount /= 2;
                                break;
                            case '2 weeks':
                                $amount *= 2;
                                break;
                            case '1 week':
                                $amount *= 4;
                                break;
                        }
                        break;
                    case 'year':
                        switch ($invoice->frequency->date_interval) {
                            case '2 years':
                                $amount /= 2;
                                break;
                            case '6 months':
                                $amount *= 2;
                                break;
                            case '4 months':
                                $amount *= 3;
                                break;
                            case '3 months':
                                $amount *= 4;
                                break;
                            case '2 months':
                                $amount *= 6;
                                break;
                            case '1 month':
                                $amount *= 12;
                                break;
                            case '2 weeks':
                                $amount *= 24;
                                break;
                            case '1 week':
                                $amount *= 48;
                                break;
                        }
                        break;
                }

                $this->addToTotals($client->currency_id, 'amount', $amount);

                if ($subgroup == 'status') {
                    $dimension = $invoice->statusLabel();
                } else {
                    $dimension = $this->getDimension($client);
                }

                $this->addChartData($dimension, $invoice->invoice_date, $invoice->amount);
            }
        }
    }
}
