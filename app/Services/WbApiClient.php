<?php

namespace App\Services;

use App\Models\Account;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class WbApiClient
{
    protected Client $http;
    protected string $apiKey;
    protected int $maxRetries = 5;
    protected int $initialDelay = 1;
    protected bool $debug = true;
    protected ?Account $account = null;
    protected array $retryableStatusCodes = [400, 429, 500, 502, 503, 504];

    public function __construct(Account $account = null, string $apiKey = null)
    {
        $this->debug = (bool) env('APP_DEBUG', true);

        if ($account) {
            $this->account = $account;
            $baseUrl = $account->apiService->base_url;

            // Используем переданный токен или активный токен аккаунта
            if ($apiKey) {
                $this->apiKey = $apiKey;
            } elseif ($account->activeToken) {
                $this->apiKey = $account->activeToken->token_value;
            } else {
                // fallback: токен из env
                $this->apiKey = env('WB_API_KEY', 'ВАШ_ТОКЕН_ЗДЕСЬ');
                $this->warn("Аккаунт {$account->id} ({$account->name}) не имеет активного токена. Используется токен из env.");
            }
        } else {
            $baseUrl = env('WB_API_HOST', 'http://109.73.206.144:6969');
            $this->apiKey = $apiKey ?? env('WB_API_KEY', 'ВАШ_ТОКЕН_ЗДЕСЬ');
        }

        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 30,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        $this->logInfo("Инициализирован WbApiClient", [
            'base_uri' => $baseUrl,
            'account_id' => $account ? $account->id : 'legacy'
        ]);
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function getPaginated(string $endpoint, array $params = [], int $limit = 500): array
    {
        if ($this->account) {
            $params['account_id'] = $this->account->id;
        }

        $allData = [];
        $page = 1;

        if ($endpoint === 'stocks') {
            $params['dateFrom'] = date('Y-m-d');
            unset($params['dateTo']);
        }

        $this->logDebug("Начало выгрузки данных", [
            'endpoint' => $endpoint,
            'params' => $params,
            'account_id' => $this->account ? $this->account->id : null
        ]);

        do {
            $query = array_merge($params, [
                'key' => $this->apiKey,
                'page' => $page,
                'limit' => $limit,
            ]);

            $attempt = 0;
            $shouldRetry = false;
            $data = [];

            do {
                $attempt++;
                $shouldRetry = false;

                try {
                    $startTime = microtime(true);
                    $response = $this->http->request('GET', "api/{$endpoint}", ['query' => $query]);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);

                    $status = $response->getStatusCode();
                    $body = json_decode($response->getBody()->getContents(), true);

                    $this->logRequestInfo($endpoint, $status, $duration, $attempt, $page);

                    if (in_array($status, $this->retryableStatusCodes)) {
                        $wait = $this->calculateBackoff($attempt);
                        $this->warn("Временная ошибка API {$status} для {$endpoint}. Повтор через {$wait} сек. (попытка {$attempt}/{$this->maxRetries})");
                        sleep($wait);
                        $shouldRetry = true;
                        continue;
                    }

                    if ($status !== 200) {
                        throw new \RuntimeException("Ошибка API {$status}: " . ($body['error'] ?? 'Неизвестная ошибка'));
                    }

                    $data = $body['data'] ?? [];
                    $allData = array_merge($allData, $data);

                    $this->logDebug("Данные получены", [
                        'page' => $page,
                        'items' => count($data),
                        'total' => count($allData),
                        'endpoint' => $endpoint
                    ]);

                } catch (RequestException $e) {
                    $this->logError("Ошибка запроса к API: " . $e->getMessage(), [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'account_id' => $this->account ? $this->account->id : null
                    ]);

                    if ($attempt < $this->maxRetries) {
                        $wait = $this->calculateBackoff($attempt);
                        $this->warn("Повтор запроса через {$wait} сек.");
                        sleep($wait);
                        $shouldRetry = true;
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    $this->logError("Ошибка при запросе к API: " . $e->getMessage(), [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'account_id' => $this->account ? $this->account->id : null
                    ]);

                    if ($attempt < $this->maxRetries) {
                        $wait = $this->calculateBackoff($attempt);
                        $this->warn("Повтор запроса через {$wait} сек.");
                        sleep($wait);
                        $shouldRetry = true;
                        continue;
                    }
                    throw $e;
                }

            } while ($shouldRetry && $attempt <= $this->maxRetries);

            if (count($data) < $limit) {
                $this->logInfo("Выгрузка данных завершена", [
                    'endpoint' => $endpoint,
                    'total_pages' => $page,
                    'total_items' => count($allData),
                    'account_id' => $this->account ? $this->account->id : null
                ]);
                break;
            }

            $page++;
        } while (true);

        return $allData;
    }

    public function updateToken(string $newToken): void
    {
        $this->apiKey = $newToken;
        $this->http = new Client([
            'base_uri' => $this->http->getConfig('base_uri'),
            'timeout' => 30,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function updateFromAccount(Account $account): void
    {
        $this->account = $account;

        if ($account->activeToken) {
            $this->apiKey = $account->activeToken->token_value;
            $this->updateToken($this->apiKey);
        } else {
            $this->warn("Аккаунт {$account->id} ({$account->name}) не имеет активного токена. Используется токен из env.");
            $this->apiKey = env('WB_API_KEY', 'ВАШ_ТОКЕН_ЗДЕСЬ');
            $this->updateToken($this->apiKey);
        }
    }

    protected function calculateBackoff(int $attempt): int
    {
        $backoff = $this->initialDelay * (2 ** ($attempt - 1));
        $jitter = mt_rand(0, 1000) / 1000;
        return (int)ceil($backoff + $jitter);
    }

    protected function logRequestInfo(string $endpoint, int $status, float $durationMs, int $attempt, int $page): void
    {
        $msg = sprintf(
            "[%s] %s | %s | %dms | Попытка: %d | Страница: %d | Аккаунт: %s",
            date('Y-m-d H:i:s'),
            str_pad("HTTP {$status}", 10),
            str_pad($endpoint, 20),
            $durationMs,
            $attempt,
            $page,
            $this->account ? $this->account->id : 'system'
        );

        if ($status >= 400) {
            $this->error($msg);
        } elseif ($attempt > 1) {
            $this->warn($msg);
        } else {
            $this->info($msg);
        }
    }

    protected function line(string $message, string $style = 'info', array $context = []): void
    {
        if (!empty($context)) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $colors = [
            'debug'   => "\033[34m", //🔵
            'info'    => "\033[32m", //🟢
            'warn'    => "\033[33m", //🟡
            'error'   => "\033[31m", //🔴
            'default' => "\033[0m",  //сброс цвета
        ];

        $color = $colors[$style] ?? $colors['default'];
        echo "{$color}{$message}{$colors['default']}\n";

        switch ($style) {
            case 'error':
                Log::error($message, $context);
                break;
            case 'warn':
                Log::warning($message, $context);
                break;
            case 'debug':
                Log::debug($message, $context);
                break;
            default:
                Log::info($message, $context);
        }
    }

    protected function logDebug(string $message, array $context = []): void { $this->line("[DEBUG] {$message}", 'debug', $context); }
    protected function logInfo(string $message, array $context = []): void { $this->line("[INFO] {$message}", 'info', $context); }
    protected function logWarning(string $message, array $context = []): void { $this->line("[WARN] {$message}", 'warn', $context); }
    protected function logError(string $message, array $context = []): void { $this->line("[ERROR] {$message}", 'error', $context); }
    protected function info(string $message): void { $this->line($message, 'info'); }
    protected function warn(string $message): void { $this->line($message, 'warn'); }
    protected function error(string $message): void { $this->line($message, 'error'); }

    // Методы для разных эндпоинтов
    public function getSales(string $dateFrom, string $dateTo, array $params = []): array
    {
        return $this->getPaginated('sales', array_merge($params, [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]));
    }

    public function getOrders(string $dateFrom, string $dateTo, array $params = []): array
    {
        return $this->getPaginated('orders', array_merge($params, [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]));
    }

    public function getStocks(array $params = []): array
    {
        return $this->getPaginated('stocks', $params);
    }

    public function getIncomes(string $dateFrom, string $dateTo, array $params = []): array
    {
        return $this->getPaginated('incomes', array_merge($params, [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]));
    }

    public function get(string $endpoint, array $params = []): array
    {
        $params['key'] = $this->apiKey;

        if ($this->account) {
            $params['account_id'] = $this->account->id;
        }

        $attempt = 0;
        $shouldRetry = false;

        do {
            $attempt++;
            $shouldRetry = false;

            try {
                $startTime = microtime(true);
                $response = $this->http->request('GET', "api/{$endpoint}", ['query' => $params]);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $status = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                $this->logRequestInfo($endpoint, $status, $duration, $attempt, 1);

                if (in_array($status, $this->retryableStatusCodes)) {
                    $wait = $this->calculateBackoff($attempt);
                    $this->warn("Временная ошибка API {$status} для {$endpoint}. Повтор через {$wait} сек. (попытка {$attempt}/{$this->maxRetries})");
                    sleep($wait);
                    $shouldRetry = true;
                    continue;
                }

                if ($status !== 200) {
                    throw new \RuntimeException("Ошибка API {$status}: " . ($body['error'] ?? 'Неизвестная ошибка'));
                }

                return $body;

            } catch (RequestException $e) {
                $this->logError("Ошибка запроса к API: " . $e->getMessage(), [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'account_id' => $this->account ? $this->account->id : null
                ]);

                if ($attempt < $this->maxRetries) {
                    $wait = $this->calculateBackoff($attempt);
                    $this->warn("Повтор запроса через {$wait} сек.");
                    sleep($wait);
                    $shouldRetry = true;
                    continue;
                }
                throw $e;
            } catch (\Exception $e) {
                $this->logError("Ошибка при запросе к API: " . $e->getMessage(), [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'account_id' => $this->account ? $this->account->id : null
                ]);

                if ($attempt < $this->maxRetries) {
                    $wait = $this->calculateBackoff($attempt);
                    $this->warn("Повтор запроса через {$wait} сек.");
                    sleep($wait);
                    $shouldRetry = true;
                    continue;
                }
                throw $e;
            }

        } while ($shouldRetry && $attempt <= $this->maxRetries);
        
        // Если дошли сюда, значит все попытки исчерпаны
        throw new \RuntimeException("Все попытки запроса исчерпаны для endpoint: {$endpoint}");
    }
}
