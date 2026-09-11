<?php

namespace App\Services;

use App\Data\FlightConfirmationData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// =============================================================================
final class OpenAIFlightConfirmationExtractor
{
	/**
	 * @throws RequestException
	 */
	// =========================================================================
	public function extract(string $emailText): FlightConfirmationData
	{
		$response = Http::withToken(config('services.openai.key'))
			->acceptJson()
			->timeout(60)
			->retry(3, 1000)
			->post('https://api.openai.com/v1/responses', [
				'model' => config('services.openai.flight_model'),
				'instructions' => <<<'PROMPT'
					Extract the airline itinerary from this confirmation email.
					
					Rules:
					- Extract only information supported by the email.
					- Never invent a flight, airport, date, time, seat, carrier, or confirmation number.
					- Use null when a value cannot be determined.
					- Return airport IATA codes when the airport can be unambiguously identified.
					- Preserve departure and arrival times as local airport times.
					- Convert dates to YYYY-MM-DD.
					- Convert date/time values to YYYY-MM-DDTHH:MM:SS when both are known.
					- Do not convert times to UTC.
					- Return one flights[] element for each flight segment.
					
					- For flight_number, return only the numeric flight number portion,
					  without the airline IATA or ICAO designator.
					- Preserve leading zeroes in flight numbers.
					  Examples: "LH 413" -> "413", "AF 009" -> "009".
					
					- When a flight number includes an airline IATA designator, use that
					  designator to identify the marketing carrier.
					  Example: "UA 4176" means United Airlines, IATA "UA".
					
					- When the email explicitly identifies an operating carrier using
					  wording such as "Operated by", "operated by", "opéré par",
					  "durchgeführt von", or an equivalent expression in another language,
					  use that carrier as operating_carrier.
					
					- Marketing carrier and operating carrier may be different.
					
					- Treat brands such as United Express, Delta Connection, and
					  American Eagle as regional service brands. If the email also identifies
					  the actual operating airline, use the actual airline as operating_carrier.
					
					- For each airline, return its IATA and ICAO codes when known.
					  These codes may be supplied from known airline identity even when
					  the email contains only the airline name.
					- For each airport return the IATA code if known.
					- For each airport return the ICAO code if known.
					- Convert names to proper case when possible.
				PROMPT,

				'input' => [
					[
						'role' => 'user',
						'content' => [
							[
								'type' => 'input_text',
								'text' => $emailText,
							],
						],
					],
				],

				'text' => [
					'format' => [
						'type' => 'json_schema',
						'name' => 'flight_confirmation',
						'strict' => true,
						'schema' => $this->schema(),
					],
				],

				// There's no reason for this application to retain
				// the response server-side.
				'store' => false,
			])
			->throw()
			->json();

		$text = $this->extractOutputText($response);

		try {
			$data = json_decode(
				$text,
				true,
				flags: JSON_THROW_ON_ERROR
			);
		} catch (\JsonException $e) {
			throw new RuntimeException(
				'OpenAI returned invalid JSON.',
				previous: $e
			);
		}

		$data = FlightConfirmationData::fromArray($data);
		$data->resolveReferences();

		return $data;
	}

	// =========================================================================
	private function extractOutputText(array $response): string {
		foreach ($response['output'] ?? [] as $output) {
			if (($output['type'] ?? null) !== 'message') {
				continue;
			}

			foreach ($output['content'] ?? [] as $content) {
				if (($content['type'] ?? null) === 'output_text') {
					return $content['text'];
				}
			}
		}

		throw new RuntimeException(
			'OpenAI response contained no output text.'
		);
	}

	// =========================================================================
	private function schema(): array {
		return [
			'type' => 'object',

			'properties' => [
				'airline' => [
					'type' => ['string', 'null'],
				],

				'confirmation_number' => [
					'type' => ['string', 'null'],
				],

				'passengers' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'name' => [
								'type' => ['string', 'null'],
							],
						],
						'required' => ['name'],
						'additionalProperties' => false,
					],
				],

				'flights' => [
					'type' => 'array',

					'items' => [
						'type' => 'object',

						'properties' => [
							'date' => [
								'type' => ['string', 'null'],
							],

							'flight_number' => [
								'type' => ['string', 'null'],
								'description' =>
									'Flight number only, excluding the airline IATA or ICAO code. '
									. 'Preserve leading zeroes. Examples: "413", "009", "4812".',
							],

							'marketing_carrier' => [
								'type' => ['object', 'null'],
								'properties' => [
									'name' => [
										'type' => ['string', 'null'],
									],
									'iata' => [
										'type' => ['string', 'null'],
									],
									'icao' => [
										'type' => ['string', 'null'],
									],
								],
								'required' => [
									'name',
									'iata',
									'icao',
								],
								'additionalProperties' => false,
							],
	
							'operating_carrier' => [
								'type' => ['object', 'null'],
								'properties' => [
									'name' => [
										'type' => ['string', 'null'],
									],
									'iata' => [
										'type' => ['string', 'null'],
									],
									'icao' => [
										'type' => ['string', 'null'],
									],
								],
								'required' => [
									'name',
									'iata',
									'icao',
								],
								'additionalProperties' => false,
							],
	
							'departure_airport' => [
								'type' => 'object',
								'properties' => [
									'name' => [
										'type' => ['string', 'null'],
									],
									'iata' => [
										'type' => ['string', 'null'],
									],
									'icao' => [
										'type' => ['string', 'null'],
									],
								],
									'required' => [
									'name',
									'iata',
									'icao',
								],
								'additionalProperties' => false,
							],
	
							'arrival_airport' => [
								'type' => 'object',
								'properties' => [
									'name' => [
										'type' => ['string', 'null'],
									],
									'iata' => [
										'type' => ['string', 'null'],
									],
									'icao' => [
										'type' => ['string', 'null'],
									],
								],
								'required' => [
									'name',
									'iata',
									'icao',
								],
								'additionalProperties' => false,
							],
							'departure_local' => [
								'type' => ['string', 'null'],
							],

							'arrival_local' => [
								'type' => ['string', 'null'],
							],

							'cabin' => [
								'type' => ['string', 'null'],
							],

							'fare_class' => [
								'type' => ['string', 'null'],
							],

							'seat' => [
								'type' => ['string', 'null'],
							],
						],

						'required' => [
							'date',
							'flight_number',
							'marketing_carrier',
							'operating_carrier',
							'departure_airport',
							'arrival_airport',
							'departure_local',
							'arrival_local',
							'cabin',
							'fare_class',
							'seat',
						],

						'additionalProperties' => false,
					],
				],
			],

			'required' => [
				'airline',
				'confirmation_number',
				'passengers',
				'flights',
			],

			'additionalProperties' => false,
		];
	}
}
