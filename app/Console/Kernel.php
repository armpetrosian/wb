<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // Команды Artisan для приложения
    protected $commands = [
        \App\Console\Commands\Company\CreateCompany::class,
        \App\Console\Commands\ApiService\CreateApiService::class,
        \App\Console\Commands\TokenType\CreateTokenType::class,
        \App\Console\Commands\TokenType\ListTokenTypes::class,
        \App\Console\Commands\Account\CreateAccount::class,
        \App\Console\Commands\Account\ListAccounts::class,
        \App\Console\Commands\Token\CreateToken::class,
        \App\Console\Commands\Token\UpdateToken::class,
        \App\Console\Commands\Token\ListTokens::class,
        \App\Console\Commands\ApiService\LinkTokenType::class,
        \App\Console\Commands\WbSyncAll::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('wb:update-daily')
            ->dailyAt('01:00')
            ->timezone('Europe/Moscow')
            ->appendOutputTo(storage_path('logs/wb-update.log'));

        $schedule->command('wb:update-daily')
            ->dailyAt('13:00')
            ->timezone('Europe/Moscow')
            ->appendOutputTo(storage_path('logs/wb-update.log'));

        // Добавляем планировщик для всех аккаунтов
        $schedule->command('wb:sync-all all')
            ->dailyAt('02:00')
            ->timezone('Europe/Moscow')
            ->appendOutputTo(storage_path('logs/wb-sync-all.log'));

        $schedule->command('wb:sync-all all')
            ->dailyAt('14:00')
            ->timezone('Europe/Moscow')
            ->appendOutputTo(storage_path('logs/wb-sync-all.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
