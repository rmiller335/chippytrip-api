<?php

namespace Tests\Safe;

// =============================================================================
class SmokeTest extends TestCase {
	// =========================================================================
	// RefreshDatabase wipes the database, so make sure it's the testing one
	// from phpunit.xml and not the dev database from .env.
	public function test_database_is_isolated_testing_db(): void {
		$this->assertSame('mysql', config('database.default'));
		$this->assertSame('chippytrip_api_testing', config('database.connections.mysql.database'));

		$this->makeFlight();

		$this->assertDatabaseCount('flights', 1);
	}
}
