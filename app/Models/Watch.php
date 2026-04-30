<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =============================================================================
class Watch extends Model {
	protected $table = 'watches';

	protected $fillable = [
		'flight_id',
		'subscription_id',
	];
}
