<?php

namespace App\Console\Commands\Token;

use App\Models\Token;
use Illuminate\Console\Command;

class ListTokens extends Command
{
    protected $signature = 'token:list {--account_id= : Показать токены только для этого аккаунта}';

    protected $description = 'Показать список всех токенов';

    public function handle()
    {
        $query = Token::with(['account', 'tokenType']);

        if ($this->option('account_id')) {
            $query->where('account_id', $this->option('account_id'));
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('Токены не найдены');
            return 0;
        }

        $this->table(
            ['ID', 'Название', 'Аккаунт', 'Тип токена', 'Активен', 'Создан'],
            $tokens->map(function ($token) {
                return [
                    $token->id,
                    $token->name,
                    $token->account->name,
                    $token->tokenType->name,
                    $token->is_active ? '✅' : '❌',
                    $token->created_at->format('d.m.Y H:i')
                ];
            })
        );

        return 0;
    }
}
