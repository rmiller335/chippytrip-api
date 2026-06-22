<?php

namespace App\Models;

use App\Models\Flight;
use App\Models\WatchCallback;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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

	// =========================================================================
    public function notifications(): BelongsToMany {
        return $this->belongsToMany(
            \Illuminate\Notifications\DatabaseNotification::class,
            'watches_notifications',  // your pivot table
            'watch_id',               // FK for Watch
            'notification_id',        // FK for DatabaseNotification
        );
    }
	// =========================================================================
	public function watchable(): bool {
		$now = Carbon::now();

		$start =	$this->flight->alert_start;
		$end =		$this->flight->alert_end;

		return $start->lte($now->startOfDay())
				&& $end->gte($now->endOfDay())
		;
	}
}
