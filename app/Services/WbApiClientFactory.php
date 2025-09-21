<?php

namespace App\Services;

use App\Models\Account;

class WbApiClientFactory
{
    public function make(Account $account)
    {
        return new WbApiClient($account);
    }
}
