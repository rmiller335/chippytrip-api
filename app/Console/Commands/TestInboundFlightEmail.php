<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// =============================================================================
class TestInboundFlightEmail extends Command {
	protected $signature = 'flight:test-email
		{file : Path to the .eml file}
		{--from= : ChippyTrip user email address}
		{--url= : Postmark callback URL}';

	protected $description =
		'Modify a saved flight confirmation to future dates and POST it as a Postmark inbound webhook';

	// =========================================================================
	public function handle(): int {
		$file = $this->resolveFile(
			$this->argument('file')
		);

		if (! $file) {
			$this->error(
				'File not found: ' . $this->argument('file')
			);

			return self::FAILURE;
		}

		$from = $this->option('from');

		if (! $from) {
			$this->error('--from is required');

			return self::FAILURE;
		}

		$url = config('postmark.inbound_url');
		$raw = file_get_contents($file);

		if ($raw === false) {
			$this->error("Unable to read: {$file}");

			return self::FAILURE;
		}

		try {
			[$raw, $departureDate, $returnDate] =
				$this->replaceItineraryDates($raw);

			[$textBody, $htmlBody] =
				$this->extractBodies($raw);

			$subject = $this->extractHeader($raw, 'Subject')
				?? 'Flight confirmation';

			$messageId = sprintf(
				'chippytrip-test-%s@chippytrip.com',
				bin2hex(random_bytes(12))
			);

			$payload = [
				'From' => $from,
				'FromName' => 'ChippyTrip Test User',

				'FromFull' => [
					'Email' => $from,
					'Name' => 'ChippyTrip Test User',
					'MailboxHash' => '',
				],

				'To' => 'plans@chippytrip.com',

				'ToFull' => [
					[
						'Email' => 'plans@chippytrip.com',
						'Name' => '',
						'MailboxHash' => '',
					],
				],

				'Cc' => '',
				'CcFull' => [],
				'Bcc' => '',
				'BccFull' => [],

				'Subject' => 'Fwd: ' . $subject,

				'MessageID' => $messageId,

				'TextBody' => $textBody,
				'HtmlBody' => $htmlBody,
				'StrippedTextReply' => '',

				'Tag' => '',

				'Headers' => [],

				'Attachments' => [],
			];

			$this->info(sprintf(
				'Departure: %s',
				$departureDate->format('Y-m-d')
			));

			$this->info(sprintf(
				'Return:	%s',
				$returnDate->format('Y-m-d')
			));

			$this->info("Posting to: {$url}");
			$this->info("From:		 {$from}");

			$response = Http::asJson()
				->withBasicAuth(
					(string) config('postmark.inbound_user'),
					(string) config('postmark.inbound_password')
				)
				->timeout(30)
				->post($url, $payload);

			if (! $response->successful()) {
				$this->error(
					"Callback returned HTTP {$response->status()}"
				);

				$this->line($response->body());

				return self::FAILURE;
			}

			$this->info(
				"Callback returned HTTP {$response->status()}"
			);

			if ($response->body()) {
				$this->line($response->body());
			}

			return self::SUCCESS;
		} catch (\Throwable $e) {
			$this->error($e->getMessage());

			return self::FAILURE;
		}
	}

	// =========================================================================
	private function replaceItineraryDates(
		string $raw
	): array {
		/*
		 * Find the first two distinct itinerary date headings, e.g.
		 *
		 *	   Wed, 02SEP
		 *	   Sun, 06SEP
		 *
		 * They appear once in text/plain and again in text/html.
		 */
		preg_match_all(
			'/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),\s+\d{2}[A-Z]{3}\b/',
			$raw,
			$matches
		);

		$dateTokens = array_values(
			array_unique($matches[0] ?? [])
		);

		if (count($dateTokens) < 2) {
			throw new RuntimeException(
				'Could not identify the original outbound and return dates.'
			);
		}

		/*
		 * Get the year from the original email Date header.
		 */
		$dateHeader = $this->extractHeader($raw, 'Date');

		if (! $dateHeader) {
			throw new RuntimeException(
				'The email does not contain a Date header.'
			);
		}

		$emailDate = Carbon::parse($dateHeader);
		$year = $emailDate->year;

		$oldDeparture = $this->dateFromToken(
			$dateTokens[0],
			$year
		);

		$oldReturn = $this->dateFromToken(
			$dateTokens[1],
			$year
		);

		/*
		 * New trip:
		 *
		 * outbound = tomorrow
		 * return	= four days after outbound
		 */
		$departure = Carbon::tomorrow();
		$return = $departure->copy()->addDays(4);

		/*
		 * Replace the compact Delta itinerary headings:
		 *
		 *	   Wed, 02SEP
		 */
		$raw = str_replace(
			$this->compactDate($oldDeparture),
			$this->compactDate($departure),
			$raw
		);

		$raw = str_replace(
			$this->compactDate($oldReturn),
			$this->compactDate($return),
			$raw
		);

		/*
		 * Delta also uses forms such as:
		 *
		 *	   Sun 06 Sep 2026
		 *
		 * elsewhere in the message.
		 */
		$raw = str_replace(
			$oldDeparture->format('D d M Y'),
			$departure->format('D d M Y'),
			$raw
		);

		$raw = str_replace(
			$oldReturn->format('D d M Y'),
			$return->format('D d M Y'),
			$raw
		);

		return [
			$raw,
			$departure,
			$return,
		];
	}

	// =========================================================================
	private function resolveFile(string $file): ?string {
		// Explicit/relative/absolute path supplied.
		if (is_file($file)) {
			return $file;
		}

		// Otherwise treat it as a fixture filename.
		$fixture = base_path(
			'tests/Fixtures/flight-confirmations/' . $file
		);

		if (is_file($fixture)) {
			return $fixture;
		}

		return null;
	}

	// =========================================================================
	private function dateFromToken(
		string $token,
		int $year
	): Carbon {
		/*
		 * "Wed, 02SEP" -> "02SEP 2026"
		 */
		if (! preg_match(
			'/(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),\s+(\d{2}[A-Z]{3})/',
			$token,
			$match
		)) {
			throw new RuntimeException(
				"Invalid itinerary date: {$token}"
			);
		}

		$date = Carbon::createFromFormat(
			'!dM Y',
			$match[1] . ' ' . $year
		);

		if (! $date) {
			throw new RuntimeException(
				"Unable to parse itinerary date: {$token}"
			);
		}

		return $date;
	}

	// =========================================================================
	private function compactDate(Carbon $date): string {
		return sprintf(
			'%s, %s',
			$date->format('D'),
			strtoupper($date->format('dM'))
		);
	}

	// =========================================================================
	private function extractHeader(
		string $raw,
		string $name
	): ?string {
		if (! preg_match(
			'/^' . preg_quote($name, '/') . ':\s*(.+)$/mi',
			$raw,
			$match
		)) {
			return null;
		}

		return trim($match[1]);
	}

	// =========================================================================
	private function extractBodies(string $raw): array {
		/*
		 * This sample is multipart/alternative. Determine its MIME boundary.
		 */
		if (! preg_match(
			'/Content-Type:\s*multipart\/alternative\s*;\s*'
				. '(?:\r?\n[ \t]*)?boundary="([^"]+)"/i',
			$raw,
			$match
		)) {
			throw new RuntimeException(
				'Unable to find multipart/alternative MIME boundary.'
			);
		}

		$boundary = $match[1];

		$parts = preg_split(
			'/\r?\n--' . preg_quote($boundary, '/') .
			'(?:--)?\r?\n/',
			$raw
		);

		$textBody = null;
		$htmlBody = null;

		foreach ($parts as $part) {
			if (! preg_match(
				'/^Content-Type:\s*(text\/plain|text\/html)\b/im',
				$part,
				$typeMatch
			)) {
				continue;
			}

			/*
			 * Split MIME-part headers from its body.
			 */
			$pieces = preg_split(
				"/\r?\n\r?\n/",
				$part,
				2
			);

			if (count($pieces) !== 2) {
				continue;
			}

			[$headers, $body] = $pieces;

			$body = $this->decodeMimeBody(
				$headers,
				$body
			);

			if (
				strcasecmp($typeMatch[1], 'text/plain') === 0
			) {
				$textBody = $body;
			}

			if (
				strcasecmp($typeMatch[1], 'text/html') === 0
			) {
				$htmlBody = $body;
			}
		}

		if ($textBody === null && $htmlBody === null) {
			throw new RuntimeException(
				'No text/plain or text/html MIME body was found.'
			);
		}

		return [
			$textBody ?? '',
			$htmlBody ?? '',
		];
	}

	// =========================================================================
	private function decodeMimeBody(
		string $headers,
		string $body
	): string {
		if (! preg_match(
			'/^Content-Transfer-Encoding:\s*([^\s]+)/mi',
			$headers,
			$match
		)) {
			return $body;
		}

		return match (strtolower($match[1])) {
			'base64' => base64_decode($body, true) ?: '',
			'quoted-printable' => quoted_printable_decode($body),
			default => $body,
		};
	}
}
