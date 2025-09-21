<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\WbApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WbUpdateDaily extends Command
{
    protected $signature = 'wb:update-daily
                        {--account_id= : ID конкретного аккаунта для обновления}
                        {--all-accounts : Обновить все активные аккаунты}
                        {--token= : API токен Wildberries}
                        {--url= : Базовый URL API (по умолчанию: https://suppliers-api.wildberries.ru)}';

    protected $description = 'Ежедневное обновление данных из API Wildberries для аккаунтов';

    public function handle()
    {
        $accountId = $this->option('account_id');
        $allAccounts = $this->option('all-accounts');
        $token = $this->option('token') ?? env('WB_API_KEY');
        $baseUrl = $this->option('url') ?? 'https://suppliers-api.wildberries.ru';

        try {
            if ($allAccounts) {
                // Обновляем все активные аккаунты
                $accounts = Account::where('is_active', true)->get();

                if ($accounts->isEmpty()) {
                    $this->error('Нет активных аккаунтов для обновления');
                    return 1;
                }

                $this->info("Начало обновления всех активных аккаунтов ({$accounts->count()})...");

                foreach ($accounts as $account) {
                    $this->processAccount($account, $token, $baseUrl);
                }

                $this->info('Обновление всех аккаунтов завершено!');
                return 0;
            }

            if ($accountId) {
                // Обновляем конкретный аккаунт
                $account = Account::find($accountId);

                if (!$account) {
                    $this->error("Аккаунт с ID {$accountId} не найден");
                    return 1;
                }

                $this->info("Начало обновления аккаунта: {$account->name} (ID: {$account->id})...");
                $this->processAccount($account, $token, $baseUrl);

                $this->info('Обновление аккаунта завершено!');
                return 0;
            }

            // По умолчанию обновляем все активные аккаунты
            $accounts = Account::where('is_active', true)->get();

            if ($accounts->isEmpty()) {
                $this->error('Нет активных аккаунтов для обновления');
                return 1;
            }

            $this->info("Начало обновления всех активных аккаунтов ({$accounts->count()})...");

            foreach ($accounts as $account) {
                $this->processAccount($account, $token, $baseUrl);
            }

            $this->info('Обновление всех аккаунтов завершено!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Ошибка при обновлении данных: ' . $e->getMessage());
            Log::error('Ошибка в команде WbUpdateDaily', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function processAccount(Account $account, ?string $defaultToken = null, string $baseUrl = '')    {
        try {
            // Получаем токен аккаунта
            $token = $account->activeToken?->token_value ?? $defaultToken;

             if (!$token) {
                 $this->error("❌ Аккаунт {$account->name} (ID: {$account->id}) не имеет активного токена");
                 $this->line("   Создайте токен командой: php artisan token:create {$account->api_service_id} TOKEN_TYPE_ID ВАШ_ТОКЕН");
                 return;
             }

            // Создаем клиент для аккаунта
            $client = new WbApiClient($account, $token);

            // Устанавливаем базовый URL
            $reflection = new \ReflectionClass($client);
            $property = $reflection->getProperty('http');
            $property->setAccessible(true);

            $property->setValue($client, new \GuzzleHttp\Client([
                'base_uri' => rtrim($baseUrl, '/') . '/',
                'timeout' => 30,
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]));

            $this->info("Обновление данных для аккаунта: {$account->name}");

            // Обновляем данные
            $this->updateSales($client, $account);
            $this->updateOrders($client, $account);
            $this->updateStocks($client, $account);
            $this->updateIncomes($client, $account);

            $this->info("Аккаунт {$account->name} обновлен успешно!");

        } catch (\Exception $e) {
            $this->error("Ошибка при обновлении аккаунта {$account->name}: " . $e->getMessage());
            Log::error("Ошибка обновления аккаунта {$account->name}", [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function updateSales(WbApiClient $client, Account $account)
    {
        $this->info('Обновление продаж...');

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $sales = $client->getPaginated('sales', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);

        $count = 0;
        foreach ($sales as $sale) {
            try {
                DB::table('sales')->updateOrInsert(
                    [
                        'account_id' => $account->id,
                        'sale_id' => $sale['sale_id']
                    ],
                    array_merge($sale, [
                        'created_at' => now(),
                        'updated_at' => now()
                    ])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Ошибка при сохранении продажи', [
                    'account_id' => $account->id,
                    'sale_id' => $sale['sale_id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Обновлено продаж: {$count}");
        return $count;
    }

    protected function updateOrders(WbApiClient $client, Account $account)
    {
        $this->info('Обновление заказов...');

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $orders = $client->getPaginated('orders', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);

        $count = 0;
        foreach ($orders as $order) {
            try {
                DB::table('orders')->updateOrInsert(
                    [
                        'account_id' => $account->id,
                        'order_id' => $order['order_id']
                    ],
                    array_merge($order, [
                        'created_at' => now(),
                        'updated_at' => now()
                    ])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Ошибка при сохранении заказа', [
                    'account_id' => $account->id,
                    'order_id' => $order['order_id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Обновлено заказов: {$count}");
        return $count;
    }

    protected function updateStocks(WbApiClient $client, Account $account)
    {
        $this->info('Обновление остатков...');

        $stocks = $client->getPaginated('stocks', [
            'dateFrom' => now()->format('Y-m-d')
        ]);

        $count = 0;
        foreach ($stocks as $stock) {
            try {
                DB::table('stocks')->updateOrInsert(
                    [
                        'account_id' => $account->id,
                        'nm_id' => $stock['nm_id'],
                        'warehouse_id' => $stock['warehouse_id'],
                        'date' => now()->format('Y-m-d')
                    ],
                    array_merge($stock, [
                        'date' => now()->format('Y-m-d'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Ошибка при сохранении остатка', [
                    'account_id' => $account->id,
                    'nm_id' => $stock['nm_id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Обновлено остатков: {$count}");
        return $count;
    }

    protected function updateIncomes(WbApiClient $client, Account $account)
    {
        $this->info('Обновление поставок...');

        $dateFrom = now()->subMonth()->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $incomes = $client->getPaginated('incomes', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);

        $count = 0;
        foreach ($incomes as $income) {
            try {
                DB::table('incomes')->updateOrInsert(
                    [
                        'account_id' => $account->id,
                        'income_id' => $income['income_id']
                    ],
                    array_merge($income, [
                        'created_at' => now(),
                        'updated_at' => now()
                    ])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Ошибка при сохранении поставки', [
                    'account_id' => $account->id,
                    'income_id' => $income['income_id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Обновлено поставок: {$count}");
        return $count;
    }

    protected function logRequest(string $endpoint, array $params = [])
    {
        $this->line("Запрос к {$endpoint}: " . json_encode($params, JSON_UNESCAPED_UNICODE));
    }
}
