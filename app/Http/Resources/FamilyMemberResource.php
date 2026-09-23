<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class FamilyMemberResource extends JsonResource {
	// =========================================================================
	public function toArray($request) {
		return [
			'id'       => $this->id,
			'name'     => $this->pivot->name,
			'email'    => $this->email,
			'auto_add' => (bool) $this->pivot->auto_add,
		];
	}
}
