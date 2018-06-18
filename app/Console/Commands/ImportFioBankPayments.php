<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Services\DatatableService;
use App\Services\PaymentService;
use App\Services\TemplateService;
use FioApi\Transaction;
use Illuminate\Console\Command;
use Auth;
use Mail;
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

    public function __construct()
    {
        parent::__construct();


        $this->paymentService                = new PaymentService(new PaymentRepository, new AccountRepository, new DatatableService);
        $this->contatMailer                  = new ContactMailer(new TemplateService);
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
    }

    public function fire()
    {
        $this->info(date('r') . ' Loading payments...');

        $emailReceipt = (bool) $this->option('email-receipt');

        try
        {
            $downloader = new \FioApi\Downloader($this->token);
            $transactionList = $downloader->downloadLast();

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

        // check if payment already exists
        if($payment = Payment::where('private_notes', $hash)->first())
        {
            $this->warn(date('r') . ' Payment already exists, skipping...');
            return null;
        }

        // search invoice if VS provided
        if( ! $transaction->getVariableSymbol()) return false;

        // search for invoice
        $invoice = Invoice::whereNotIn('invoice_status_id', array(INVOICE_STATUS_DRAFT, INVOICE_STATUS_PAID))
            ->where('is_deleted', '!=', 1)
            ->where($this->variableSymbolCustomFieldName, $transaction->getVariableSymbol())
            ->first();

        // invoice not found, so continue to next payment
        if( ! $invoice) return false;

        // check payment has been marked sent
        $invoice->markSentIfUnsent();

        $paymentData = array(
            'amount' => $transaction->getAmount(),
            'payment_type_id' => PAYMENT_TYPE_BANK_TRANSFER,
            'payment_date_sql' => $transaction->getDate()->format('Y-m-d'),
            'transaction_reference' => $this->getTransactionSummary($transaction, false),
            'private_notes' => $hash,
            'convert_currency' => 0,
            'exchange_currency_id' => '',
            'exchange_rate' => '',
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
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
        if($includeHash) $result .= 'Hash: '.$hash."\n\n";

        return $result;
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
