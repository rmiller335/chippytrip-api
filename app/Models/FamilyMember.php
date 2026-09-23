<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class FamilyMember extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $table = 'family_members';

	protected $casts = [
		'auto_add' =>	'boolean',
	];

	protected $fillable = [
		'user_id',
		'family_member_id',
		'name',
		'auto_add',
	];

	// =========================================================================
	public function user(): BelongsTo {
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

	// =========================================================================
	public function familyMember(): BelongsTo {
		return $this->belongsTo(User::class, 'family_member_id', 'id');
	}
}
