<?php

namespace Tests\Unit;

use App\Models\WatchCallback;
use App\Models\User;
use App\Notifications\Departure;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// =============================================================================
class PushifyTest extends TestCase {
    public function test_pushify(): void {
		$user = User::first();
		$this->assertNotNull($user);

		$callback = WatchCallback::where('event_code', 'departure')->first();
		$this->assertNotNull($callback);

		$notification = new Departure($callback);

		$user->notify($notification);

        $this->assertTrue(true);
    }
}
