<?php

namespace App\Jobs;

use App\Data\FlightConfirmationData;
use App\Jobs\AddFlightDetails;
use App\Services\FlightAwareSvc;
use App\Models\Flight;
use App\Models\FlightListener;
use App\Models\InboundEmail;
use App\Services\OpenAIFlightConfirmationExtractor;
use Carbon\Carbon;
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
		FlightAwareSvc $fa
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
					$email,
					$confirmation,
					$flight,
					$fa
				);

				if(null != $flightRec) {
					$this->addListener(
						$email,
						$confirmation,
						$flightRec
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
		InboundEmail $email,
		FlightConfirmationData $confirmation,
		array $flight,
		FlightAwareSvc $fa
	): ?Flight {
		$flightNo = $flight['marketing_carrier']['iata'] . $flight['flight_number'];

		$flightRec = Flight::where('flight', $flightNo)
			->where('origin_icao', $flight['departure_airport']['icao'])
			->where('destination_icao', $flight['arrival_airport']['icao'])
			->where('departure_date', $flight['date'])
			->first()
		;

		if(null == $flightRec) {
			$flightRec = Flight::make([
				'airline_icao' =>		Flight::icaoFromFlightNum($flightNo),
				'departure_date' =>		$flight['date'],
				'departure_dt' =>		$flight['departure_local'],
				'destination_icao' =>	$flight['arrival_airport']['icao'],
				'flight_no' =>			$flight['flight_number'],
				'flight' =>				$flightNo,
				'origin_icao' =>		$flight['departure_airport']['icao'],
			]);

			$info = $fa->flightSchedule($flightRec);

			Log::debug("FlightAwareSvc::flightSchedule() returned:");
			Log::debug(json_encode($info, JSON_PRETTY_PRINT));

			if(null != $info) {
				$flightRec->departure_dt =		new Carbon($info->scheduled_out);
				$flightRec->arrival_dt =		new Carbon($info->scheduled_in);
				$flightRec->equipment =			$info->aircraft_type;
				$flightRec->meal_service =		$info->meal_service;
				$flightRec->first_seats =		$info->seats_cabin_first;
				$flightRec->business_seats =	$info->seats_cabin_business;
				$flightRec->coach_seats =		$info->seats_cabin_coach;

				$flightRec->save();

				return $flightRec;
			}
		}

		return null;
	}

	// =========================================================================
	private function addListener(
		InboundEmail $email,
		FlightConfirmationData $confirmation,
		Flight $flightRec
	): void {
		$watchRec = $flightRec->watch;

		if(null == $watchRec) {
			$watchRec = $flightRec->watch()->create([
				'enabled' =>	false,
			]);
		}

		if(0 == $watchRec->listeners->count()) {
			$names = collect($confirmation->passengers)
				->pluck('name')
				->filter()
				->implode(', ')
			;

			$watchRec->listeners()->updateOrCreate([
					'user_id' =>	$email->user->id,
				], [
					'travelers' =>	$names,
				]
			);
		}

		if($watchRec->watchable()) {
			EnableWatch::dispatch($watchRec);
		}
	}
}
