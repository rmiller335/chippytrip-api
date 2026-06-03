<?php

namespace App\Traits;

// =============================================================================
trait Callback {
    // =========================================================================
	public function toArray(object $notifiable): array {
		return [
			'event' =>			$this->callback->event_code,
			'watch_id' =>		$this->callback->alert_id,
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
}
