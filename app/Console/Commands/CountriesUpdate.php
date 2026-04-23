<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('countries:update')]
#[Description('Update the countries table')]
class CountriesUpdate extends Command {
	// =========================================================================
	protected function getTable(): array {
		$path = storage_path('app/countries.html');

		$response = Http::withOptions([
			'debug' => false
		])
		->withHeaders([
			'User-Agent' =>	'Chippytrip / 1.0'
		])
		->sink($path)
		->get('https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes');

		if($response->successful()) {
			$html = file_get_contents($path);

			libxml_use_internal_errors(true);

			$dom = new \DOMDocument();
			$dom->loadHTML($html);

			$xpath = new \DOMXPath($dom);

			// Find the first wikitable (main country table)
			$table = $xpath->query('//table[contains(@class, "wikitable")]')->item(0);

			$data = [];

			if ($table) {
				$rows = $table->getElementsByTagName("tr");

				foreach ($rows as $i => $row) {
					// Skip header row
					if ($i === 0) continue;

					$cols = $row->getElementsByTagName("td");

					if ($cols->length >= 5) {
						$alpha2  =	trim($cols->item(2)->textContent);
						$alpha3  =	trim($cols->item(3)->textContent);
						$country =	trim($cols->item(0)->textContent);
						$dominion =	trim($cols->item(1)->textContent);

						$alpha2 = preg_replace('/^.*} */', '', $alpha2);
						$alpha3 = preg_replace('/^.*} */', '', $alpha3);
						$country = preg_replace('/^.*} */', '', $country);
						$dominion = preg_replace('/^.*} */', '', $dominion);

						$data[] = [
							'name' =>		$country,
							'iso2'  =>		$alpha2,
							'iso3'  =>		$alpha3,
							'dominion' =>	$dominion,
						];
					}
				}
			}

			return $data;
		}
		else {
			$this->error($response->body());
		}
	}

	// =========================================================================
    public function handle() {
		$table = $this->getTable();

		foreach($table as $entry) {
			$newRec = new Country($entry);

			$current = Country::where('iso2', $newRec->iso2)->first();

			if($current) {
				if(!$current->equal($newRec)) {
					Log::debug("Update $newRec->iso2");

					$current->update($newRec->toArray());
				}
			}
			else {
				Log::debug("Create $newRec->iso2");

				Country::create($newRec->toArray());
			}
		}
    }
}
