<?php

namespace App\Console\Commands\Token;

use App\Models\Account;
use App\Models\Token;
use App\Models\TokenType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateToken extends Command
{
    protected $signature = 'token:create
                            {account_id : ID аккаунта}
                            {name : Название токена}
                            {token_value : Значение токена}
                            {--token_type=api_key : Тип токена (slug)}
                            {--refresh_token= : Refresh токен}
                            {--expires_at= : Дата истечения срока действия (Y-m-d H:i:s)}
                            {--active : Сделать токен активным}';

    protected $description = 'Создать новый токен доступа';

    public function handle()
    {
        $account = Account::find($this->argument('account_id'));
        if (!$account) {
            $this->error('Аккаунт не найден');
            return 1;
        }

        $tokenType = TokenType::where('slug', $this->option('token_type'))->first();
        if (!$tokenType) {
            $this->error('Тип токена не найден');
            return 1;
        }

        $tokenData = [
            'account_id' => $account->id,
            'token_type_id' => $tokenType->id,
            'name' => $this->argument('name'),
            'token_value' => $this->argument('token_value'),
            'refresh_token' => $this->option('refresh_token'),
            'is_active' => $this->option('active', false),
        ];

        if ($this->option('expires_at')) {
            $tokenData['expires_at'] = $this->option('expires_at');
        }

        $token = new Token($tokenData);
        $token->save();

        $this->info("Токен успешно создан!");
        $this->line("ID: " . $token->id);
        $this->line("Название: " . $token->name);
        $this->line("Тип токена: " . $tokenType->name);
        $this->line("Аккаунт: " . $account->name);
        $this->line("Статус: " . ($token->is_active ? 'Активен' : 'Неактивен'));

        return 0;
    }
}
