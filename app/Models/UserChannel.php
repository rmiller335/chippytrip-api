<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// =============================================================================
class UserChannel extends Model {
	protected $table = 'user_channels';

	protected $casts = [
		'credentials' =>	'json',
	];

	protected $fillable = [
		'user_id',
		'channel',
		'credentials',
	];

	// =========================================================================
	public function user(): BelongsTo {
		return $this->belongsTo(User::class);
	}
}
