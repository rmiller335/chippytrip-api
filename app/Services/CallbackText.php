<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\WatchCallback;
use Carbon\Carbon;

// =============================================================================
// Friendlier notification text for a FlightAware callback, e.g.
//
//   DL185 took off from MXP
//   Arrives JFK 5:22 PM (10 min early) at Terminal 4, Gate B35.
//
// Built from the callback's structured fields instead of FlightAware's
// summary/long_description, which are written for dispatchers (ICAO idents,
// aircraft types, filed routes). Times are local to the airport they refer
// to. Events we don't handle, or callbacks missing what we need, fall back
// to FlightAware's summary and short_description.
class CallbackText {
	// A difference smaller than this still counts as on time.
	private const ON_TIME_MINUTES = 5;

	/** @var array<string, ?Airport> */
	private array $airports = [];

	private ?array $text = null;

	// =========================================================================
	public function __construct(private readonly WatchCallback $cb) {
	}

	// =========================================================================
	public function title(): string {
		return $this->compose()['title'];
	}

	// =========================================================================
	public function body(): string {
		return $this->compose()['body'];
	}

	// =========================================================================
	private function compose(): array {
		if (null !== $this->text) {
			return $this->text;
		}

		$text = match ($this->cb->event_code) {
			'filed' =>								$this->filed(),
			'out', 'offblock' =>					$this->leftGate(),
			'departure' =>							$this->tookOff(),
			'arrival' =>							$this->landed(),
			'in', 'onblock' =>						$this->atGate(),
			'cancelled' =>							$this->cancelled(),
			'diverted' =>							$this->diverted(),
			'hold_start' =>							$this->holding(),
			'hold_end' =>							$this->holdEnded(),
			'change', 'gate_change' =>				$this->changed(),
			'delay', 'departure_delay' =>			$this->departureDelay(),
			'arrival_delay' =>						$this->arrivalDelay(),
			default =>								null,
		};

		// Nothing to identify the flight by means the payload is too thin to
		// say anything useful ourselves.
		if (null === $text || null === $this->flight()) {
			$text = [
				'title' =>	$this->cb->summary ?? '',
				'body' =>	$this->cb->short_description ?? $this->cb->long_description ?? '',
			];
		}

		return $this->text = [
			'title' =>	$text['title'],
			'body' =>	trim(preg_replace('/\s+/', ' ', $text['body'])),
		];
	}

	// -------------------------------------------------------------------------
	// Events
	// -------------------------------------------------------------------------

	// =========================================================================
	// "DL185 is on time" / "Departs MXP 2:25 PM from Terminal 1, Gate B72.
	// Arrives JFK 5:21 PM."
	private function filed(): array {
		return [
			'title' =>	$this->flight() . ' ' . $this->runningStatus($this->departureMinutesLate()),
			'body' =>	$this->departsSentence(withDelay: false) . ' ' . $this->arrivesSentence(withDelay: false),
		];
	}

	// =========================================================================
	private function leftGate(): array {
		return [
			'title' =>	$this->flight() . ' has left the gate at ' . $this->code('origin'),
			'body' =>	$this->arrivesSentence(),
		];
	}

	// =========================================================================
	private function tookOff(): array {
		return [
			'title' =>	$this->flight() . ' took off from ' . $this->code('origin'),
			'body' =>	$this->arrivesSentence(),
		];
	}

	// =========================================================================
	// "DL185 landed at JFK" / "Landed 10 min early. Heading to Terminal 4,
	// Gate B35."
	private function landed(): array {
		$parts = [];
		$delay = $this->arrivalMinutesLate();

		if (null !== $delay) {
			$parts[] = 'Landed ' . $this->delayPhrase($delay) . '.';
		}

		if ($place = $this->gatePlace('destination')) {
			$parts[] = "Heading to {$place}.";
		}

		return [
			'title' =>	$this->flight() . ' landed at ' . $this->code('destination'),
			'body' =>	implode(' ', $parts),
		];
	}

	// =========================================================================
	private function atGate(): array {
		$parts = [];

		if ($place = $this->gatePlace('destination')) {
			$parts[] = "{$place}.";
		}

		if ($this->cb->baggage_claim) {
			$parts[] = "Baggage claim {$this->cb->baggage_claim}.";
		}

		return [
			'title' =>	$this->flight() . ' is at the gate at ' . $this->code('destination'),
			'body' =>	implode(' ', $parts),
		];
	}

	// =========================================================================
	private function cancelled(): array {
		$body = 'Check with the airline about rebooking.';

		if ($this->cb->scheduled_out) {
			$body = 'It was scheduled to leave ' . $this->code('origin') . ' '
				. $this->time($this->cb->scheduled_out, 'origin', withDay: true) . '. ' . $body;
		}

		return [
			'title' =>	$this->flight() . ' has been cancelled',
			'body' =>	$body,
		];
	}

	// =========================================================================
	// FlightAware reports the new destination in `destination`; the planned
	// one is on the watched Flight.
	private function diverted(): array {
		$planned = $this->cb->watch?->flight?->destination;
		$now = $this->airport('destination');
		$body = 'Check with the airline for updates.';

		if ($planned && $now && $planned->icao !== $now->icao) {
			$body = 'It is now heading to ' . $this->code('destination')
				. ' instead of ' . ($planned->iata ?: $planned->icao) . '. ' . $body;
		}

		return [
			'title' =>	$this->flight() . ' has been diverted',
			'body' =>	$body,
		];
	}

	// =========================================================================
	// "UA100 is in a holding pattern" / "Arrives JFK 5:40 PM (20 min late)."
	private function holding(): array {
		return [
			'title' =>	$this->flight() . ' is in a holding pattern',
			'body' =>	$this->arrivesSentence(),
		];
	}

	// =========================================================================
	private function holdEnded(): array {
		return [
			'title' =>	$this->flight() . ' has left the holding pattern',
			'body' =>	$this->arrivesSentence(),
		];
	}

	// =========================================================================
	// Compared with the previous callback for this flight, if there is one,
	// so the title names what actually changed. If it's nothing we track,
	// the title gives the next time that matters.
	private function changed(): array {
		$prev = $this->previous();
		$departed = null !== $this->cb->actual_out;
		$title = $this->nextTimeTitle($departed);

		if (! $departed && $this->timeChanged($prev?->estimated_out, $this->cb->estimated_out)) {
			$title = $this->flight() . ' now departs ' . $this->time($this->cb->estimated_out, 'origin');
		}
		elseif (! $departed && $this->valueChanged($prev?->gate_origin, $this->cb->gate_origin)) {
			$title = $this->flight() . ' now departs from Gate ' . $this->cb->gate_origin;
		}
		elseif ($this->timeChanged($prev?->estimated_in, $this->cb->estimated_in)) {
			$title = $this->flight() . ' now arrives ' . $this->time($this->cb->estimated_in, 'destination');
		}
		elseif ($this->valueChanged($prev?->gate_destination, $this->cb->gate_destination)) {
			$title = $this->flight() . ' now arrives at Gate ' . $this->cb->gate_destination;
		}

		$body = $departed
			? $this->arrivesSentence()
			: $this->departsSentence() . ' ' . $this->arrivesSentence();

		return ['title' => $title, 'body' => $body];
	}

	// =========================================================================
	// "DL185 departs 2:15 PM" / "DL185 arrives 5:32 PM"
	private function nextTimeTitle(bool $departed): string {
		$out = $this->cb->estimated_out ?? $this->cb->scheduled_out;
		$in = $this->cb->estimated_in ?? $this->cb->scheduled_in;

		return match (true) {
			! $departed && null !== $out =>	$this->flight() . ' departs ' . $this->time($out, 'origin'),
			null !== $in =>					$this->flight() . ' arrives ' . $this->time($in, 'destination'),
			default =>						$this->flight() . ' has an update',
		};
	}

	// =========================================================================
	private function departureDelay(): array {
		return [
			'title' =>	$this->flight() . ' ' . $this->runningStatus($this->departureMinutesLate()),
			'body' =>	$this->departsSentence(withDelay: false) . ' ' . $this->arrivesSentence(withDelay: false),
		];
	}

	// =========================================================================
	private function arrivalDelay(): array {
		$delay = $this->arrivalMinutesLate();

		return [
			'title' =>	$this->flight() . (null === $delay ? ' is delayed' : ' will arrive ' . $this->delayPhrase($delay)),
			'body' =>	$this->arrivesSentence(withDelay: false),
		];
	}

	// -------------------------------------------------------------------------
	// Sentences
	// -------------------------------------------------------------------------

	// =========================================================================
	// "Departs MXP 2:45 PM (30 min late) from Terminal 1, Gate B72."
	private function departsSentence(bool $withDelay = true): string {
		$time = $this->cb->estimated_out ?? $this->cb->scheduled_out;

		if (null === $time) {
			return '';
		}

		$sentence = 'Departs ' . $this->code('origin') . ' ' . $this->time($time, 'origin');
		$delay = $this->departureMinutesLate();

		if ($withDelay && null !== $delay) {
			$sentence .= ' (' . $this->delayPhrase($delay) . ')';
		}

		if ($place = $this->gatePlace('origin')) {
			$sentence .= " from {$place}";
		}

		return $sentence . '.';
	}

	// =========================================================================
	// "Arrives JFK 5:22 PM (10 min early) at Terminal 4, Gate B35."
	private function arrivesSentence(bool $withDelay = true): string {
		$time = $this->cb->estimated_in ?? $this->cb->scheduled_in;

		if (null === $time) {
			return '';
		}

		$sentence = 'Arrives ' . $this->code('destination') . ' ' . $this->time($time, 'destination');
		$delay = $this->arrivalMinutesLate();

		if ($withDelay && null !== $delay) {
			$sentence .= ' (' . $this->delayPhrase($delay) . ')';
		}

		if ($place = $this->gatePlace('destination')) {
			$sentence .= " at {$place}";
		}

		return $sentence . '.';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	// =========================================================================
	// The flight number passengers know (DL185), not the ICAO one (DAL185).
	private function flight(): ?string {
		return $this->cb->ident_iata ?: $this->cb->ident ?: null;
	}

	// =========================================================================
	// $end is 'origin' or 'destination'.
	private function airport(string $end): ?Airport {
		$icao = $this->cb->{"{$end}_icao"} ?: $this->cb->{$end};

		if (! $icao) {
			return null;
		}

		if (! array_key_exists($icao, $this->airports)) {
			$this->airports[$icao] = Airport::where('icao', $icao)->first();
		}

		return $this->airports[$icao];
	}

	// =========================================================================
	private function code(string $end): string {
		return $this->cb->{"{$end}_iata"}
			?: $this->airport($end)?->iata
			?: $this->cb->{$end}
			?: ($end === 'origin' ? 'origin' : 'destination');
	}

	// =========================================================================
	// "Terminal 4, Gate B35", or whichever part is known.
	private function gatePlace(string $end): ?string {
		$parts = array_filter([
			$this->cb->{"terminal_{$end}"} ? 'Terminal ' . $this->cb->{"terminal_{$end}"} : null,
			$this->cb->{"gate_{$end}"} ? 'Gate ' . $this->cb->{"gate_{$end}"} : null,
		]);

		return $parts ? implode(', ', $parts) : null;
	}

	// =========================================================================
	// Local time at that end of the flight. Adds the weekday when it falls on
	// a different local day from the scheduled departure (overnight flights),
	// or always with $withDay.
	private function time(Carbon $dt, string $end, bool $withDay = false): string {
		$tz = $this->airport($end)?->timezone ?: 'UTC';
		$local = $dt->copy()->setTimezone($tz);
		$format = 'g:i A';

		$departureDay = $this->cb->scheduled_out
			?->copy()->setTimezone($this->airport('origin')?->timezone ?: 'UTC')
			->toDateString();

		if ($withDay || ($departureDay && $local->toDateString() !== $departureDay)) {
			$format = 'D g:i A';
		}

		$formatted = $local->format($format);

		return $tz === 'UTC' ? "{$formatted} UTC" : $formatted;
	}

	// =========================================================================
	// Minutes late (negative = early), from the best time we have.
	private function departureMinutesLate(): ?int {
		return $this->minutesLate(
			$this->cb->actual_out ?? $this->cb->estimated_out,
			$this->cb->scheduled_out
		);
	}

	// =========================================================================
	// Before the gate arrival, a landing is compared with the scheduled
	// landing rather than the scheduled gate arrival, which is later.
	private function arrivalMinutesLate(): ?int {
		if ($this->cb->actual_in) {
			return $this->minutesLate($this->cb->actual_in, $this->cb->scheduled_in);
		}

		if ($this->cb->actual_on && $this->cb->scheduled_on) {
			return $this->minutesLate($this->cb->actual_on, $this->cb->scheduled_on);
		}

		return $this->minutesLate($this->cb->estimated_in, $this->cb->scheduled_in);
	}

	// =========================================================================
	private function minutesLate(?Carbon $actual, ?Carbon $scheduled): ?int {
		if (null === $actual || null === $scheduled) {
			return null;
		}

		return (int) round($scheduled->diffInMinutes($actual, false));
	}

	// =========================================================================
	// "on time", "25 min late", "1 hr 5 min early"
	private function delayPhrase(int $minutes): string {
		if (abs($minutes) < self::ON_TIME_MINUTES) {
			return 'on time';
		}

		return $this->duration(abs($minutes)) . ($minutes > 0 ? ' late' : ' early');
	}

	// =========================================================================
	// "is on time", "is running 25 min late", "is running 10 min early"
	private function runningStatus(?int $minutes): string {
		if (null === $minutes || abs($minutes) < self::ON_TIME_MINUTES) {
			return 'is on time';
		}

		return 'is running ' . $this->delayPhrase($minutes);
	}

	// =========================================================================
	private function duration(int $minutes): string {
		$hours = intdiv($minutes, 60);
		$mins = $minutes % 60;

		return match (true) {
			0 === $hours =>	"{$mins} min",
			0 === $mins =>	"{$hours} hr",
			default =>		"{$hours} hr {$mins} min",
		};
	}

	// =========================================================================
	private function previous(): ?WatchCallback {
		if (! $this->cb->fa_flight_id) {
			return null;
		}

		return WatchCallback::where('fa_flight_id', $this->cb->fa_flight_id)
			->when($this->cb->id, fn ($q) => $q->where('id', '<', $this->cb->id))
			->orderByDesc('id')
			->first();
	}

	// =========================================================================
	// With no previous callback, any known time counts as the news.
	private function timeChanged(?Carbon $before, ?Carbon $after): bool {
		if (null === $after) {
			return false;
		}

		return null === $before || abs($before->diffInMinutes($after, false)) >= 1;
	}

	// =========================================================================
	private function valueChanged(?string $before, ?string $after): bool {
		return null !== $after && $before !== $after;
	}
}
