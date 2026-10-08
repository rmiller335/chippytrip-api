<?php

namespace App\Enums;

// =============================================================================
enum SubscriptionType: string {
	case Basic =	'basic';
	case Beta =		'beta';
	case Family =	'family';
	case Free =		'free';
	case Frequent =	'frequent';
	case Super =	'super';
}
