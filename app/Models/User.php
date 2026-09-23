<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\UserChannel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// =============================================================================
#[Fillable(['name', 'email', 'password', 'subscription_type'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

	// =========================================================================
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

	// =========================================================================
	public function channels() : HasMany {
		return $this->hasMany(UserChannel::class);
	}

	// =========================================================================
	public function getChannelIdsAttribute() {
		return $this->channels->pluck('channel');
	}

	// =========================================================================
	public function listeners(): HasMany {
		return $this->hasMany(Listener::class, 'user_id', 'id');
	}

	// =========================================================================
	public function familyMembers(): HasMany {
		return $this->hasMany(FamilyMember::class, 'user_id', 'id');
	}

	// =========================================================================
	public function family(): BelongsToMany {
		return $this->belongsToMany(User::class, 'family_members', 'user_id', 'family_member_id')
			->withPivot('name', 'auto_add')
			->withTimestamps()
		;
	}

}
