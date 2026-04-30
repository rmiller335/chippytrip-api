<?php

use App\Models\Airline;

// =============================================================================
function arrayToObject(array $array): stdClass {
	$object = new stdClass();

	foreach($array as $key => $value) {
		$object->$key = is_array($value) ? arrayToObject($value) : $value;
	}

	return $object;
}

// =============================================================================
function humanAirline(string $icao): string {
	$airline = Airline::where('icao', $icao);

	return $airline->call_sign;
}

// =============================================================================
function humanFlight(string $flight): string {
	$icao = substr($flight, 0, 3);
	$number = substr($flight, 3);

	return implode(' ', [ humanAirline($icao), $number ]);
}
