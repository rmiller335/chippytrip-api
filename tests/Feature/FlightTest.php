<?php

namespace Tests\Feature;

use App\Services\AviationEdgeSvc;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

// =============================================================================
class FlightTest extends TestCase {
	protected $verbose = true;

	// =========================================================================
    public function test_schedule(): void {
		$ae = new AviationEdgeSvc();

		$flight = $ae->flight('DL184');

		if($this->verbose) {
			fwrite(STDERR, json_encode($flight, JSON_PRETTY_PRINT) . "\n");
		}

        $this->assertEquals($flight['airline']['iataCode'], 'DL');

		$iata = $flight['departure']['iataCode'];
		$airlineIcao = $flight['airline']['icaoCode'];
		$flightNum = $flight['flight']['number'];
		$date = Carbon::today()->addDays(60)->format('Y-m-d');

		if($this->verbose) {
			fwrite(STDERR, "iata =			$iata\n");
			fwrite(STDERR, "airlineIcao =	$airlineIcao\n");
			fwrite(STDERR, "flightNum =		$flightNum\n");
			fwrite(STDERR, "date =			$date\n");
		}

		$future = $ae->futureFlight($iata, $date, $airlineIcao, $flightNum);

		if($this->verbose) {
			fwrite(STDERR, json_encode($future, JSON_PRETTY_PRINT) . "\n");
		}
    }
}
