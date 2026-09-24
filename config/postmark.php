<?php

return [
	'inbound_url' => env('POSTMARK_INBOUND_URL', null),

	// Basic-auth credentials Postmark must send to /api/postmark/inbound.
	// Put them in the inbound webhook URL in Postmark:
	// https://inbound:<password>@host/api/postmark/inbound
	// If the password is empty, every request is rejected.
	'inbound_user' => env('POSTMARK_INBOUND_USER', 'inbound'),
	'inbound_password' => env('POSTMARK_INBOUND_PASSWORD'),
];
