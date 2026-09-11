<?php

namespace Tests\Feature;

use App\Services\OpenAIFlightConfirmationExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenAIFlightConfirmationExtractorTest extends TestCase
{
    #[DataProvider('confirmationEmails')]
    public function test_it_extracts_airline_confirmations(
        string $fixture,
        string $expectedConfirmation,
        array $expectedFlights,
        ?array $expectedOperatingCarrier = null,
    ): void {
        if (! filter_var(env('RUN_OPENAI_FLIGHT_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped(
                'Set RUN_OPENAI_FLIGHT_INTEGRATION_TESTS=true to run live OpenAI extraction tests.'
            );
        }

        $path = base_path('tests/Fixtures/flight-confirmations/' . $fixture);

        $this->assertFileExists($path);

        $email = file_get_contents($path);

        $this->assertIsString($email);

        /** @var OpenAIFlightConfirmationExtractor $extractor */
        $extractor = app(OpenAIFlightConfirmationExtractor::class);

        $result = $extractor->extract($email);
        $data = $this->objectToArray($result);

        $this->assertSame(
            $expectedConfirmation,
            $data['confirmation_number'] ?? null,
            "{$fixture}: confirmation number"
        );

        $flights = $data['flights'] ?? [];

        $this->assertCount(
            count($expectedFlights),
            $flights,
            "{$fixture}: number of flight segments"
        );

        foreach ($expectedFlights as $i => $expected) {
            $actual = $flights[$i] ?? [];

            $this->assertSame(
                $expected['flight_number'],
                (string) ($actual['flight_number'] ?? ''),
                "{$fixture} segment {$i}: flight number"
            );

            $this->assertSame(
                $expected['carrier'],
                $actual['marketing_carrier']['iata'] ?? null,
                "{$fixture} segment {$i}: marketing carrier IATA"
            );

            $this->assertSame(
                $expected['from'],
                $actual['departure_airport']['iata'] ?? null,
                "{$fixture} segment {$i}: departure airport IATA"
            );

            $this->assertSame(
                $expected['to'],
                $actual['arrival_airport']['iata'] ?? null,
                "{$fixture} segment {$i}: arrival airport IATA"
            );
        }

        if ($expectedOperatingCarrier !== null) {
            $i = $expectedOperatingCarrier['index'];
            $carrier = $flights[$i]['operating_carrier'] ?? [];

            $this->assertStringContainsStringIgnoringCase(
                $expectedOperatingCarrier['name_contains'],
                (string) ($carrier['name'] ?? ''),
                "{$fixture} segment {$i}: operating carrier name"
            );

            $this->assertSame(
                $expectedOperatingCarrier['iata'],
                $carrier['iata'] ?? null,
                "{$fixture} segment {$i}: operating carrier IATA"
            );
        }
    }

    public static function confirmationEmails(): array
    {
        return [
            'Delta simple' => [
                'delta-simple.eml',
                'Q7M2LK',
                [
                    ['flight_number' => '2047', 'carrier' => 'DL', 'from' => 'ROC', 'to' => 'ATL'],
                    ['flight_number' => '174',  'carrier' => 'DL', 'from' => 'ATL', 'to' => 'MXP'],
                ],
                null,
            ],

            'Delta Connection / Endeavor' => [
                'delta-connection.eml',
                'X4N8PV',
                [
                    ['flight_number' => '4812', 'carrier' => 'DL', 'from' => 'ROC', 'to' => 'LGA'],
                    ['flight_number' => '132',  'carrier' => 'DL', 'from' => 'JFK', 'to' => 'ATH'],
                ],
                ['index' => 0, 'name_contains' => 'Endeavor', 'iata' => '9E'],
            ],

            'United multi-carrier' => [
                'united-multicarrier.eml',
                'L8R3WX',
                [
                    ['flight_number' => '972',  'carrier' => 'UA', 'from' => 'ORD', 'to' => 'BRU'],
                    ['flight_number' => '9981', 'carrier' => 'UA', 'from' => 'BRU', 'to' => 'FCO'],
                    ['flight_number' => '7204', 'carrier' => 'UA', 'from' => 'FCO', 'to' => 'ADD'],
                    ['flight_number' => '500',  'carrier' => 'ET', 'from' => 'ADD', 'to' => 'IAD'],
                    ['flight_number' => '4176', 'carrier' => 'UA', 'from' => 'IAD', 'to' => 'ROC'],
                ],
                null,
            ],

            'Southwest' => [
                'southwest.eml',
                'H7K3QZ',
                [
                    ['flight_number' => '1834', 'carrier' => 'WN', 'from' => 'ROC', 'to' => 'BWI'],
                    ['flight_number' => '227',  'carrier' => 'WN', 'from' => 'BWI', 'to' => 'ROC'],
                ],
                null,
            ],

            'American' => [
                'american.eml',
                'J6QXMP',
                [
                    ['flight_number' => '1978', 'carrier' => 'AA', 'from' => 'ROC', 'to' => 'CLT'],
                    ['flight_number' => '748',  'carrier' => 'AA', 'from' => 'CLT', 'to' => 'MAD'],
                ],
                null,
            ],

            'Lufthansa German' => [
                'lufthansa-de.eml',
                'P8LMQ2',
                [
                    ['flight_number' => '413', 'carrier' => 'LH', 'from' => 'JFK', 'to' => 'FRA'],
                    ['flight_number' => '230', 'carrier' => 'LH', 'from' => 'FRA', 'to' => 'FCO'],
                ],
                null,
            ],

            'Air France French' => [
                'air-france-fr.eml',
                'AF7KQ9',
                [
                    ['flight_number' => '009',  'carrier' => 'AF', 'from' => 'JFK', 'to' => 'CDG'],
                    ['flight_number' => '1304', 'carrier' => 'AF', 'from' => 'CDG', 'to' => 'FCO'],
                ],
                null,
            ],

            'ITA Italian' => [
                'ita-it.eml',
                'R4T9XZ',
                [
                    ['flight_number' => '611',  'carrier' => 'AZ', 'from' => 'JFK', 'to' => 'FCO'],
                    ['flight_number' => '1463', 'carrier' => 'AZ', 'from' => 'FCO', 'to' => 'VCE'],
                ],
                null,
            ],

            'Iberia Spanish' => [
                'iberia-es.eml',
                'M3V7LP',
                [
                    ['flight_number' => '6250', 'carrier' => 'IB', 'from' => 'JFK', 'to' => 'MAD'],
                    ['flight_number' => '3170', 'carrier' => 'IB', 'from' => 'MAD', 'to' => 'LHR'],
                ],
                null,
            ],

            'ANA Japanese' => [
                'ana-ja.eml',
                'N7K2QP',
                [
                    ['flight_number' => '109', 'carrier' => 'NH', 'from' => 'JFK', 'to' => 'HND'],
                    ['flight_number' => '33',  'carrier' => 'NH', 'from' => 'HND', 'to' => 'ITM'],
                ],
                null,
            ],
        ];
    }

    /**
     * Convert the DTO tree to arrays without depending on a particular DTO package.
     */
    private function objectToArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->objectToArray($item), $value);
        }

        if (! is_object($value)) {
            return $value;
        }

        if (method_exists($value, 'toArray')) {
            return $this->objectToArray($value->toArray());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->objectToArray($value->jsonSerialize());
        }

        $reflection = new \ReflectionObject($value);
        $result = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            if (! $property->isInitialized($value)) {
                continue;
            }

            $result[$property->getName()] = $this->objectToArray(
                $property->getValue($value)
            );
        }

        return $result;
    }
}
