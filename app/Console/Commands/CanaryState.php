<?php

namespace App\Console\Commands;

use App\Models\Listener;
use App\Models\Watch;
use App\Models\WatchCallback;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dumps the canary listeners' watches, subscribers and callbacks as JSON for
 * the chippy-canary verifier. Read-only.
 *
 *   php artisan canary:state --email=canary-alice@example.net --email=...
 *
 * ADAPT: the lines marked ADAPT assume column/relation names; adjust them to
 * match the real models. The JSON shape at the bottom is the contract.
 */
class CanaryState extends Command
{
    protected $signature = 'canary:state
        {--email=* : Listener email addresses to include}
        {--days=10 : Only watches created in the last N days}';

    protected $description = 'JSON dump of canary listeners\' watches for the end-to-end canary';

    public function handle(): int
    {
        $emails = array_map('strtolower', $this->option('email'));
        $since = now()->subDays((int) $this->option('days'));

        $listeners = Listener::query()
            ->whereIn('email', $emails)                     // ADAPT: listener email column
            ->get()
            ->keyBy('id');

        $query = Watch::query()
            ->whereIn('listener_id', $listeners->keys())    // ADAPT: owner FK
            ->where('created_at', '>=', $since)
            ->with(['flight', 'subscribers']);              // ADAPT: relation names

        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive(Watch::class), true);
        if ($softDeletes) {
            $query->withTrashed();
        }

        $watches = $query->get();

        $callbacks = WatchCallback::query()
            ->whereIn('alert_id', $watches->pluck('subscription_id')->filter())   // ADAPT: callback → watch link
            ->orderBy('created_at')
            ->get()
            ->groupBy('alert_id');

        $out = $watches->map(function (Watch $w) use ($listeners, $callbacks, $softDeletes) {
            $flight = $w->flight;

            return [
                'id' => $w->id,
                'owner_email' => strtolower((string) optional($listeners->get($w->listener_id))->email),
                'ident' => $flight?->ident,                           // ADAPT: ICAO ident, e.g. DAL1234
                'ident_iata' => $flight?->ident_iata,                 // ADAPT: e.g. DL1234 (may be null)
                'flight_date' => optional($flight?->scheduled_out_local ?? $flight?->flight_date)  // ADAPT
                    ?->format('Y-m-d'),
                'subscription_id' => $w->subscription_id,
                'deleted' => $softDeletes ? $w->trashed() : false,
                'subscribers' => $w->subscribers                        // ADAPT: listeners other than the owner
                    ->pluck('email')->map(fn ($e) => strtolower($e))->values(),
                'callbacks' => ($callbacks->get($w->subscription_id) ?? collect())
                    ->map(fn (WatchCallback $c) => [
                        'notification_id' => $c->notification_id,
                        'event_code' => $c->event_code,             // ADAPT: e.g. filed/departure/arrived
                        'created_at' => $c->created_at->toIso8601String(),
                    ])->values(),
            ];
        })->values();

        $this->line(json_encode([
            'generated_at' => now()->toIso8601String(),
            'watches' => $out,
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
