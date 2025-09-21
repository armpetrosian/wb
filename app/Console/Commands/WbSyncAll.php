<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WbSyncAll extends Command
{
    protected $signature = 'wb:sync-all
                        {type : Тип данных для синхронизации (sales, orders, stocks, incomes, all)}
                        {--dateFrom= : Начальная дата в формате YYYY-MM-DD}
                        {--dateTo= : Конечная дата в формате YYYY-MM-DD (по умолчанию сегодня)}
                        {--limit=1000 : Количество записей на странице}
                        {--force : Принудительная синхронизация, даже если данные уже есть}';

    protected $description = 'Синхронизация всех активных аккаунтов с API Wildberries';

    public function handle()
    {
        $type = $this->argument('type');
        $validTypes = ['sales', 'orders', 'stocks', 'incomes', 'all'];

        if (!in_array($type, $validTypes)) {
            $this->error("Неверный тип данных. Допустимые значения: " . implode(', ', $validTypes));
            return 1;
        }

        $dateFrom = $this->option('dateFrom') ? Carbon::parse($this->option('dateFrom')) : now()->subDay();
        $dateTo = $this->option('dateTo') ? Carbon::parse($this->option('dateTo')) : now();
        $limit = (int)$this->option('limit');

        try {
            // Получаем все активные аккаунты
            $accounts = Account::where('is_active', true)->get();

            if ($accounts->isEmpty()) {
                $this->error('Нет активных аккаунтов для синхронизации');
                return 1;
            }

            $this->info("НАЧАЛО СИНХРОНИЗАЦИИ ВСЕХ АККАУНТОВ");
            $this->info("Количество аккаунтов: {$accounts->count()}");
            $this->info("Период: с {$dateFrom->format('d.m.Y H:i')} по {$dateTo->format('d.m.Y H:i')}");
            $this->info("Тип данных: {$type}");
            $this->newLine();

            $totalProcessed = 0;
            $totalErrors = 0;
            $startTime = now();

            $progressBar = $this->output->createProgressBar($accounts->count());
            $progressBar->start();

            foreach ($accounts as $account) {
                $result = $this->syncAccount($type, $account, $dateFrom, $dateTo, $limit);

                if ($result['success']) {
                    $totalProcessed += $result['processed'];
                } else {
                    $totalErrors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $executionTime = now()->diffInSeconds($startTime);

            // Итоговая статистика
            $this->info("СТАТИСТИКА СИНХРОНИЗАЦИИ:");
            $this->info("Обработано записей: {$totalProcessed}");
            $this->info("Ошибок: {$totalErrors}");
            $this->info("Время выполнения: {$executionTime} сек");
            $this->info("Средняя скорость: " . ($executionTime > 0 ? round($totalProcessed / $executionTime, 2) : 0) . " записей/сек");

            if ($totalErrors > 0) {
                $this->warn("Были ошибки при синхронизации {$totalErrors} аккаунтов");
                return 1;
            }

            $this->info("СИНХРОНИЗАЦИЯ ВСЕХ АККАУНТОВ ЗАВЕРШЕНА УСПЕШНО!");
            return 0;

        } catch (\Exception $e) {
            $this->error("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
            return 1;
        }
    }

    private function syncAccount(string $type, Account $account, Carbon $dateFrom, Carbon $dateTo, int $limit): array
    {
        try {
            $this->line("");
            $this->info("Синхронизация аккаунта: {$account->name} (ID: {$account->id})");

            // Проверяем, что у аккаунта есть активный токен
             if (!$account->activeToken) {
                 $this->line("  ❌ Аккаунт {$account->name} не имеет активного токена");
                 return [
                     'success' => false,
                     'processed' => 0,
                     'account' => $account->name,
                     'error' => 'Нет активного токена'
                 ];
             }

            // Создаем клиент для аккаунта
            $client = new \App\Services\WbApiClient($account);

            // Создаем сервис с клиентом для аккаунта
            $syncService = new \App\Services\WbSyncService($client);

            $params = [
                'dateFrom' => $dateFrom->toDateTimeLocalString(),
                'dateTo' => $dateTo->toDateTimeLocalString(),
                'limit' => $limit,
            ];

            if ($type === 'all') {
                $totalProcessed = 0;
                $types = ['sales', 'orders', 'stocks', 'incomes'];

                foreach ($types as $t) {
                    $result = $syncService->sync($t, $params);

                    if ($result['success']) {
                        $totalProcessed += $result['processed'];
                        $this->line("  ✅ {$t}: {$result['processed']} записей");
                    } else {
                        $this->line("  ❌ {$t}: {$result['message']}");
                    }
                }

                return [
                    'success' => true,
                    'processed' => $totalProcessed,
                    'account' => $account->name
                ];
            } else {
                $result = $syncService->sync($type, $params);

                if ($result['success']) {
                    $this->line("  ✅ {$type}: {$result['processed']} записей");
                } else {
                    $this->line("  ❌ {$type}: {$result['message']}");
                }

                return [
                    'success' => $result['success'],
                    'processed' => $result['processed'],
                    'account' => $account->name
                ];
            }

        } catch (\Exception $e) {
            $this->line("Ошибка: " . $e->getMessage());

            return [
                'success' => false,
                'processed' => 0,
                'account' => $account->name,
                'error' => $e->getMessage()
            ];
        }
    }
}
