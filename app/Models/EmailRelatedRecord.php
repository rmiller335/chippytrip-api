<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =============================================================================
class EmailRelatedRecord extends Model
{
    protected $fillable = [
        'inbound_email_id',
        'record_type',
        'record_id',
    ];

	// =========================================================================
    public function inboundEmail() {
        return $this->belongsTo(InboundEmail::class);
    }

	// =========================================================================
    public function record() {
        return $this->morphTo(__FUNCTION__, 'record_type', 'record_id');
    }
}
