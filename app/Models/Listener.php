<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Listener extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $table = 'listeners';

	protected $fillable = [
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
