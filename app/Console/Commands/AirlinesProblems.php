<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Services\AviationEdgeSvc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;


#[Signature('airlines:problems')]
#[Description('Look for problems in the Aviation Edge airlines DB')]

// =============================================================================
class AirlinesProblems extends Command {
	// =========================================================================
    public function handle(AviationEdgeSvc $ae) {
		$table = $ae->airlines();

		$tabbed = [];

		foreach($table as $entry) {
			if(0 < strlen($entry['type']) && 'active' == $entry['statusAirline']) {
				if(array_key_exists($entry['codeIcaoAirline'], $tabbed)) {
					$tabbed[$entry['codeIcaoAirline']][] = $entry;
				}
				else {
					$tabbed[$entry['codeIcaoAirline']] = [ $entry ];
				}
			}
		}

		foreach($tabbed as $key => $entry) {
			if(1 < count($entry)) {
				$this->info(json_encode($entry, JSON_PRETTY_PRINT));
			}
		}
    }
}
