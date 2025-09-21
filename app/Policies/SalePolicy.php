<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Sale $sale)
    {
        return $user->account_id === $sale->account_id;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, Sale $sale)
    {
        return $user->account_id === $sale->account_id;
    }

    public function delete(User $user, Sale $sale)
    {
        return $user->account_id === $sale->account_id;
    }
}
