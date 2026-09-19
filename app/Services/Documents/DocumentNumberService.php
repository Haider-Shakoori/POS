<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $type, string $prefix): string
    {
        $date = now()->format('Ymd');
        $key = $type.':'.$date;

        DB::table('document_sequences')->insertOrIgnore([
            'key' => $key,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_sequences')
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        $value = (int) $sequence->next_value;

        DB::table('document_sequences')
            ->where('key', $key)
            ->update([
                'next_value' => $value + 1,
                'updated_at' => now(),
            ]);

        return sprintf('%s-%s-%05d', $prefix, $date, $value);
    }
}
