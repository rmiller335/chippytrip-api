<?php

namespace App\Models;

use App\Models\Error;
use App\Models\Flight;
use App\Models\EmailRelatedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// =============================================================================
class InboundEmail extends Model {
    protected $fillable = [
        'user_id',
        'message_id',
        'from_address',
        'subject',
        'text_body',
        'html_body',
        'status',
        'error',
    ];

	// =========================================================================
	public function emailRelatedRecords() {
		return $this->hasMany(EmailRelatedRecord::class);
	}

	// =========================================================================
	public function errors() {
		return $this->morphMany(Error::class, 'errorable');
	}

	// =========================================================================
	public function flights() {
		return $this->hasManyThrough(
			Flight::class,
			EmailRelatedRecord::class,
			'inbound_email_id', // FK on email_related_records
			'id',                // FK on flights
			'id',                // local key on inbound_emails
			'record_id'          // local key on email_related_records
		)->where('email_related_records.record_type', Flight::class);
	}
	// =========================================================================
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
