<?php

namespace Tests\Safe\Console\Commands;

use App\Jobs\EnableWatch;
use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Safe\TestCase;

// =============================================================================
class WatchCreateTest extends TestCase {
	// =========================================================================
	public function test_creates_a_watch_and_enables_it_when_watchable(): void {
		Bus::fake();

		$flight = $this->makeFlight(['departure_date' => Carbon::now()->toDateString()]);

		$this->artisan('watch:create', ['flight-id' => $flight->id])->assertExitCode(0);

		$this->assertDatabaseHas('watches', ['flight_id' => $flight->id]);
		Bus::assertDispatched(EnableWatch::class);
	}

	// =========================================================================
	public function test_creates_a_watch_without_enabling_when_not_watchable(): void {
		Bus::fake();

		$flight = $this->makeFlight(['departure_date' => Carbon::now()->addDays(30)->toDateString()]);

		$this->artisan('watch:create', ['flight-id' => $flight->id])->assertExitCode(0);

		$this->assertDatabaseHas('watches', ['flight_id' => $flight->id]);
		Bus::assertNotDispatched(EnableWatch::class);
	}
}
