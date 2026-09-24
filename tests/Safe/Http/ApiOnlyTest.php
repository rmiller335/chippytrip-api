<?php

namespace Tests\Safe\Http;

use Tests\Safe\TestCase;

// =============================================================================
// There are no web routes; everything answers in JSON, whatever the client
// sends as Accept.
class ApiOnlyTest extends TestCase {
	// =========================================================================
	public function test_root_is_a_json_404(): void {
		$response = $this->get('/', ['Accept' => 'text/html']);

		$response->assertStatus(404);
		$response->assertHeader('Content-Type', 'application/json');
		$response->assertJsonStructure(['message']);
	}

	// =========================================================================
	public function test_removed_routes_are_gone(): void {
		foreach (['/up', '/login'] as $uri) {
			$this->get($uri)->assertStatus(404);
		}
	}

	// =========================================================================
	public function test_unauthenticated_request_is_a_json_401_not_a_redirect(): void {
		$response = $this->get('/api/user', ['Accept' => 'text/html']);

		$response->assertStatus(401);
		$response->assertExactJson(['message' => 'Unauthenticated.']);
	}

	// =========================================================================
	public function test_validation_errors_are_json_without_an_accept_header(): void {
		$user = \App\Models\User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->post('/api/flights/search', [], ['Accept' => '*/*']);

		$response->assertStatus(422);
		$response->assertJsonValidationErrors(['origin', 'destination', 'date']);
	}
}
