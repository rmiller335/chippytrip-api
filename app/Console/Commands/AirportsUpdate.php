<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Country;
use App\Services\TimezoneSvc;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// =============================================================================
class AirportsUpdate extends Command {
	protected $signature = 'airports:update {--force-update}';
	protected $description = 'Update airports table from ourairports';

	protected $localFile;
	protected $multiAirportNames = [];

	// =========================================================================
	protected function download() {
		$url = 'https://ourairports.com/airports.csv';
		$ap = $this->openUrl($url);

		if(null == $ap) {
			$this->error("Can't open " . $url);
			exit(-1);
		}

		$this->localFile = storage_path('/app/airports.csv');
		$stream = fopen($this->localFile, 'w');

		while($buffer = fread($ap, 8096)) {
			fwrite($stream, $buffer);
		}

		fclose($ap);
		fclose($stream);
	}

	// =========================================================================
	public function handle(TimezoneSvc $tzsvc) {
		$this->download();
		$this->loadMultiAirportNames();

		$ap = fopen($this->localFile, 'r');

		$force = $this->option('force-update');

		if($ap) {
			$recNo = 0;

			while($row = fgetcsv($ap, 2048)) {
				if(0 < $recNo++ && '' != $row[16]) {
					$airport = Airport::where('icao', $row[16])->first();
					$country = Country::where('iso2', $row[9])->first();
					$iata = $row[17];

					// If the airport is in the multi-airport list, use that name,
					// otherwise use the city name if it exists, otherwise use the airport name.
					$displayName =
						$this->multiAirportNames[$iata]
							?? (strlen(trim($row[13])) ? $row[13] : $row[3]);

					if(null != $country && null != $airport) {
						$updated = new Carbon($row[23]);

						if($force || $updated->greaterThan($airport->updated_at)) {
							Log::debug("Updating airport $row[3]");

							$airport->update([
								'iata' =>			$row[17],
								'name' =>			$row[3],
								'display_name' =>	$displayName,
								'city' =>			$row[13],
								'state' =>			$row[12],
								'longitude' =>		$row[5],
								'latitude' =>		$row[4],
								'country_code' =>	$row[9],
								'wiki_url' =>		$row[20],
							]);
						}
					}
					elseif(null != $country) {
						Log::debug("Adding airport $row[3]");

						$tz = $tzsvc->timezone((float) $row[4], (float) $row[5]);
						$row[6] = ('' == $row[6]) ? null : $row[6];

						$airport = Airport::create([
							'icao' =>			$row[16],
							'iata' =>			$row[17],
							'name' =>			$row[3],
							'display_name' =>	$displayName,
							'elevation' =>		(0 == strlen($row[6])) ? null : $row[6],
							'city' =>			$row[13],
							'state' =>			$row[12],
							'longitude' =>		$row[5],
							'latitude' =>		$row[4],
							'timezone' =>		$tz,
							'country_code' =>	$row[9],
						]);
					}
				}

			}
		}
		else {
			$this->error("Can't open " . $this->localFile);
			exit(-1);
		}

		fclose($ap);
	}

	// =========================================================================
	protected function loadMultiAirportNames(): void {
		$url = 'https://raw.githubusercontent.com/mborsetti/airportsdata/main/airportsdata/iata_macs.csv';

		$stream = $this->openUrl($url);

		if(null == $stream) {
			$this->error("Can't open " . $url);
			exit(-1);
		}

		$headers = fgetcsv($stream);

		while($row = fgetcsv($stream)) {
			$data = array_combine($headers, $row);

			if(!empty($data['Airport Code']) && !empty($data['Airport Name'])) {
				$this->multiAirportNames[
					strtoupper($data['Airport Code'])
				] = $data['Airport Name'];
			}
		}

		fclose($stream);
	}

	// =========================================================================
	protected function openUrl($url) {
		ob_start();

		$opts = [
			"ssl" => [
				"verify_peer" => false,
				"verify_peer_name" => false,
			],
		];

		return fopen($url, 'rb', false, stream_context_create($opts));
	}
}
