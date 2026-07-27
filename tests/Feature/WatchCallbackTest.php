<?php

namespace Tests\Feature;

use App\Models\Watch;
use App\Models\FlightAwareCallback;
use App\Http\Controllers\WatchCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WatchCallbackTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a FlightAware-style alert payload for the given Watch.
     * Mirrors the structure expected by FlightAwareCallback::fromApiPayload().
     */
    protected function buildPayload(Watch $watch, array $overrides = []): array
    {
        $flight      = $watch->flight;
        $depDate     = $flight->departure_date->copy()->startOfDay();
        $scheduledOut = $depDate->copy()->setTime(14, 0, 0)->toIso8601ZuluString();

        return array_merge([
            'alert_id'          => $watch->subscription_id,
            'event_code'        => 'departure',
            'summary'           => "{$flight->ident} departed",
            'short_description' => 'Flight has departed',
            'long_description'  => "Flight {$flight->ident} has departed the origin airport.",
            'flight'            => [
                'fa_flight_id'    => 'TEST-' . strtoupper(uniqid()),
                'ident'           => $flight->flight,
                'ident_icao'      => $flight->flight,
                'ident_iata'      => $flight->flight,
                'scheduled_out'   => $scheduledOut,
                'estimated_out'   => $scheduledOut,
                'actual_out'      => $scheduledOut,
                'scheduled_off'   => $scheduledOut,
                'estimated_off'   => $scheduledOut,
                'actual_off'      => null,
                'scheduled_on'    => null,
                'estimated_on'    => null,
                'actual_on'       => null,
                'scheduled_in'    => null,
                'estimated_in'    => null,
                'actual_in'       => null,
                'origin'          => [
                    'code'      => $flight->origin->icao      ?? 'KJFK',
                    'code_icao' => $flight->origin->icao      ?? 'KJFK',
                    'code_iata' => $flight->origin->iata      ?? 'JFK',
                    'name'      => $flight->origin->name,
                    'city'      => $flight->origin->city,
                ],
                'destination'     => [
                    'code'      => $flight->destination->icao ?? 'KLAX',
                    'code_icao' => $flight->destination->icao ?? 'KLAX',
                    'code_iata' => $flight->destination->iata ?? 'LAX',
                    'name'      => $flight->destination->name,
                    'city'      => $flight->destination->city,
                ],
                'aircraft_type'   => 'B738',
                'operator'        => 'TEST',
                'operator_icao'   => 'TST',
                'operator_iata'   => 'TS',
                'flight_number'   => '1234',
                'cancelled'       => false,
                'diverted'        => false,
                'blocked'         => false,
                'position_only'   => false,
            ],
        ], $overrides);
    }

    /**
     * Build a POST Request object as FlightAware would send it.
     */
    protected function buildRequest(Watch $watch, array $payloadOverrides = []): Request
    {
        $payload = $this->buildPayload($watch, $payloadOverrides);

        $request = Request::create(
            '/webhook/callback?s=' . $watch->secret,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * A valid callback saves the FlightAwareCallback record and returns 200.
     */
    public function test_valid_callback_saves_and_returns_200(): void
    {
        Bus::fake();

        $watch = Watch::with('flight')->firstOrFail();

        $request  = $this->buildRequest($watch);
        $response = app(WatchCallback::class)->callback($request);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('watch_callbacks', [
            'alert_id' => $watch->subscription_id,
        ]);
    }

    /**
     * A wrong secret returns 403 and does not save anything.
     */
public function test_invalid_secret_returns_403(): void
{
    Bus::fake();

    $watch = Watch::with('flight')->firstOrFail();

    $before = \App\Models\WatchCallback::where('alert_id', $watch->subscription_id)->count();

        $request  = $this->buildRequest($watch);
        // Tamper with the secret
        $request = Request::create(
            '/webhook/callback?s=WRONG_SECRET',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->buildPayload($watch))
        );
        $request->headers->set('Content-Type', 'application/json');

    $response = app(WatchCallback::class)->callback($request);

    $this->assertEquals(403, $response->getStatusCode());

    $after = \App\Models\WatchCallback::where('alert_id', $watch->subscription_id)->count();
    $this->assertEquals($before, $after);  // count unchanged
}

    /**
     * A callback with an unknown subscription_id returns 403.
     */
    public function test_unknown_subscription_id_returns_403(): void
    {
        Bus::fake();

        $watch = Watch::with('flight')->firstOrFail();

        $payload = $this->buildPayload($watch, ['alert_id' => 'NONEXISTENT-ID']);

        $request = Request::create(
            '/webhook/callback?s=' . $watch->secret,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $request->headers->set('Content-Type', 'application/json');

        $response = app(WatchCallback::class)->callback($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * A callback whose departure date doesn't match the watch's flight date
     * saves the record but does NOT dispatch notifications.
     */
    public function test_date_mismatch_saves_but_skips_notifications(): void
    {
        Bus::fake();

        $watch = Watch::with('flight')->firstOrFail();

        // Push scheduled_out one week into the future so the date won't match
        $wrongDate = $watch->flight->departure_date->copy()->addWeek()->toIso8601ZuluString();

        $request  = $this->buildRequest($watch, [
            'flight' => array_merge(
                $this->buildPayload($watch)['flight'],
                [
                    'scheduled_out' => $wrongDate,
                    'estimated_out' => $wrongDate,
                    'actual_out'    => $wrongDate,
                ]
            ),
        ]);

        $response = app(WatchCallback::class)->callback($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Record should still be saved
        $this->assertDatabaseHas('watch_callbacks', [
            'alert_id' => $watch->subscription_id,
        ]);

        // But no jobs should have been dispatched
        Bus::assertNothingDispatched();
    }

    /**
     * A valid callback with matching date dispatches notification jobs.
     */
    public function test_matching_date_dispatches_notifications(): void
    {
        Bus::fake();

        $watch = Watch::with(['flight', 'listeners'])->firstOrFail();

        $request  = $this->buildRequest($watch);
        $response = app(WatchCallback::class)->callback($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Expect one chained dispatch per listener
        Bus::assertChained([
            \App\Jobs\SendNotification::class,
            \App\Jobs\NotificationsIndex::class,
        ]);
    }

    // =========================================================================
    // Manual / quick-fire helper (not a PHPUnit test)
    // =========================================================================

    /**
     * Call this from tinker to fire a real callback through the controller:
     *
     *   (new \Tests\Feature\WatchCallbackTest)->fireFromTinker();
     */
    public function fireFromTinker(?int $watchId = null): void
    {
        $watch = $watchId
            ? Watch::with('flight')->findOrFail($watchId)
            : Watch::with('flight')->firstOrFail();

        $request  = $this->buildRequest($watch);
        $response = app(WatchCallback::class)->callback($request);

        echo "Status: " . $response->getStatusCode() . PHP_EOL;
        echo "Payload alert_id: " . $watch->subscription_id . PHP_EOL;
        echo "Watch secret: " . $watch->secret . PHP_EOL;
        echo "Departure date: " . $watch->flight->departure_date . PHP_EOL;
    }
}
