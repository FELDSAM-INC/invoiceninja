<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public $company;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data['settings'] = $this->company->settings;
        $data['company'] = $this->company;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('email.import.completed', $data)
                    ->attach($this->company->invoices->first()->pdf_file_path());
    }
}
