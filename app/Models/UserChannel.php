<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// =============================================================================
class UserChannel extends Model {
	protected $table = 'user_channels';

	protected $casts = [
		'credentials' =>	'json',
	];

	protected $fillable = [
		'channel',
		'credentials',
	];

	// =========================================================================
	public function user(): BelongsTo {
		return $this->belongsTo(User::class);
	}
}
