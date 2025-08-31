<?php

namespace App\Services;

use App\Models\RawResponse;
use App\Models\Sale;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Income;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class WbSyncService
{
    protected WbApiClient $client;
    protected array $endpoints = [
        'sales' => 'api/v1/supplier/sales',
        'orders' => 'api/v1/supplier/orders',
        'stocks' => 'api/v1/supplier/stocks',
        'incomes' => 'api/v1/supplier/incomes',
    ];

    public function __construct(WbApiClient $client)
    {
        $this->client = $client;
    }

    public function sync(string $type, array $params = []): array
    {
        if (!isset($this->endpoints[$type])) {
            throw new \InvalidArgumentException("Invalid sync type: {$type}");
        }

        $endpoint = $this->endpoints[$type];
        $page = 1;
        $limit = 1000;
        $totalProcessed = 0;
        $hasMore = true;

        try {
            while ($hasMore) {
                $response = $this->client->get($endpoint, array_merge($params, [
                    'dateFrom' => $params['dateFrom'] ?? now()->subDay()->toIso8601String(),
                    'dateTo' => $params['dateTo'] ?? now()->toIso8601String(),
                    'page' => $page,
                    'limit' => $limit
                ]));

                // Сохраняем сырой ответ
                $rawResponse = RawResponse::create([
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'response' => $response,
                    'processed' => false
                ]);

                // Обрабатываем данные
                $processed = $this->processResponse($type, $response);
                $totalProcessed += $processed;

                // Обновляем статус обработки
                $rawResponse->update(['processed' => true]);

                // Проверяем, есть ли еще данные
                $hasMore = !empty($response['data']) && count($response['data']) === $limit;
                $page++;

                // Небольшая задержка, чтобы не перегружать API
                if ($hasMore) {
                    sleep(1);
                }
            }

            return [
                'success' => true,
                'processed' => $totalProcessed,
                'message' => "Успешно синхронизировано {$totalProcessed} записей"
            ];

        } catch (\Exception $e) {
            Log::error("WB Sync failed for {$type}: " . $e->getMessage());
            
            return [
                'success' => false,
                'processed' => $totalProcessed,
                'message' => "Ошибка синхронизации: " . $e->getMessage()
            ];
        }
    }

    protected function processResponse(string $type, array $data): int
    {
        $items = $data['data'] ?? [];
        $processed = 0;

        foreach ($items as $item) {
            try {
                switch ($type) {
                    case 'sales':
                        Sale::updateOrCreate(
                            ['sale_id' => $item['saleID'] ?? null],
                            $item
                        );
                        break;
                    case 'orders':
                        Order::updateOrCreate(
                            ['odid' => $item['odid'] ?? null],
                            $item
                        );
                        break;
                    case 'stocks':
                        Stock::updateOrCreate(
                            ['barcode' => $item['barcode'] ?? null],
                            $item
                        );
                        break;
                    case 'incomes':
                        Income::updateOrCreate(
                            ['income_id' => $item['incomeId'] ?? null],
                            $item
                        );
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                Log::error("Failed to process {$type} item: " . $e->getMessage(), [
                    'item' => $item
                ]);
            }
        }

        return $processed;
    }
}
