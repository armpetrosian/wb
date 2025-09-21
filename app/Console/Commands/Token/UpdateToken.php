<?php

namespace App\Console\Commands\Token;

use App\Models\Token;
use Illuminate\Console\Command;

class UpdateToken extends Command
{
    protected $signature = 'token:update
                            {token_id : ID токена}
                            {token_value : Новое значение токена}
                            {--refresh_token= : Refresh токен}
                            {--expires_at= : Дата истечения срока действия (Y-m-d H:i:s)}
                            {--active : Сделать токен активным}
                            {--name= : Новое название токена}';

    protected $description = 'Обновить существующий токен';

    public function handle()
    {
        $token = Token::find($this->argument('token_id'));
        if (!$token) {
            $this->error('Токен не найден');
            return 1;
        }

        $token->token_value = $this->argument('token_value');

        if ($this->option('name')) {
            $token->name = $this->option('name');
        }

        if ($this->option('refresh_token')) {
            $token->refresh_token = $this->option('refresh_token');
        }

        if ($this->option('expires_at')) {
            $token->expires_at = $this->option('expires_at');
        }

        if ($this->option('active')) {
            $token->is_active = true;
        }

        $token->last_used_at = now();
        $token->save();

        $this->info("Токен успешно обновлен!");
        $this->line("ID: " . $token->id);
        $this->line("Название: " . $token->name);
        $this->line("Аккаунт: " . $token->account->name);
        $this->line("Статус: " . ($token->is_active ? 'Активен' : 'Неактивен'));

        return 0;
    }
}
