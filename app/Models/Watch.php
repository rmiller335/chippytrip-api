<?php

namespace App\Models;

use App\Models\Flight;
use App\Models\WatchCallback;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Watch extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $table = 'watches';

	protected $attributes = [
		'enabled' =>	false,
	];

	protected $fillable = [
		'flight_id',
		'subscription_id',
		'enabled',
		'secret',
	];

	// =========================================================================
/*
	protected static function booted(): void {
		static::saving(function(Watch $w) {
			if(!$w->enabled) {
				$w->secret = null;
				$w->subscription_id = null;
			}
		});
	}
*/

	// =========================================================================
	public function callbacks(): HasMany {
		return $this->hasMany(WatchCallback::class, 'alert_id', 'subscription_id');
	}

	// =========================================================================
	public function disable() {
		$this->subscription_id =	null;
		$this->secret =				null;
		$this->enabled =			false;
	}

	// =========================================================================
	public function enable($subsId, $secret) {
		$this->subscription_id =	$subsId;
		$this->secret =				$secret;
		$this->enabled =			true;
	}

	// =========================================================================
	public function flight(): HasOne {
		return $this->hasOne(Flight::class, 'id', 'flight_id');
	}


	// =========================================================================
	public static function genSecret(): string {
		return bin2hex(random_bytes(16));
	}

	// =========================================================================
	public function listeners(): HasMany {
		return $this->hasMany(Listener::class, 'watch_id', 'id');
	}
}
