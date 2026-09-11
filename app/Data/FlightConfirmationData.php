<?php

namespace App\Data;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Support\Facades\Validator;

// =============================================================================
final readonly class FlightConfirmationData {
	// =========================================================================
	public function __construct(
		public ?string $airline,
		public ?string $confirmationNumber,
		public array $passengers,
		public array $flights,
	) {}

	// =========================================================================
	public static function fromArray(array $data): self {
		array_walk_recursive($data, function (&$value) {
			if (is_string($value)) {
				$value = trim($value);
			}
		});

		$validated = Validator::make($data, [
			'airline' => [
				'nullable',
				'string',
				'max:100',
			],

			'confirmation_number' => [
				'nullable',
				'string',
				'max:20',
			],

			'passengers' => [
				'required',
				'array',
			],

			'passengers.*.name' => [
				'nullable',
				'string',
				'max:255',
			],

			'flights' => [
				'required',
				'array',
				'min:1',
			],

			'flights.*.date' => [
				'required',
				'date_format:Y-m-d',
			],

			'flights.*.flight_number' => [
				'nullable',
				'string',
				'max:12',
			],

			'flights.*.marketing_carrier' => [
				'required',
				'array',
			],

			'flights.*.marketing_carrier.name' => [
				'nullable',
				'string',
				'max:100',
			],

			'flights.*.marketing_carrier.iata' => [
				'nullable',
				'string',
				'size:2',
			],

			'flights.*.marketing_carrier.icao' => [
				'nullable',
				'string',
				'size:3',
			],

			'flights.*.operating_carrier' => [
				'nullable',
				'array',
			],

			'flights.*.operating_carrier.name' => [
				'nullable',
				'string',
				'max:100',
			],

			'flights.*.operating_carrier.iata' => [
				'nullable',
				'string',
				'size:2',
			],

			'flights.*.operating_carrier.icao' => [
				'nullable',
				'string',
				'size:3',
			],

			'flights.*.departure_airport' => [
				'required',
				'array',
			],

			'flights.*.departure_airport.name' => [
				'nullable',
				'string',
				'max:255',
			],

			'flights.*.departure_airport.iata' => [
				'nullable',
				'string',
				'size:3',
			],

			'flights.*.departure_airport.icao' => [
				'nullable',
				'string',
				'size:4',
			],

			'flights.*.arrival_airport' => [
				'required',
				'array',
			],
			
			'flights.*.arrival_airport.name' => [
				'nullable',
				'string',
				'max:255',
			],

			'flights.*.arrival_airport.iata' => [
				'nullable',
				'string',
				'size:3',
			],

			'flights.*.arrival_airport.icao' => [
				'nullable',
				'string',
				'size:4',
			],

			'flights.*.departure_local' => [
				'required',
				'date_format:Y-m-d\TH:i:s',
			],

			'flights.*.arrival_local' => [
				'required',
				'date_format:Y-m-d\TH:i:s',
			],

			'flights.*.cabin' => [
				'nullable',
				'string',
				'max:100',
			],

			'flights.*.fare_class' => [
				'nullable',
				'string',
				'max:10',
			],

			'flights.*.seat' => [
				'nullable',
				'string',
				'max:10',
			],
		])->validate();

		return new self(
			airline: $validated['airline'] ?? null,
			confirmationNumber: $validated['confirmation_number'] ?? null,
			passengers: $validated['passengers'],
			flights: $validated['flights'],
		);
	}

	// =========================================================================
	public function resolveReferences(
	): self {
		$flights = array_map(function (array $flight) {
			$marketingAirline = ! empty($flight['marketing_carrier'])
				? Airline::findBestMatch(
					$flight['marketing_carrier']['name'],
					$flight['marketing_carrier']['iata'],
					$flight['marketing_carrier']['icao']
				)
				: null;
	
			$operatingAirline = ! empty($flight['operating_carrier'])
				? Airline::findBestMatch(
					$flight['operating_carrier']['name'],
					$flight['operating_carrier']['iata'],
					$flight['operating_carrier']['icao']
				)
				: null;
	
			$departureAirport = ! empty($flight['departure_airport'])
				? Airport::findBestMatch(
					$flight['departure_airport']['name'],
					$flight['departure_airport']['iata'],
					$flight['departure_airport']['icao']
				)
				: null;
	
			$arrivalAirport = ! empty($flight['arrival_airport'])
				? Airport::findBestMatch(
					$flight['arrival_airport']['name'],
					$flight['arrival_airport']['iata'],
					$flight['arrival_airport']['icao']
				)
				: null;
	
			if ($marketingAirline) {
				$flight['marketing_carrier'] = [
					'name' => $marketingAirline->name,
					'iata' => $marketingAirline->iata,
					'icao' => $marketingAirline->icao,
				];
			}
	
			if ($operatingAirline) {
				$flight['operating_carrier'] = [
					'name' => $operatingAirline->name,
					'iata' => $operatingAirline->iata,
					'icao' => $operatingAirline->icao,
				];
			}
	
			if ($departureAirport) {
				$flight['departure_airport'] = [
					'name' => $departureAirport->name,
					'iata' => $departureAirport->iata,
					'icao' => $departureAirport->icao,
				];
			}
	
			if ($arrivalAirport) {
				$flight['arrival_airport'] = [
					'name' => $arrivalAirport->name,
					'iata' => $arrivalAirport->iata,
					'icao' => $arrivalAirport->icao,
				];
			}
	
			return $flight;
		}, $this->flights);

		return new self(
			airline: $this->airline,
			confirmationNumber: $this->confirmationNumber,
			passengers: $this->passengers,
			flights: $flights,
		);
	}

	// =========================================================================
	public function toArray(): array {
		return [
			'airline' => $this->airline,
			'confirmation_number' => $this->confirmationNumber,
			'passengers' => $this->passengers,
			'flights' => $this->flights,
		];
	}
}
