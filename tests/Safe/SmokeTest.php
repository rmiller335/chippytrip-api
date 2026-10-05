<?php

namespace Tests\Safe;

// =============================================================================
class SmokeTest extends TestCase {
	// =========================================================================
	// RefreshDatabase wipes the database, so make sure it's the testing one
	// from phpunit.xml and not the dev database from .env.
	public function test_database_is_isolated_testing_db(): void {
		$conn = config('database.default');
		$db   = config(
			"database.connections.$conn.database"
		);

		$this->assertContains(
			"$conn:$db",
			[
				'sqlite::memory:',
				'mysql:chippytrip_api_testing',
			]
		);

		$this->makeFlight();

		$this->assertDatabaseCount('flights', 1);
	}
}
