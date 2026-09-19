<?php

namespace App\Models;

use App\Enums\BusinessDayStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessDay extends Model
{
    protected $fillable = [
        'business_date',
        'status',
        'closed_at',
        'closed_by_user_id',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => BusinessDayStatus::class,
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(BusinessDayClosure::class);
    }
}
