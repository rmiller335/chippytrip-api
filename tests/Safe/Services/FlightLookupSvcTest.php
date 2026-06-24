<?php

namespace Tests\Safe\Services;

use App\Services\FlightLookupSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

class FlightLookupSvcTest extends TestCase
{
    public function test_flight_returns_leg_details_when_present(): void
    {
        Http::fake([
            '*/TimeTable/*' => Http::response(
                '<Response><FlightDetails><FlightLegDetails><Airline>UA</Airline></FlightLegDetails></FlightDetails></Response>',
                200
            ),
        ]);

        $details = (new FlightLookupSvc())->flight('UA', '100', 'KSFO', 'KJFK', '20260701');

        $this->assertSame('UA', $details->Airline->value);
    }

    public function test_flight_returns_null_without_flight_details(): void
    {
        Http::fake([
            '*/TimeTable/*' => Http::response('<Response></Response>', 200),
        ]);

        $details = (new FlightLookupSvc())->flight('UA', '100', 'KSFO', 'KJFK', '20260701');

        $this->assertNull($details);
    }
}
