<?php

namespace Tests\Safe\Jobs;

use App\Jobs\AddFlightDetails;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

class AddFlightDetailsTest extends TestCase
{
    public function test_handle_fills_details_from_flight_schedule(): void
    {
        $flight = $this->makeFlight(['departure_date' => Carbon::now()->addDays(10)->toDateString()]);

        Http::fake([
            '*/schedules/*' => Http::response([
                'scheduled' => [[
                    'ident_iata' => 'UA100',
                    'scheduled_out' => '2026-07-01T09:00:00Z',
                    'scheduled_in' => '2026-07-01T17:30:00Z',
                    'aircraft_type' => 'B738',
                    'meal_service' => 'snack',
                    'seats_cabin_first' => 8,
                    'seats_cabin_business' => 20,
                    'seats_cabin_coach' => 150,
                ]],
            ], 200),
        ]);

        (new AddFlightDetails($flight))->handle(app(FlightAwareSvc::class));

        $flight->refresh();
        $this->assertSame('B738', $flight->equipment);
        $this->assertSame('snack', $flight->meal_service);
        $this->assertSame(8, $flight->first_seats);
    }

    public function test_handle_falls_back_to_flight_info_when_departing_today(): void
    {
        $flight = $this->makeFlight(['departure_date' => Carbon::now()->toDateString()]);

        Http::fake([
            '*/schedules/*' => Http::response(['scheduled' => []], 200),
            '*/flights/UA100*' => Http::response([
                'flights' => [[
                    'ident_iata' => 'UA100',
                    'scheduled_out' => '2026-07-01T09:00:00Z',
                    'scheduled_in' => '2026-07-01T17:30:00Z',
                    'aircraft_type' => 'A320',
                    'meal_service' => null,
                    'seats_cabin_first' => 0,
                    'seats_cabin_business' => 12,
                    'seats_cabin_coach' => 138,
                ]],
            ], 200),
        ]);

        (new AddFlightDetails($flight))->handle(app(FlightAwareSvc::class));

        $flight->refresh();
        $this->assertSame('A320', $flight->equipment);
        $this->assertSame(138, $flight->coach_seats);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/flights/UA100'));
    }

    public function test_handle_fails_when_no_match_and_not_departing_today(): void
    {
        $flight = $this->makeFlight(['departure_date' => Carbon::now()->addDays(10)->toDateString()]);

        Http::fake([
            '*/schedules/*' => Http::response(['scheduled' => []], 200),
        ]);

        (new AddFlightDetails($flight))->handle(app(FlightAwareSvc::class));

        $flight->refresh();
        $this->assertNull($flight->equipment);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/flights/'));
    }
}
