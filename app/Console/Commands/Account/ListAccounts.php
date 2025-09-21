<?php

namespace App\Console\Commands\Account;

use App\Models\Account;
use Illuminate\Console\Command;

class ListAccounts extends Command
{
    protected $signature = 'account:list {--active : Показать только активные аккаунты}';

    protected $description = 'Показать список всех аккаунтов';

    public function handle()
    {
        $query = Account::with(['company', 'apiService', 'activeToken.tokenType']);

        if ($this->option('active')) {
            $query->where('is_active', true);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('Аккаунты не найдены');
            return 0;
        }

        $this->table(
            ['ID', 'Название', 'Компания', 'API Сервис', 'Активен', 'Токен', 'Создан'],
            $accounts->map(function ($account) {
                $tokenInfo = $account->activeToken
                    ? $account->activeToken->tokenType->name . ' ✅'
                    : '❌';

                return [
                    $account->id,
                    $account->name,
                    $account->company->name,
                    $account->apiService->name,
                    $account->is_active ? '✅' : '❌',
                    $tokenInfo,
                    $account->created_at->format('d.m.Y H:i')
                ];
            })
        );

        return 0;
    }
}
