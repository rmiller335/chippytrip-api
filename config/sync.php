<?php

return [
	// How many days back /api/sync/listeners goes. Flights that departed
	// before this aren't sent to the client.
	'past_days' => (int) env('SYNC_PAST_DAYS', 14),
];
