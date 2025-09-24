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
        'sales' => 'sales',
        'orders' => 'orders',
        'stocks' => 'stocks',
        'incomes' => 'incomes',
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
            // Получаем данные через соответствующий метод клиента
            $dateFrom = $params['dateFrom'] ?? now()->subDay()->format('Y-m-d');
            $dateTo = $params['dateTo'] ?? now()->format('Y-m-d');
            
            $response = match($type) {
                'sales' => $this->client->getSales($dateFrom, $dateTo, $params),
                'orders' => $this->client->getOrders($dateFrom, $dateTo, $params),
                'stocks' => $this->client->getStocks($dateFrom, $params),
                'incomes' => $this->client->getIncomes($dateFrom, $dateTo, $params),
                default => []
            };

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
        $items = $data;
        $processed = 0;

        foreach ($items as $item) {
            try {
                switch ($type) {
                    case 'sales':
                        Sale::updateOrCreate(
                            [
                                'account_id' => $accountId,
                                'sale_id' => $item['sale_id'] ?? null
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
