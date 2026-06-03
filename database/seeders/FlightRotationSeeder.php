<?php

namespace Database\Seeders;

use App\Jobs\AddFlightDetails;
use App\Jobs\EnableWatch;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\User;
use App\Services\FlightLookupSvc;
use Carbon\Carbon;
use Faker\Factory;
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

		$user = User::where('email', 'rmiller@villamilla.com')->first();
		$faker = Factory::create();

		for($date = $startDate ; $date->lessThan($endDate) ; $date->addDay()) {
			$flights = $this->flights->get($date->dayName);

			foreach($flights as $flight) {
				$flightRec = Flight::where('flight', $flight->get('flight'))
					->where('origin_icao', $flight->get('from'))
					->where('destination_icao', $flight->get('to'))
					->where('departure_date', $date)
					->first()
				;

				if(null == $flightRec) {
					$flightRec = Flight::create([
						'airline_icao' =>		$flight->get('icao'),
						'flight_no' =>			substr($flight->get('flight'), 2),
						'flight' =>				$flight->get('flight'),
						'origin_icao' =>		$flight->get('from'),
						'destination_icao' =>	$flight->get('to'),
						'departure_date' =>		$date,
					]);

					AddFlightDetails::dispatch($flightRec);

					$watchRec = $flightRec->watch;

					if(null == $watchRec) {
						$watchRec = $flightRec->watch()->create([
							'enabled' =>	false,
						]);
					}

					if(0 == $watchRec->listeners->count()) {
						$travelers = implode(' and ' , [
							$faker->firstName,
							$faker->firstName
						]);

						$watchRec->listeners()->create([
							'user_id' =>	$user->id,
							'travelers' =>	$travelers,
						]);
					}

					if($watchRec->watchable()) {
						EnableWatch::dispatch($watchRec);
					}
				}
			}
		}
	}
}
