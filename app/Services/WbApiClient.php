<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WbApiClient
{
    protected Client $http;
    protected string $apiKey;

    public function __construct()
    {
        $baseUrl = env('WB_API_HOST', 'http://109.73.206.144:6969');
        $this->apiKey = env('WB_API_KEY', 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie');

        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 30,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }


    public function getPaginated(string $endpoint, array $params = [], int $limit = 500): array
    {
        $allData = [];
        $page = 1;

        if ($endpoint === 'stocks') {
            $params['dateFrom'] = date('Y-m-d');
            unset($params['dateTo']);
        }

        do {
            $query = array_merge($params, [
                'key' => $this->apiKey,
                'page' => $page,
                'limit' => $limit,
            ]);

            try {
                $response = $this->http->request('GET', "api/{$endpoint}", ['query' => $query]);
                $status = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                if ($status !== 200) {
                    throw new \RuntimeException(
                        "API request failed with status {$status}: " . ($body['error'] ?? 'Unknown error')
                    );
                }

                $data = $body['data'] ?? [];
                $allData = array_merge($allData, $data);

                if (count($data) < $limit) {
                    break;
                }

                $page++;
            } catch (GuzzleException $e) {
                Log::error("WB API request failed: " . $e->getMessage());
                break;
            }
        } while (true);

        return $allData;
    }
}
