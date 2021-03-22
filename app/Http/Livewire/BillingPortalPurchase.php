<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use App\Factory\ClientFactory;
use App\Models\BillingSubscription;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BillingPortalPurchase extends Component
{
    /**
     * Random hash generated by backend to handle the tracking of state.
     *
     * @var string
     */
    public $hash;

    /**
     * Top level text on the left side of billing page.
     *
     * @var string
     */
    public $heading_text;


    /**
     * E-mail address model for user input.
     *
     * @var string
     */
    public $email;

    /**
     * Password model for user input.
     *
     * @var string
     */
    public $password;

    /**
     * Instance of billing subscription.
     *
     * @var BillingSubscription
     */
    public $billing_subscription;

    /**
     * Instance of client contact.
     *
     * @var null|ClientContact
     */
    public $contact;

    /**
     * Rules for validating the form.
     *
     * @var \string[][]
     */
    protected $rules = [
        'email' => ['required', 'email'],
    ];

    /**
     * Id for CompanyGateway record.
     *
     * @var string|integer
     */
    public $company_gateway_id;

    /**
     * Id for GatewayType.
     *
     * @var string|integer
     */
    public $payment_method_id;

    /**
     * List of steps that frontend form follows.
     *
     * @var array
     */
    public $steps = [
        'passed_email' => false,
        'existing_user' => false,
        'fetched_payment_methods' => false,
        'fetched_client' => false,
        'show_start_trial' => false,
    ];

    /**
     * List of payment methods fetched from client.
     *
     * @var array
     */
    public $methods = [];

    /**
     * Instance of \App\Models\Invoice
     *
     * @var Invoice
     */
    public $invoice;

    /**
     * Coupon model for user input
     *
     * @var string
     */
    public $coupon;

    /**
     * Quantity for seats
     *
     * @var int
     */
    public $quantity = 1;

    /**
     * First-hit request data (queries, locales...).
     *
     * @var array
     */
    public $request_data;

    /**
     * Price of product.
     *
     * @var string
     */
    public $price;

    /**
     * Handle user authentication
     *
     * @return $this|bool|void
     */
    public function authenticate()
    {
        $this->validate();

        $contact = ClientContact::where('email', $this->email)->first();

        if ($contact && $this->steps['existing_user'] === false) {
            return $this->steps['existing_user'] = true;
        }

        if ($contact && $this->steps['existing_user']) {
            $attempt = Auth::guard('contact')->attempt(['email' => $this->email, 'password' => $this->password]);

            return $attempt
                ? $this->getPaymentMethods($contact)
                : session()->flash('message', 'These credentials do not match our records.');
        }

        $this->steps['existing_user'] = false;

        $contact = $this->createBlankClient();

        if ($contact && $contact instanceof ClientContact) {
            $this->getPaymentMethods($contact);
        }
    }

    /**
     * Create a blank client. Used for new customers purchasing.
     *
     * @return mixed
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    protected function createBlankClient()
    {
        $company = $this->billing_subscription->company;
        $user = $this->billing_subscription->user;

        $client_repo = new ClientRepository(new ClientContactRepository());

        $data = [
            'name' => 'Client Name',
            'contacts' => [
                ['email' => $this->email],
            ],
            'settings' => [],
        ];

        if (array_key_exists('locale', $this->request_data)) {
            $record = DB::table('languages')->where('locale', $this->request_data['locale'])->first();

            if ($record) {
                $data['settings']['language_id'] = (string)$record->id;
            }
        }

        $client = $client_repo->save($data, ClientFactory::create($company->id, $user->id));

        return $client->contacts->first();
    }

    /**
     * Fetching payment methods from the client.
     *
     * @param ClientContact $contact
     * @return $this
     */
    protected function getPaymentMethods(ClientContact $contact): self
    {
        if ($this->billing_subscription->trial_enabled) {
            $this->heading_text = ctrans('texts.plan_trial');
            $this->steps['show_start_trial'] = true;

            return $this;
        }

        $this->steps['fetched_payment_methods'] = true;

        $this->methods = $contact->client->service()->getPaymentMethods(1000);

        $this->heading_text = ctrans('texts.payment_methods');

        Auth::guard('contact')->login($contact);

        $this->contact = $contact;

        return $this;
    }

    /**
     * Middle method between selecting payment method &
     * submitting the from to the backend.
     *
     * @param $company_gateway_id
     * @param $gateway_type_id
     */
    public function handleMethodSelectingEvent($company_gateway_id, $gateway_type_id)
    {
        $this->company_gateway_id = $company_gateway_id;
        $this->payment_method_id = $gateway_type_id;

        $this->handleBeforePaymentEvents();
    }

    /**
     * Method to handle events before payments.
     *
     * @return void
     */
    public function handleBeforePaymentEvents()
    {
        $data = [
            'client_id' => $this->contact->client->id,
            'date' => now()->format('Y-m-d'),
            'invitations' => [[
                'key' => '',
                'client_contact_id' => $this->contact->hashed_id,
            ]],
            'user_input_promo_code' => $this->coupon,
            'coupon' => empty($this->billing_subscription->promo_code) ? '' : $this->coupon,
            'quantity' => $this->quantity,
        ];

        $this->invoice = $this->billing_subscription
            ->service()
            ->createInvoice($data)
            ->service()
            ->markSent()
            ->save();

        Cache::put($this->hash, [
            'billing_subscription_id' => $this->billing_subscription->id,
            'email' => $this->email ?? $this->contact->email,
            'client_id' => $this->contact->client->id,
            'invoice_id' => $this->invoice->id],
            now()->addMinutes(60)
        );

        $this->emit('beforePaymentEventsCompleted');
    }

    /**
     * Proxy method for starting the trial.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function handleTrial()
    {
        return $this->billing_subscription->service()->startTrial([
            'email' => $this->email ?? $this->contact->email,
        ]);
    }

    /**
     * Update quantity property.
     *
     * @param string $option
     * @return int
     */
    public function updateQuantity(string $option): int
    {
        if ($this->quantity == 1 && $option == 'decrement') {
            return $this->quantity;
        }

        if ($this->quantity >= $this->billing_subscription->max_seats_limit && $option == 'increment') {
            return $this->quantity;
        }

        if ($option == 'increment') {
            $this->quantity++;
            return $this->price = (int) $this->price + $this->billing_subscription->product->price;
        }

        $this->quantity--;
        $this->price = (int) $this->price - $this->billing_subscription->product->price;

        return 0;
    }

    public function render()
    {
        if ($this->contact instanceof ClientContact) {
            $this->getPaymentMethods($this->contact);
        }

        return render('components.livewire.billing-portal-purchase');
    }
}
