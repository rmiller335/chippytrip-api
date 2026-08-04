<?php

namespace App\Traits;

use App\Models\Watch;

// =============================================================================
trait Callback {
    // =========================================================================
	public function toArray(object $notifiable): array {
		$watch = Watch::where('subscription_id', $this->callback->alert_id)->first();

		return [
			'event' =>			$this->callback->event_code,
			'alert_id' =>		$this->callback->alert_id,
			'watch_id' =>		$watch ? $watch->id : null,
			'callback_id' =>	$this->callback->id,
			'summary' =>		$this->callback->summary,
			'description' =>	$this->callback->long_description,
		];
	}

    // =========================================================================
    public function via(object $notifiable): array {
		return array_unique(
			array_merge(
				$notifiable->channel_ids->toArray(), [ 'database' ]
			)
		);
    }

    // =========================================================================
    public function toFcm(object $notifiable): array {
		return [
			'title' => $this->callback->summary,
			'body'  => $this->callback->long_description,
		];
    }
}
