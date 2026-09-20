<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class FirstRunSetup
{
    public function required(): bool
    {
        return Schema::hasTable((new User)->getTable()) && ! User::query()->exists();
    }
}