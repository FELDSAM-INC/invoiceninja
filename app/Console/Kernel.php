<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Utils;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\SendRecurringInvoices',
        'App\Console\Commands\RemoveOrphanedDocuments',
        'App\Console\Commands\ResetData',
        'App\Console\Commands\CheckData',
        'App\Console\Commands\PruneData',
        'App\Console\Commands\CreateTestData',
        'App\Console\Commands\CreateLuisData',
        'App\Console\Commands\SendRenewalInvoices',
        'App\Console\Commands\ChargeRenewalInvoices',
        'App\Console\Commands\SendReminders',
        'App\Console\Commands\TestOFX',
        'App\Console\Commands\MakeModule',
        'App\Console\Commands\MakeClass',
        'App\Console\Commands\InitLookup',
        'App\Console\Commands\CalculatePayouts',
        'App\Console\Commands\UpdateKey',
        'App\Console\Commands\MobileLocalization',
        'App\Console\Commands\SendOverdueTickets',
        'App\Console\Commands\FioPayeezeClose',
        'App\Console\Commands\CheckEuVat',
        'App\Console\Commands\ImportFioBankPayments',
        'App\Console\Commands\ImportFioBankExchangeRates',
        'App\Console\Commands\MakeModuleSettings',
        'App\Console\Commands\ImportToggle',
        'App\Console\Commands\ConvertClientsCurrency',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $logFile = storage_path() . '/logs/cron.log';

        $schedule
            ->command('ninja:send-invoices')
            ->appendOutputTo($logFile)
            ->withoutOverlapping()
            ->hourly();

        $schedule
            ->command('ninja:send-reminders')
            ->appendOutputTo($logFile)
            ->daily();

        $schedule
            ->command('ninja:check-eu-vat')
            ->appendOutputTo($logFile)
            ->daily();

        $schedule
            ->command('ninja:fiopayeezy-close')
            ->appendOutputTo($logFile)
            ->daily();

        $schedule
            ->command('ninja:import-fio-bank-payments --email-receipt=true')
            ->appendOutputTo($logFile)
            ->hourly();
    }
}
