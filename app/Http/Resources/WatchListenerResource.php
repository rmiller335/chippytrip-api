<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class WatchListenerResource extends JsonResource {
	// =========================================================================
	public function toArray($request) {
		return [
			'email' => $this->user->email,
			'name'  => $this->user->name,
		];
	}
}
