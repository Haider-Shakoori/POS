<?php

namespace App\Services\Closing;

use App\Enums\BusinessDayStatus;
use App\Models\BusinessDay;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class BusinessDayService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function dateString(CarbonInterface|string|null $value = null): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }

    public function lock(CarbonInterface|string|null $value = null): BusinessDay
    {
        $date = $this->dateString($value);

        DB::table('business_days')->insertOrIgnore([
            'business_date' => $date,
            'status' => BusinessDayStatus::Open->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return BusinessDay::query()
            ->whereDate('business_date', $date)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function lockOpen(CarbonInterface|string|null $value = null): BusinessDay
    {
        $day = $this->lock($value);

        if ($day->status !== BusinessDayStatus::Open) {
            throw new DomainException(
                'The business day '.$day->business_date->format('Y-m-d').' is closed. Reopen it before posting new transactions.'
            );
        }

        return $day;
    }

    public function reopen(string $date, string $reason, User $actor): BusinessDay
    {
        if (! $actor->hasPermission('business_days.reopen')) {
            throw new DomainException('The user is not allowed to reopen business days.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A reason is required to reopen a business day.');
        }

        return DB::transaction(function () use ($date, $reason, $actor): BusinessDay {
            $day = $this->lock($date);

            if ($day->status === BusinessDayStatus::Open) {
                return $day;
            }

            $old = [
                'status' => $day->status->value,
                'closed_at' => $day->closed_at?->toDateTimeString(),
                'closed_by_user_id' => $day->closed_by_user_id,
            ];

            $day->forceFill([
                'status' => BusinessDayStatus::Open,
                'reopened_at' => now(),
                'reopened_by_user_id' => $actor->id,
                'reopen_reason' => $reason,
            ])->save();

            $this->audit->record(
                'closing.business_day.reopened',
                model: $day,
                oldValues: $old,
                newValues: [
                    'status' => BusinessDayStatus::Open->value,
                    'reopen_reason' => $reason,
                ],
                actor: $actor,
            );

            return $day->fresh();
        });
    }
}
