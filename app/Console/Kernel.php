<?php namespace app\Console;

use Utils;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\SendRecurringInvoices',
        'App\Console\Commands\ResetData',
        'App\Console\Commands\CheckData',
        'App\Console\Commands\SendRenewalInvoices',
        'App\Console\Commands\ChargeRenewalInvoices',
        'App\Console\Commands\SendReminders',
        'App\Console\Commands\TestOFX',
        'App\Console\Commands\GenerateResources',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $logFile = storage_path() . '/logs/cron.log';

        $schedule
            ->command('ninja:send-invoices --force')
            ->sendOutputTo($logFile)
            ->withoutOverlapping()
            ->hourly();

        $schedule
            ->command('ninja:send-reminders --force')
            ->sendOutputTo($logFile)
            ->daily();

        if (Utils::isNinja()) {
            $schedule
                ->command('ninja:send-renewals --force')
                ->sendOutputTo($logFile)
                ->daily();
        }
    }
}
