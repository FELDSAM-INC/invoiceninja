<?php

namespace App\Console\Commands;

use App\Models\AccountGateway;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class FioPayeezeClose.
 */
class FioPayeezeClose extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:fiopayeezy-close';

    /**
     * @var string
     */
    protected $description = 'Close day of payment gateway Fio Bank Payeezy';

    public function fire()
    {
        $this->info(date('r') . ' Running close day...');

        $accountGateway = AccountGateway::where('gateway_id', GATEWAY_FIO)->where('deleted_at', null)->firstOrFail();

        $gateway = new \Omnipay\FioBankPayeezy\Gateway();

        $gateway->initialize( (array) $accountGateway->getConfig());

        $gateway->close()->send();
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
