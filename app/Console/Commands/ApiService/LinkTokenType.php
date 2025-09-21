<?php

namespace App\Console\Commands\ApiService;

use App\Models\ApiService;
use App\Models\TokenType;
use Illuminate\Console\Command;

class LinkTokenType extends Command
{
    protected $signature = 'api-service:link-token-type
                            {api_service : Slug или ID API сервиса}
                            {token_type : Slug или ID типа токена}';

    protected $description = 'Связать API сервис с типом токена';

    public function handle()
    {
        $apiService = is_numeric($this->argument('api_service'))
            ? ApiService::find($this->argument('api_service'))
            : ApiService::where('slug', $this->argument('api_service'))->first();

        if (!$apiService) {
            $this->error('API сервис не найден');
            return 1;
        }

        $tokenType = is_numeric($this->argument('token_type'))
            ? TokenType::find($this->argument('token_type'))
            : TokenType::where('slug', $this->argument('token_type'))->first();

        if (!$tokenType) {
            $this->error('Тип токена не найден');
            return 1;
        }

        $apiService->tokenTypes()->syncWithoutDetaching([$tokenType->id => ['is_default' => true]]);

        $this->info("API сервис '{$apiService->name}' успешно связан с типом токена '{$tokenType->name}'");
        return 0;
    }
}
