<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// =============================================================================
class Listener extends Model {
	protected $table = 'listeners';

	protected $fillable = [
		'user_id',
		'travelers',
		'watch_id',
	];

	// =========================================================================
	public function user(): BelongsTo {
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

	// =========================================================================
	public function watch(): BelongsTo {
		return $this->belongsTo(Watch::class, 'watch_id', 'id');
	}
}
