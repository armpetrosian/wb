<?php

namespace App\Services;

use App\Models\Account;
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
    protected ?Account $account = null;

    protected array $endpoints = [
        'sales' => 'v1/supplier/sales',
        'orders' => 'v1/supplier/orders',
        'stocks' => 'v1/supplier/stocks',
        'incomes' => 'v1/supplier/incomes',
    ];

    public function __construct(WbApiClient $client)
    {
        $this->client = $client;
        $this->account = $client->getAccount();
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
                    'account_id' => $this->account?->id ?? null,
                    'endpoint' => $endpoint,
                    'request_payload' => json_encode($params),
                    'response_body' => json_encode($response),
                    'http_status' => 200,
                    'fetched_at' => now(),
                    'processed' => false
                ]);

                // Обрабатываем данные
                $processed = $this->processResponse($type, $response, $this->account?->id);
                $totalProcessed += $processed;

                // Обновляем статус обработки
                $rawResponse->update(['processed' => true]);

                // Проверяем, есть ли еще данные
                $hasMore = !empty($response['data']) && count($response['data']) === $limit;
                $page++;

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

    protected function processResponse(string $type, array $data, ?int $accountId): int
    {
        $items = $data['data'] ?? [];
        $processed = 0;

        foreach ($items as $item) {
            try {
                switch ($type) {
                    case 'sales':
                        Sale::updateOrCreate(
                            [
                                'account_id' => $accountId,
                                'sale_id' => $item['saleID'] ?? null
                            ],
                            array_merge($item, [
                                'account_id' => $accountId,
                                'date' => $item['date'] ?? now(),
                                'amount' => $item['totalPrice'] ?? 0,
                                'payload' => json_encode($item)
                            ])
                        );
                        break;
                    case 'orders':
                        Order::updateOrCreate(
                            [
                                'account_id' => $accountId,
                                'order_id' => $item['odid'] ?? null
                            ],
                            array_merge($item, [
                                'account_id' => $accountId,
                                'date' => $item['date'] ?? now(),
                                'total' => $item['totalPrice'] ?? 0,
                                'payload' => json_encode($item)
                            ])
                        );
                        break;
                    case 'stocks':
                        // Для остатков используем date как уникальный ключ
                        $date = $item['date'] ?? now()->format('Y-m-d');
                        Stock::updateOrCreate(
                            [
                                'account_id' => $accountId,
                                'date' => $date
                            ],
                            array_merge($item, [
                                'account_id' => $accountId,
                                'date' => $date,
                                'quantity' => $item['quantity'] ?? 0,
                                'payload' => json_encode($item)
                            ])
                        );
                        break;
                    case 'incomes':
                        // Для доходов используем date как уникальный ключ
                        $date = $item['date'] ?? now()->format('Y-m-d');
                        Income::updateOrCreate(
                            [
                                'account_id' => $accountId,
                                'date' => $date
                            ],
                            array_merge($item, [
                                'account_id' => $accountId,
                                'date' => $date,
                                'amount' => $item['totalPrice'] ?? 0,
                                'payload' => json_encode($item)
                            ])
                        );
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                Log::error("Failed to process {$type} item: " . $e->getMessage(), [
                    'item' => $item,
                    'account_id' => $accountId
                ]);
            }
        }

        return $processed;
    }
}
