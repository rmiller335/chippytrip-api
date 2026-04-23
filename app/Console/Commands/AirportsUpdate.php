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
    protected $signature = 'airports:update';
    protected $description = 'Update airports table from ourairports';

	protected $localFile;

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
		$ap = fopen($this->localFile, 'r');

		if($ap) {
			$recNo = 0;

			while($row = fgetcsv($ap, 2048)) {
				if(0 < $recNo++ && '' != $row[16]) {
					$airport = Airport::where('icao', $row[16])->first();

					$country = Country::where('iso2', $row[9])->first();

					if(null != $country && null != $airport) {
						Log::debug("Updating airport $row[3]");

						$updated = new Carbon($row[23]);

						if($updated->greaterThan($airport->updated_at)) {
							$airport->update([
								'iata' =>			$row[17],
								'name' =>			$row[3],
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

						$tz = $tzsvc->timezone($row[4], $row[5]);
						$row[6] = ('' == $row[6]) ? null : $row[6];

						$airport = Airport::create([
							'icao' =>			$row[16],
							'iata' =>			$row[17],
							'name' =>			$row[3],
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
