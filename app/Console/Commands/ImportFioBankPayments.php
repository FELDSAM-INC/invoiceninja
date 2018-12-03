<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use FioApi\Transaction;
use Illuminate\Console\Command;
use Auth;
use DB;
use Mail;
use App;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ImportFioBankPayments.
 */
class ImportFioBankPayments extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:import-fio-bank-payments {--email-receipt=}';

    /**
     * @var string
     */
    protected $description = 'Import payments from account in Fio Bank';

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var ContactMailer
     */
    protected $contatMailer;

    /**
     * @var array hold transaction that can not be paired
     */
    protected $canNotPair = array();

    /**
     * @var string FIO Bank API token
     */
    protected $token;

    /**
     * @var string name of invoice custom field used as variable symbol
     */
    protected $variableSymbolCustomFieldName;

    public function __construct(PaymentService $paymentService, InvoiceService $invoiceService, ContactMailer $contactMailer)
    {
        parent::__construct();

        $this->paymentService = $paymentService;
        $this->invoiceService = $invoiceService;
        $this->contatMailer   = $contactMailer;
    }

    public function fire()
    {
        $this->token                         = env('FIO_API_TOKEN');
        $this->variableSymbolCustomFieldName = env('INVOICE_VARIABLE_SYMBOL_CUSTOM_FIELD_NAME');

        if( ! $this->token)
        {
            $this->error(date('r') . ' There is no API token set. Please set it in .env file FIO_API_TOKEN={token}');
            exit(1);
        }

        if( ! $this->variableSymbolCustomFieldName)
        {
            $this->error(date('r') . ' There is no invoice custom field for use as variable symbol set. Please set it in .env file INVOICE_VARIABLE_SYMBOL_CUSTOM_FIELD_NAME={custom_field_name}');
            exit(1);
        }

        $this->info(date('r') . ' Loading payments...');

        $emailReceipt = (bool) $this->option('email-receipt');

        try
        {
            $downloader = new \FioApi\Downloader($this->token);
            $transactionList = $downloader->downloadSince(new \DateTimeImmutable('-12 hours'));

            foreach ($transactionList->getTransactions() as $transaction)
            {
                $payment = $this->processTransaction($transaction);

                if($payment && $emailReceipt)
                {
                    $this->contatMailer->sendPaymentConfirmation($payment);
                }

                // can not pair transaction
                if($payment === false)
                {
                    $this->canNotPair[] = $transaction;
                }
            }
        }
        catch(\Exception $e)
        {
            $this->error(date('r') . ' ' . $e->getMessage());
            $this->error(date('r') . ' ' . $e->getTraceAsString());
            exit(1);
        }

        // send info about transactions that can not be paired
        if ($this->canNotPair && $errorEmail = env('ERROR_EMAIL'))
        {
            $body = 'Some transactions need attention:'."\n\n";
            foreach($this->canNotPair as $transaction)
            {
                $body .= $this->getTransactionSummary($transaction);
            }

            Mail::raw($body, function ($message) use ($errorEmail) {
                $message->to($errorEmail)
                    ->from(CONTACT_EMAIL)
                    ->subject("ImportFioBankPayments: Some transactions need attention");
            });
        }
    }

    /**
     * Process single transaction
     *
     * @param Transaction $transaction
     * @return bool|null|Payment null - not interested, false - can not pair, Payment
     */
    protected function processTransaction(Transaction $transaction)
    {
        // calculate transaction hash
        $hash = md5(serialize($transaction));

        // skip expenses
        if($transaction->getAmount() < 0) return null;

        // skip payments done via payment gateway
        if(strpos($transaction->getComment(), 'Zaúčtování POS terminálů') === 0) return null;

        // skip tax returns
        if($transaction->getConstantSymbol() == '4146') return null;

        // skip insurance payouts
        if($transaction->getConstantSymbol() == '3558') return null;

        // skip HoppyGo payments
        if($transaction->getVariableSymbol() == '4677946') return null;

        // skip one time payment
        if($transaction->getId() == '17694406216') return null;

        // check if payment already exists
        if($payment = Payment::where('private_notes', $hash)->first())
        {
            $this->warn(date('r') . ' Payment already exists, skipping...');
            return null;
        }

        // search invoice if reference is provided
        if( ! $reference = $this->getTransactionReference($transaction)) return false;

        $vsCustomFieldName = $this->variableSymbolCustomFieldName;

        // search for invoice
        $invoice = Invoice::whereNotIn('invoice_status_id', array(INVOICE_STATUS_DRAFT, INVOICE_STATUS_PAID))
            ->where('is_deleted', '!=', 1)
            ->where(function (&$query) use($vsCustomFieldName, $reference) {
                $query->where($vsCustomFieldName, $reference)
                    ->orWhere(DB::raw('LPAD('.$vsCustomFieldName.', 10, "0")'), $reference);
            })
            ->first();

        // invoice not found or can not convert amount, so continue to next payment
        if( ! $invoice || ! $transactionAmountData = $this->getTransactionAmountData($invoice, $transaction)) return false;

        // assign data to vars
        list($convert_currency, $exchange_currency_id, $exchange_rate, $amount) = $transactionAmountData;

        // check if invoice is quote and if is, them convert it
        if($invoice->isQuote()) {
            $invoice = $this->invoiceService->convertQuote($invoice);
        }

        // check payment has been marked sent
        $invoice->markSentIfUnsent();

        $paymentData = array(
            'amount'                => $amount,
            'payment_type_id'       => PAYMENT_TYPE_BANK_TRANSFER,
            'payment_date_sql'      => $transaction->getDate()->format('Y-m-d'),
            'transaction_reference' => $this->getTransactionSummary($transaction, false),
            'private_notes'         => $hash,
            'convert_currency'      => $convert_currency,
            'exchange_currency_id'  => $exchange_currency_id,
            'exchange_rate'         => round(1 / $exchange_rate, 4),
            'invoice_id'            => $invoice->id,
            'client_id'             => $invoice->client_id,
        );

        // login as invoice user
        Auth::loginUsingId($invoice->user_id);

        // create and store payment
        // if the payment amount is more than the balance them PaymentService creates a credit
        $payment = $this->paymentService->save($paymentData, null, $invoice);

        // logout user
        Auth::logout();

        return $payment;
    }

    /**
     * Check invoice currency and convert amount if needed
     *
     * @param Invoice       $invoice
     * @param Transaction   $transaction
     * @return bool|array   $convert_currency, $exchange_currency_id, $exchange_rate, $amount
     */
    protected function getTransactionAmountData(Invoice $invoice, Transaction $transaction)
    {
        $account = $invoice->account()->first();
        $invoiceCurrency = $invoice->client()->first()->currency()->first();

        // nothing to do
        if($invoiceCurrency === null || $account->currency_id === $invoiceCurrency->id)
        {
            return array(0, 0, 1, $transaction->getAmount());
        }

        if(! $invoiceExchangeRate = $this->getInvoiceExchangeRate($account, $invoice))
        {
            return false;
        }

        $actualExchangeRate = $invoiceCurrency->exchange_rate;

        // convert amount
        $amount = $transaction->getAmount() * $actualExchangeRate;

        // received amount is not equal to balance
        // calculate allowed diff from total amount
        // get percentual diff from exchange rates + add small 3%
        $percentualDiff = abs(1 - $actualExchangeRate / $invoiceExchangeRate) + 0.03;
        $allowedDiff    = $amount * $percentualDiff;

        // is withing allowed diff
        // this can happen due to exchange rates floating
        if($amount < $invoice->balance && $amount >= $invoice->balance - $allowedDiff || $amount > $invoice->balance && $amount <= $invoice->balance + $allowedDiff)
        {
            // return with corrected exchange rate
            return array(1, $account->currency_id, ($invoice->balance / $transaction->getAmount()), $invoice->balance);
        }

        // if received amount is much greater that balance, just return a let payment create with credit
        // or if amount is equal or much less that invoice balance
        return array(1, $account->currency_id, $actualExchangeRate, $amount);
    }

    /**
     * Get invoice exchange rate
     *
     * @param Account $account
     * @param Invoice $invoice
     * @return bool|string
     */
    protected function getInvoiceExchangeRate(Account $account, Invoice $invoice)
    {
        App::setLocale($account->language()->first()->locale);

        $exchangeRateTranslation = strtolower(trans('texts.exchange_rate'));

        if($exchangeRateTranslation == strtolower($account->custom_fields->invoice_text1))
        {
            return $invoice->custom_text_value1;
        }

        if($exchangeRateTranslation == strtolower($account->custom_fields->invoice_text2))
        {
            return $invoice->custom_text_value2;
        }

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param  bool $includeHash
     * @return string
     */
    protected function getTransactionSummary(Transaction $transaction, $includeHash = true)
    {
        $hash = md5(serialize($transaction));

        $result  = 'User: '.$transaction->getUserIdentity()."\n";
        if($transaction->getUserMessage()) $result .= 'User Message: '.$transaction->getUserMessage()."\n";

        $result .= 'Sender Account: '.$transaction->getSenderAccountNumber();
        if($transaction->getSenderBankCode()) $result .= '/'.$transaction->getSenderBankCode();
        $result .= "\n";

        $result .= 'Amount: '.$transaction->getAmount().' '.$transaction->getCurrency()."\n";

        if($transaction->getVariableSymbol()) $result .= 'VS: '.$transaction->getVariableSymbol()."\n";
        if($transaction->getConstantSymbol()) $result .= 'KS: '.$transaction->getConstantSymbol()."\n";
        if($transaction->getSpecificSymbol()) $result .= 'SS: '.$transaction->getSpecificSymbol()."\n";

        $result .= 'Date: '.$transaction->getDate()->format('Y-m-d')."\n";
        $result .= 'ID: '.$transaction->getId()."\n";
        if($includeHash) $result .= 'Hash: '.$hash."\n\n";

        return $result;
    }

    /**
     * @param Transaction $transaction
     * @return string
     */
    protected function getTransactionReference(Transaction $transaction)
    {
        if( ! $reference = $transaction->getVariableSymbol())
        {
            $reference = $transaction->getUserMessage();

            // try to extract VS
            if(preg_match('/VS([0-9]{7,10})|([0-9]{7,10})|([0-9]{6}-[0-9]{1,3})/', $reference, $m))
            {
                $reference = $m[0];
            }
        }

        // sanitize
        return preg_replace('/[^0-9]/', '', $reference);
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
        return [
            ['email-receipt', null, InputOption::VALUE_OPTIONAL, 'Email Receipt', false],
        ];
    }
}
