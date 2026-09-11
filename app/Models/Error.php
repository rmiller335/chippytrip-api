<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =============================================================================
class Error extends Model {
	protected $fillable = [
		'code',
		'context',
		'message',
	];

	protected $casts = [
		'context' => 'array',
	];

	// =========================================================================
	public function errorable() {
		return $this->morphTo();
	}
}
