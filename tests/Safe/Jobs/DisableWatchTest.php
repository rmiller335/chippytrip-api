<?php

namespace Tests\Safe\Jobs;

use App\Jobs\DisableWatch;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

class DisableWatchTest extends TestCase
{
    public function test_handle_deletes_remote_watch_and_disables_locally(): void
    {
        $flight = $this->makeFlight();
        $watch = Watch::create([
            'flight_id' => $flight->id,
            'subscription_id' => 'SUB123',
            'secret' => 'secret123',
            'enabled' => true,
        ]);

        Http::fake([
            '*/alerts/SUB123' => Http::response(null, 204),
        ]);

        (new DisableWatch($watch))->handle(app(FlightAwareSvc::class));

        $watch->refresh();
        $this->assertFalse($watch->enabled);
        $this->assertNull($watch->subscription_id);
        $this->assertNull($watch->secret);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/alerts/SUB123'));
    }
}
