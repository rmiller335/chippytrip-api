<?php

namespace Tests\Safe\Http\Controllers;

use App\Jobs\ParseConfirmationEmail;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Safe\TestCase;

// =============================================================================
class PostmarkInboundTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		config(['postmark.inbound_user' => 'inbound', 'postmark.inbound_password' => 'test-password-16']);
	}

	// =========================================================================
	private function sendEmail(
		string $from,
		?string $messageId = 'msg-1',
		?string $user = 'inbound',
		?string $password = 'test-password-16',
	) {
		$headers = null === $user
			? []
			: ['Authorization' => 'Basic ' . base64_encode("{$user}:{$password}")];

		return $this->withHeaders($headers)->postJson('/api/postmark/inbound', array_filter([
			'FromFull' => ['Email' => $from],
			'Subject' => 'Your booking confirmation',
			'TextBody' => 'UA100 SFO to JFK',
			'MessageID' => $messageId,
		]));
	}

	// =========================================================================
	public function test_email_from_a_user_is_stored_and_parsed(): void {
		$user = User::factory()->create(['email' => 'pat@example.com']);

		$this->sendEmail('Pat@Example.com')->assertStatus(200);

		$email = InboundEmail::sole();
		$this->assertSame($user->id, $email->user_id);
		$this->assertSame('pending', $email->status);
		Queue::assertPushed(ParseConfirmationEmail::class, 1);
	}

	// =========================================================================
	public function test_email_from_unknown_sender_is_discarded(): void {
		$this->sendEmail('stranger@example.com')->assertStatus(200);

		$this->assertSame(0, InboundEmail::count());
		Queue::assertNothingPushed();
	}

	// =========================================================================
	public function test_a_retried_email_is_parsed_once(): void {
		User::factory()->create(['email' => 'pat@example.com']);

		$this->sendEmail('pat@example.com')->assertStatus(200);
		$this->sendEmail('pat@example.com')->assertStatus(200);

		$this->assertSame(1, InboundEmail::count());
		Queue::assertPushed(ParseConfirmationEmail::class, 1);
	}

	// =========================================================================
	public function test_emails_without_a_message_id_are_each_stored(): void {
		User::factory()->create(['email' => 'pat@example.com']);

		$this->sendEmail('pat@example.com', messageId: null);
		$this->sendEmail('pat@example.com', messageId: null);

		$this->assertSame(2, InboundEmail::count());
		Queue::assertPushed(ParseConfirmationEmail::class, 2);
	}

	// =========================================================================
	public function test_requests_without_credentials_are_rejected(): void {
		User::factory()->create(['email' => 'pat@example.com']);

		$response = $this->sendEmail('pat@example.com', user: null);

		$response->assertStatus(401);
		$response->assertHeader('WWW-Authenticate');
		$this->assertSame(0, InboundEmail::count());
		Queue::assertNothingPushed();
	}

	// =========================================================================
	public function test_wrong_credentials_are_rejected(): void {
		User::factory()->create(['email' => 'pat@example.com']);

		$this->sendEmail('pat@example.com', password: 'wrong')->assertStatus(401);
		$this->sendEmail('pat@example.com', user: 'someone')->assertStatus(401);

		$this->assertSame(0, InboundEmail::count());
	}

	// =========================================================================
	// With no password configured the endpoint stays closed.
	public function test_everything_is_rejected_without_a_configured_password(): void {
		config(['postmark.inbound_password' => null]);
		User::factory()->create(['email' => 'pat@example.com']);

		$this->sendEmail('pat@example.com', password: '')->assertStatus(401);
	}
}
