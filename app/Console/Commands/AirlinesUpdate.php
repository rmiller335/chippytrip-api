<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Services\AviationEdgeSvc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

// =============================================================================
class AirlinesUpdate extends Command {
    protected $signature = 'airlines:update';
    protected $description = 'Update the airlines table from Aviation Edge';

	// =========================================================================
    public function handle(AviationEdgeSvc $ae) {
		$table = $ae->airlines();

		foreach($table as $entry) {
			if(
				    $entry['codeIcaoAirline']
					&& $entry['codeIataAirline']
					&& 'active' == $entry['statusAirline']
					&& 'Scoot Private Limited' != $entry['nameAirline']
			) {
				$this->update($entry);
			}
		}
    }

	// =========================================================================
	public function update(array $entry) {
		$newAirline = new Airline([
			'icao' =>			$entry['codeIcaoAirline'],
			'call_sign' =>		$entry['callsign'],
			'country_code' =>	$entry['codeIso2Country'],
			'iata' =>			$entry['codeIataAirline'],
			'name' =>			$entry['nameAirline'],
			'status' =>			$entry['statusAirline'],
			'types' =>			explode(',', $entry['type']),
		]);

		if('DL' == $newAirline->iata) {
			Log::debug(json_encode($newAirline, JSON_PRETTY_PRINT));
			if($newAirline->hasType('scheduled')) {
				Log::debug('has type scheduled');
			}
			else {
				Log::debug("doesn't have type scheduled");
			}
		}

		if('UK' == $newAirline->country_code) {
			$newAirline->country_code = 'GB';
		}

		if('GIL' == $newAirline->icao) {
			$newAirline->country_code = 'GE';
		}

		if(
		  	   $newAirline->hasType('scheduled')
			|| $newAirline->hasType('leisure')
		) {

			$airline = Airline::where('icao', $newAirline->icao)->first();

			if($airline) {
				if(!$airline->equal($newAirline)) {
					$airline->update($newAirline->toArray());
				}
			}
			else {
				$airline = Airline::create($newAirline->toArray());
			}
		}
	}
}
