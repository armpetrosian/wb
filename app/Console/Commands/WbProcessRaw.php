<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RawResponse;
use App\Models\Sale;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Income;
use App\Models\Account;
use Carbon\Carbon;

class WbProcessRaw extends Command
{
    protected $signature = 'wb:process-raw
                            {--account_id= : ID аккаунта для обработки}
                            {--chunk=100 : Размер батча для обработки}
                            {--dateFrom= : Начальная дата (YYYY-MM-DD или days назад)}
                            {--dateTo= : Конечная дата (YYYY-MM-DD, по умолчанию сегодня)}
                            {--fresh : Обрабатывать только свежие данные (за последние 7 дней)}';

    protected $description = 'Process raw_responses and fill normalized tables for specific account';

    public function handle()
    {
        $accountId = $this->option('account_id');
        $chunkSize = (int) $this->option('chunk');

        if (!$accountId) {
            $this->error('Не указан account_id. Используйте --account_id=ID');
            return 1;
        }

        // Получаем и проверяем аккаунт
        $account = Account::find($accountId);
        if (!$account) {
            $this->error("Аккаунт с ID {$accountId} не найден");
            return 1;
        }

        // Проверяем, что у аккаунта есть активный токен
        if (!$account->activeToken) {
            $this->error("Аккаунт {$account->name} не имеет активного токена");
            $this->line("Создайте токен командой: php artisan token:create {$account->api_service_id} TOKEN_TYPE_ID ВАШ_ТОКЕН");
            return 1;
        }

        // Определяем период дат
        $dateFrom = $this->getDateFrom();
        $dateTo = $this->getDateTo();

        $this->info("Начало обработки аккаунта ID: {$accountId}");
        $this->info("Период: с {$dateFrom->format('Y-m-d')} по {$dateTo->format('Y-m-d')}");

        $this->processSales($chunkSize, $accountId, $dateFrom, $dateTo);
        $this->processOrders($chunkSize, $accountId, $dateFrom, $dateTo);
        $this->processStocks($chunkSize, $accountId, $dateFrom, $dateTo);
        $this->processIncomes($chunkSize, $accountId, $dateFrom, $dateTo);

        $this->info('Все данные успешно обработаны.');
        return 0;
    }

    private function getDateFrom(): Carbon
    {
        $dateFrom = $this->option('dateFrom');
        $fresh = $this->option('fresh');

        if ($fresh) {
            return now()->subDays(7);
        }

        if ($dateFrom) {
            // Если указано число, считаем днями
            if (is_numeric($dateFrom)) {
                return now()->subDays((int)$dateFrom);
            }
            // Иначе парсим как дату
            return Carbon::parse($dateFrom);
        }

        // По умолчанию за последние 7 дней
        return now()->subDays(7);
    }

    private function getDateTo(): Carbon
    {
        $dateTo = $this->option('dateTo');
        return $dateTo ? Carbon::parse($dateTo) : now();
    }

    private function processSales(int $chunkSize = 100, int $accountId, Carbon $dateFrom, Carbon $dateTo)
    {
        $query = RawResponse::where('endpoint', 'sales')
            ->where('account_id', $accountId)
            ->whereBetween('fetched_at', [$dateFrom, $dateTo]);

        $total = $query->count();

        if ($total === 0) {
            $this->info("Нет данных продаж для аккаунта {$accountId} за указанный период");
            return;
        }

        $this->info("Обрабатываем {$total} записей продаж батчами по {$chunkSize}");

        $query->chunk($chunkSize, function ($records) use ($accountId) {
            foreach ($records as $r) {
                $item = json_decode($r->response_body, true);

                if (!is_array($item)) continue;

                $saleId = $item['sale_id'] ?? $item['saleID'] ?? null;
                if (!$saleId) continue;

                Sale::updateOrCreate(
                    [
                        'account_id' => $accountId,
                        'sale_id' => $saleId
                    ],
                    [
                        'date' => $item['date'] ?? null,
                        'amount' => $item['amount'] ?? $item['totalPrice'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        $this->info('Продажи обработаны.');
    }

    private function processOrders(int $chunkSize = 100, int $accountId, Carbon $dateFrom, Carbon $dateTo)
    {
        $query = RawResponse::where('endpoint', 'orders')
            ->where('account_id', $accountId)
            ->whereBetween('fetched_at', [$dateFrom, $dateTo]);

        $total = $query->count();

        if ($total === 0) {
            $this->info("Нет данных заказов для аккаунта {$accountId} за указанный период");
            return;
        }

        $this->info("Обрабатываем {$total} записей заказов батчами по {$chunkSize}");

        $query->chunk($chunkSize, function ($records) use ($accountId) {
            foreach ($records as $r) {
                $item = json_decode($r->response_body, true);

                if (!is_array($item)) continue;

                $orderId = $item['order_id'] ?? $item['odid'] ?? null;
                if (!$orderId) continue;

                Order::updateOrCreate(
                    [
                        'account_id' => $accountId,
                        'order_id' => $orderId
                    ],
                    [
                        'date' => $item['date'] ?? null,
                        'total' => $item['total'] ?? $item['totalPrice'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        $this->info('Заказы обработаны.');
    }

    private function processStocks(int $chunkSize = 100, int $accountId, Carbon $dateFrom, Carbon $dateTo)
    {
        $query = RawResponse::where('endpoint', 'stocks')
            ->where('account_id', $accountId)
            ->whereBetween('fetched_at', [$dateFrom, $dateTo]);

        $total = $query->count();

        if ($total === 0) {
            $this->info("Нет данных остатков для аккаунта {$accountId} за указанный период");
            return;
        }

        $this->info("Обрабатываем {$total} записей остатков батчами по {$chunkSize}");

        $processed = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);

        $query->chunk($chunkSize, function ($records) use (&$processed, &$skipped, $bar, $accountId) {
            foreach ($records as $r) {
                $item = json_decode($r->response_body, true);

                if (!is_array($item)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Используем nm_id или barcode как идентификатор
                $sku = $item['nm_id'] ?? $item['barcode'] ?? $item['nmId'] ?? $item['sku'] ?? null;

                if (!$sku) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                try {
                    Stock::updateOrCreate(
                        [
                            'account_id' => $accountId,
                            'sku' => $sku
                        ],
                        [
                            'date' => $item['date'] ?? $item['last_change_date'] ?? now(),
                            'quantity' => $item['quantity'] ?? $item['quantity_full'] ?? 0,
                            'payload' => json_encode($item),
                        ]
                    );
                    $processed++;
                } catch (\Exception $e) {
                    $this->error("Ошибка обработки stock {$sku}: " . $e->getMessage());
                    $skipped++;
                }

                $bar->advance();
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Остатки обработаны. Сохранено: {$processed}, пропущено: {$skipped}");
    }

    private function processIncomes(int $chunkSize = 100, int $accountId, Carbon $dateFrom, Carbon $dateTo)
    {
        $query = RawResponse::where('endpoint', 'incomes')
            ->where('account_id', $accountId)
            ->whereBetween('fetched_at', [$dateFrom, $dateTo]);

        $total = $query->count();

        if ($total === 0) {
            $this->info("Нет данных доходов для аккаунта {$accountId} за указанный период");
            return;
        }

        $this->info("Обрабатываем {$total} записей доходов батчами по {$chunkSize}");

        $query->chunk($chunkSize, function ($records) use ($accountId) {
            foreach ($records as $r) {
                $item = json_decode($r->response_body, true);

                if (!is_array($item)) continue;

                Income::create([
                    'account_id' => $accountId,
                    'date' => $item['date'] ?? null,
                    'amount' => $item['amount'] ?? $item['totalPrice'] ?? null,
                    'payload' => json_encode($item),
                ]);
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        $this->info('Доходы обработаны.');
    }
}
