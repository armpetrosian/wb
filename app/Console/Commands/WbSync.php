<?php

namespace App\Console\Commands;

use App\Services\WbSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WbSync extends Command
{
    protected $signature = 'wb:sync 
        {type : Тип данных для синхронизации (sales, orders, stocks, incomes, all)}
        {--dateFrom= : Начальная дата в формате YYYY-MM-DD}
        {--dateTo= : Конечная дата в формате YYYY-MM-DD (по умолчанию сегодня)}
        {--limit=1000 : Количество записей на странице}
        {--force : Принудительная синхронизация, даже если данные уже есть}';

    protected $description = 'Синхронизация данных с API Wildberries';

    protected WbSyncService $syncService;

    public function __construct(WbSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

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

        $params = [
            'dateFrom' => $dateFrom->toDateTimeLocalString(),
            'dateTo' => $dateTo->toDateTimeLocalString(),
        ];

        $this->info("Начало синхронизации данных...");
        $this->line("Тип: {$type}");
        $this->line("Период: с {$dateFrom->format('d.m.Y H:i')} по {$dateTo->format('d.m.Y H:i')}");

        if ($type === 'all') {
            $types = ['sales', 'orders', 'stocks', 'incomes'];
            foreach ($types as $t) {
                $this->processSync($t, $params, $limit);
            }
        } else {
            $this->processSync($type, $params, $limit);
        }

        $this->info("Синхронизация завершена!");
        return 0;
    }

    protected function processSync(string $type, array $params, int $limit): void
    {
        $this->line("");
        $this->info("Синхронизация {$type}...");

        $startTime = microtime(true);
        
        try {
            $result = $this->syncService->sync($type, array_merge($params, [
                'limit' => $limit,
            ]));

            $executionTime = round(microtime(true) - $startTime, 2);

            if ($result['success']) {
                $this->info("Успешно синхронизировано {$result['processed']} записей за {$executionTime} сек.");
            } else {
                $this->error("Ошибка при синхронизации: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->error("Критическая ошибка: " . $e->getMessage());
        }
    }
}
