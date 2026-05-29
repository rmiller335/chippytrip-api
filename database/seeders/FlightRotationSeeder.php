<?php

namespace Database\Seeders;

use App\Jobs\AddFlightDetails;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\FlightLookupSvc;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

// =============================================================================
class FlightRotationSeeder extends Seeder {
	protected Collection		$flights;
	protected FlightLookupSvc	$fl;

	// =========================================================================
	protected function readFlights() {
		$path = base_path('misc/Test Flight Rotation.ods');
		$ods = IOFactory::load($path);
		$sheet = $ods->getActiveSheet();

		$flights = new Collection;

		foreach($sheet->getRowIterator() as $rowNo => $row) {
			$rowData = new Collection();

			if(1 < $rowNo) {
				$rowData = new Collection;

				$ci = $row->getCellIterator();
				$ci->setIterateOnlyExistingCells(false);

				foreach($ci as $cell) {
					$colNo = $cell->getColumn();

					Log::debug("$colNo: " . $cell->getValue());

					switch($colNo) {
						// Day of week
						case 'A':	$day = $cell->getValue();
									break;
						// Flight #
						case 'B':
						case 'F':	$flight = $cell->getValue();
									break;
						// Airline ICAO
						case 'C':
						case 'G':	$icao = $cell->getValue();
									break;
						// Origin
						case 'D':
						case 'H':	$from = $cell->getValue();
									break;
						// Destination
						case 'E':	
						case 'I':	$rowData->push(collect([
										'flight' =>		$flight,
										'icao' =>		$icao,
										'from_iata' =>	$from,
										'to_iata' =>	$cell->getValue(),
										'from' =>		Airport::icaoForIata($from),
										'to' =>			Airport::icaoForIata($cell->getValue()),
									]));
									break;
					}
				}

				$flights->put($day, $rowData);
			}
		}

		Log::debug("Flights = ...");
		Log::debug(json_encode($flights, JSON_PRETTY_PRINT));

		return $flights;
	}

	// =========================================================================
    public function run(FLightLookupSvc $fl): void {
		$this->fl = $fl;

		$this->flights = $this->readFlights();
		$this->update();
    }

	// =========================================================================
	protected function update(): void {
		$startDate = Carbon::now()->startOfDay();
		$endDate = $startDate->copy()->addDays(7);

		for($date = $startDate ; $date->lessThan($endDate) ; $date->addDay()) {
			$flights = $this->flights->get($date->dayName);
//			Log::debug(json_encode($flights, JSON_PRETTY_PRINT));

			foreach($flights as $flight) {
				$flightRec = Flight::where('flight', $flight->get('flight'))
					->where('origin_icao', $flight->get('from'))
					->where('destination_icao', $flight->get('to'))
					->where('departure_date', $date)
					->first()
				;

				if(null == $flightRec) {
					Log::debug("Adding flight ...");
					Log::debug(json_encode([
						'airline_icao' =>		$flight->get('icao'),
						'flight_no' =>			substr($flight->get('flight'), 2),
						'flight' =>				$flight->get('flight'),
						'origin_icao' =>		$flight->get('from'),
						'destination_icao' =>	$flight->get('to'),
						'departure_date' =>		$date,
					], JSON_PRETTY_PRINT));

					$flightRec = Flight::create([
						'airline_icao' =>		$flight->get('icao'),
						'flight_no' =>			substr($flight->get('flight'), 2),
						'flight' =>				$flight->get('flight'),
						'origin_icao' =>		$flight->get('from'),
						'destination_icao' =>	$flight->get('to'),
						'departure_date' =>		$date,
					]);

					AddFlightDetails::dispatch($flightRec);
				}
			}
		}
	}
}
