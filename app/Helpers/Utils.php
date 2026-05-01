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

// =============================================================================
function xmlToObject(string $xmlString): stdClass {
    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($xml === false) {
        throw new InvalidArgumentException('Invalid XML string provided.');
    }

    return parseXmlNode($xml);
}

// =============================================================================
function parseXmlNode(SimpleXMLElement $xml): stdClass {
    $obj = new stdClass();

    // Attributes
    foreach ($xml->attributes() as $key => $value) {
        if (!isset($obj->attributes)) {
            $obj->attributes = new stdClass();
        }
        $obj->attributes->$key = (string) $value;
    }

    // Child elements
    foreach ($xml->children() as $childName => $child) {
        $childObj = parseXmlNode($child);

        if (isset($obj->$childName)) {
            if (!is_array($obj->$childName)) {
                $obj->$childName = [$obj->$childName];
            }
            $obj->$childName[] = $childObj;
        } else {
            $obj->$childName = $childObj;
        }
    }

    // Text content
    $text = trim((string) $xml);
    if ($text !== '' && !isset($obj->attributes) && count((array) $obj) === 0) {
        $obj->value = $text;
    }

    return $obj;
}
