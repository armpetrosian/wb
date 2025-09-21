<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\WbSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WbSync extends Command
{
    protected $signature = 'wb:sync
        {type : Тип данных для синхронизации (sales, orders, stocks, incomes, all)}
        {--account_id= : ID конкретного аккаунта}
        {--all-accounts : Синхронизировать все аккаунты}
        {--dateFrom= : Начальная дата в формате YYYY-MM-DD}
        {--dateTo= : Конечная дата в формате YYYY-MM-DD (по умолчанию сегодня)}
        {--limit=1000 : Количество записей на странице}
        {--force : Принудительная синхронизация, даже если данные уже есть}';

    protected $description = 'Синхронизация данных с API Wildberries для аккаунтов';

    protected WbSyncService $syncService;

    public function __construct(WbSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        $type = $this->argument('type');
        $accountId = $this->option('account_id');
        $allAccounts = $this->option('all-accounts');
        $validTypes = ['sales', 'orders', 'stocks', 'incomes', 'all'];

        if (!in_array($type, $validTypes)) {
            $this->error("Неверный тип данных. Допустимые значения: " . implode(', ', $validTypes));
            return 1;
        }

        $dateFrom = $this->option('dateFrom') ? Carbon::parse($this->option('dateFrom')) : now()->subDay();
        $dateTo = $this->option('dateTo') ? Carbon::parse($this->option('dateTo')) : now();
        $limit = (int)$this->option('limit');

        $params = [
            'dateFrom' => $dateFrom->toDateTimeLocalString(),
            'dateTo' => $dateTo->toDateTimeLocalString(),
        ];

        try {
            if ($allAccounts) {
                // Синхронизируем все активные аккаунты
                $accounts = Account::where('is_active', true)->get();

                if ($accounts->isEmpty()) {
                    $this->error('Нет активных аккаунтов для синхронизации');
                    return 1;
                }

                $this->info("Начало синхронизации всех активных аккаунтов ({$accounts->count()})...");
                $this->line("Тип: {$type}");
                $this->line("Период: с {$dateFrom->format('d.m.Y H:i')} по {$dateTo->format('d.m.Y H:i')}");

                foreach ($accounts as $account) {
                    $this->processAccountSync($type, $account, $params, $limit);
                }

                $this->info("Синхронизация всех аккаунтов завершена!");
                return 0;
            }

            if ($accountId) {
                // Синхронизируем конкретный аккаунт
                $account = Account::find($accountId);

                if (!$account) {
                    $this->error("Аккаунт с ID {$accountId} не найден");
                    return 1;
                }

                $this->info("Начало синхронизации аккаунта: {$account->name} (ID: {$account->id})...");
                $this->line("Тип: {$type}");
                $this->line("Период: с {$dateFrom->format('d.m.Y H:i')} по {$dateTo->format('d.m.Y H:i')}");

                $this->processAccountSync($type, $account, $params, $limit);

                $this->info("Синхронизация аккаунта завершена!");
                return 0;
            }

            // По умолчанию синхронизируем все активные аккаунты
            $accounts = Account::where('is_active', true)->get();

            if ($accounts->isEmpty()) {
                $this->error('Нет активных аккаунтов для синхронизации');
                return 1;
            }

            $this->info("Начало синхронизации всех активных аккаунтов ({$accounts->count()})...");
            $this->line("Тип: {$type}");
            $this->line("Период: с {$dateFrom->format('d.m.Y H:i')} по {$dateTo->format('d.m.Y H:i')}");

            foreach ($accounts as $account) {
                $this->processAccountSync($type, $account, $params, $limit);
            }

            $this->info("Синхронизация всех аккаунтов завершена!");
            return 0;

        } catch (\Exception $e) {
            $this->error("Критическая ошибка: " . $e->getMessage());
            return 1;
        }
    }

    private function processAccountSync(string $type, Account $account, array $params, int $limit): void
    {
        $this->line("");
        $this->info("Синхронизация {$type} для аккаунта: {$account->name} (ID: {$account->id})");

        try {
            // Проверяем, что у аккаунта есть активный токен
             if (!$account->activeToken) {
                 $this->error("Аккаунт {$account->name} не имеет активного токена");
                 $this->line(" Создайте токен командой: php artisan token:create {$account->api_service_id} TOKEN_TYPE_ID ВАШ_ТОКЕН");
                 return;
             }

            // Создаем клиент для аккаунта
            $client = new \App\Services\WbApiClient($account);

            // Создаем сервис с клиентом для аккаунта
            $syncService = new WbSyncService($client);

            $startTime = microtime(true);

            if ($type === 'all') {
                $types = ['sales', 'orders', 'stocks', 'incomes'];
                foreach ($types as $t) {
                    $result = $syncService->sync($t, array_merge($params, [
                        'limit' => $limit,
                    ]));

                    if ($result['success']) {
                        $this->line("{$t}: {$result['processed']} записей");
                    } else {
                        $this->error("{$t}: {$result['message']}");
                    }
                }
            } else {
                $result = $syncService->sync($type, array_merge($params, [
                    'limit' => $limit,
                ]));

                $executionTime = round(microtime(true) - $startTime, 2);

                if ($result['success']) {
                    $this->info("Успешно синхронизировано {$result['processed']} записей за {$executionTime} сек.");
                } else {
                    $this->error("Ошибка при синхронизации: {$result['message']}");
                }
            }

        } catch (\Exception $e) {
            $this->error("Ошибка при синхронизации аккаунта {$account->name}: " . $e->getMessage());
        }
    }
}

