<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class TicketNumberGenerator
{
    public function next(): string
    {
        $period = now()->format('Ym');
        DB::table('ticket_number_sequences')->insertOrIgnore(['period' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $sequence = DB::table('ticket_number_sequences')->where('period', $period)->lockForUpdate()->first();
        $next = ((int) $sequence->last_number) + 1;
        DB::table('ticket_number_sequences')->where('period', $period)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('TIC-%s-%06d', $period, $next);
    }
}
