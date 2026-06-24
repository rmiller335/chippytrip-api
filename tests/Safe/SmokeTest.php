<?php

namespace Tests\Safe;

class SmokeTest extends TestCase
{
    public function test_database_is_isolated_sqlite(): void
    {
        $this->assertSame('sqlite', config('database.default'));

        $this->makeFlight();

        $this->assertDatabaseCount('flights', 1);
    }
}
