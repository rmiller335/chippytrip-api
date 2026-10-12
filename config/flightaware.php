<?php

return [
	'callback' => env('FLIGHTAWARE_CALLBACK'),
	'key' => env('FLIGHTAWARE_KEY'),
	'url' => env('FLIGHTAWARE_URL'),

	// Days of slack either side of the watched flight's departure date when
	// creating an AeroAPI alert (start/end are departure dates in the origin's
	// time zone, end inclusive). Callbacks for other departures are stored but
	// not notified (WatchCallback::matchesFlightDate). `php artisan
	// alerts:window-report` shows whether the slack ever catches anything.
	'alert_buffer_days' => (int) env('FLIGHTAWARE_ALERT_BUFFER_DAYS', 1),
];
