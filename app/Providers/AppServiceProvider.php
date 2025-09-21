<?php

namespace App\Providers;

use App\Services\WbApiClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Account;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Привязка WbApiClient
        $this->app->bind(WbApiClient::class, function ($app, $params = []) {
            // Передан объект аккаунта напрямую
            if (isset($params['account'])) {
                return new WbApiClient($params['account']);
            }

            // Передан ID аккаунта
            if (isset($params['account_id'])) {
                try {
                    $account = Account::find($params['account_id']);
                    if ($account) {
                        return new WbApiClient($account);
                    }
                } catch (\Exception $e) {
                    // Если таблицы нет или ошибка БД, fallback
                    return new WbApiClient(null, env('WB_API_KEY'));
                }
            }

            // Для консольных команд, которые не требуют аккаунта
            if ($app->runningInConsole() && !$this->isCommandRequiringAccount($app)) {
                return new WbApiClient(null, env('WB_API_KEY'));
            }

            // По умолчанию возвращаем "legacy" клиент без аккаунта
            return new WbApiClient(null, env('WB_API_KEY'));
        });
    }

    public function boot()
    {
        // Можно безопасно обращаться к DB/Schema только в boot()
        try {
            if (Schema::hasTable('accounts')) {
                $account = Account::active()->first();
                if ($account) {
                    // Переопределяем binding для активного аккаунта
                    $this->app->bind(WbApiClient::class, function () use ($account) {
                        return new WbApiClient($account);
                    });
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки БД при boot
        }
    }

    /**
     * Проверка, требует ли текущая консольная команда аккаунт
     */
    protected function isCommandRequiringAccount($app): bool
    {
        $argv = $app->make('request')->server('argv', []);

        if (!is_array($argv)) {
            return false;
        }

        $commandLine = implode(' ', $argv);

        $noAccountCommands = [
            'migrate',
            'migrate:status',
            'db:seed',
            'make:model',
            'make:migration',
            'package:discover',
            'key:generate',
            'config:cache',
            'route:cache',
            'view:cache',
            'storage:link',
            'vendor:publish'
        ];

        foreach ($noAccountCommands as $cmd) {
            if (str_contains($commandLine, $cmd)) {
                return false;
            }
        }

        return true;
    }
}
