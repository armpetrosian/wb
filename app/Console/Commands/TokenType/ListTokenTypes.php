<?php

namespace App\Console\Commands\TokenType;

use App\Models\TokenType;
use Illuminate\Console\Command;

class ListTokenTypes extends Command
{
    protected $signature = 'token-type:list';

    protected $description = 'Показать список всех типов токенов';

    public function handle()
    {
        $tokenTypes = TokenType::all();

        if ($tokenTypes->isEmpty()) {
            $this->info('Типы токенов не найдены');
            return 0;
        }

        $this->table(
            ['ID', 'Название', 'Slug', 'Refresh Token', 'Expires At', 'Создан'],
            $tokenTypes->map(function ($tokenType) {
                return [
                    $tokenType->id,
                    $tokenType->name,
                    $tokenType->slug,
                    $tokenType->requires_refresh_token ? '✅' : '❌',
                    $tokenType->requires_expires_at ? '✅' : '❌',
                    $tokenType->created_at->format('d.m.Y H:i')
                ];
            })
        );

        return 0;
    }
}
