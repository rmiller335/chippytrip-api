<?php

namespace Tests\Safe\Models;

use App\Models\Airline;
use Tests\Safe\TestCase;

class AirlineTest extends TestCase
{
    private function makeAirline(array $overrides = []): Airline
    {
        return new Airline(array_merge([
            'icao' => 'UAL',
            'iata' => 'UA',
            'call_sign' => 'UNITED',
            'name' => 'United Airlines',
            'country_code' => 'US',
            'status' => 'active',
            'types' => ['M'],
        ], $overrides));
    }

    public function test_has_type_checks_the_array_cast(): void
    {
        $airline = $this->makeAirline(['types' => ['M', 'J']]);

        $this->assertTrue($airline->hasType('M'));
        $this->assertFalse($airline->hasType('X'));
    }

    public function test_equal_ignores_type_order(): void
    {
        $a = $this->makeAirline(['types' => ['M', 'J']]);
        $b = $this->makeAirline(['types' => ['J', 'M']]);

        $this->assertTrue($a->equal($b));
    }

    public function test_equal_is_false_when_name_differs(): void
    {
        $a = $this->makeAirline();
        $b = $this->makeAirline(['name' => 'Different Airline']);

        $this->assertFalse($a->equal($b));
    }

    public function test_country_relation(): void
    {
        \App\Models\Country::create([
            'iso2' => 'US',
            'iso3' => 'USA',
            'name' => 'United States',
            'dominion' => '',
        ]);

        $airline = $this->makeAirline();
        $airline->save();

        $this->assertSame('United States', $airline->country->name);
    }
}
