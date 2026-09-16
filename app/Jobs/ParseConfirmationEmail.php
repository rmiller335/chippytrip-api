<?php

namespace App\Jobs;

use App\Data\FlightConfirmationData;
use App\Services\FlightWatchSvc;
use App\Models\Flight;
use App\Models\InboundEmail;
use App\Services\OpenAIFlightConfirmationExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

// =============================================================================
class ParseConfirmationEmail implements ShouldQueue {
	use Queueable;

	// =========================================================================
	public function __construct(
		public int $inboundEmailId
	) {
	}

	// =========================================================================
	public function handle(
		OpenAIFlightConfirmationExtractor $extractor,
		FlightWatchSvc $flightWatchSvc
	): void {
		$email = InboundEmail::with('user')
			->findOrFail($this->inboundEmailId);

		$email->update([
			'status' => 'processing',
			'error' => null,
		]);

		try {
			$confirmation = $this->parseEmail(
				$email,
				$extractor
			);

			foreach ($confirmation->flights as $flight) {
				$flightRec = $this->addFlight(
					$flight,
					$flightWatchSvc
				);

				if(null != $flightRec) {
					$flightWatchSvc->addListener(
						$flightRec,
						$email->user,
						$this->travelerNames($confirmation)
					);

					Log::debug("Adding flight {$flightRec->flight} to email {$email->id} related records.");

					$email->emailRelatedRecords()->create([
						'record_type' =>	Flight::class,
						'record_id' =>		$flightRec->id,
					]);

					$email->update([
						'status' => 'processed',
					]);
				}
				else {
					$flightNo = $flight['marketing_carrier']['iata'] . $flight['flight_number'];

					$error = join(' ', [
						"Invalid flight:",
							$flight['marketing_carrier']['iata'] . $flight['flight_number'],
						"from", $flight['departure_airport']['icao'],
						"to", $flight['arrival_airport']['icao'],
						"on", $flight['date']
					]);

					$email->errors()->create([
						'code' =>		'invalid_flight',
						'message' =>	$error,
						'context' =>	$flight,
					]);
				}
			}
		} catch (Throwable $e) {
			$email->update([
				'status' => 'failed',
				'error' => $e->getMessage(),
			]);

			throw $e;
		}
	}

	// =========================================================================
	private function parseEmail(
		InboundEmail $email,
		OpenAIFlightConfirmationExtractor $extractor
	): FlightConfirmationData {
		$body = $email->text_body;

		if (!$body && $email->html_body) {
			$body = trim(
				html_entity_decode(
					strip_tags($email->html_body),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				)
			);
		}

		if (!$body) {
			throw new \RuntimeException(
				'Inbound email contains no usable body.'
			);
		}

		$emailText = implode("\n\n", array_filter([
			$email->subject
				? "Subject: {$email->subject}"
				: null,
			$body,
		]));

		$confirmation = $extractor->extract($emailText);

		Log::debug('Flight confirmation extracted:');
		Log::debug(
			json_encode(
				$confirmation->toArray(),
				JSON_PRETTY_PRINT
			)
		);

		return $confirmation;
	}

	// =========================================================================
	private function addFlight(
		array $flight,
		FlightWatchSvc $flightWatchSvc
	): ?Flight {
		$flightNo = $flight['marketing_carrier']['iata'] . $flight['flight_number'];

		return $flightWatchSvc->findOrCreateFlight(
			$flightNo,
			$flight['departure_airport']['icao'],
			$flight['arrival_airport']['icao'],
			$flight['date'],
			$flight['departure_local']
		);
	}

	// =========================================================================
	private function travelerNames(FlightConfirmationData $confirmation): string {
		return collect($confirmation->passengers)
			->pluck('name')
			->filter()
			->implode(', ')
		;
	}
}
