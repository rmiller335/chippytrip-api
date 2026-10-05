<?php

namespace Tests\Safe\Models;

use App\Enums\SubscriptionType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Safe\TestCase;

// =============================================================================
// users.subscription_type is a plain varchar that User now casts to
// SubscriptionType. These pin the enum to the values already stored in the
// column and check that code written against the old string attribute (string
// assignment, string queries, the JSON the API returns) still works.
class UserSubscriptionTypeTest extends TestCase {
	// =========================================================================
	// Existing rows hold these strings, and a value the enum doesn't know
	// throws a ValueError when the attribute is read. Renaming or removing a
	// case needs a data migration first.
	public function test_enum_values_match_stored_strings(): void {
		$this->assertEqualsCanonicalizing(
			['basic', 'family', 'free', 'frequent', 'super'],
			array_column(SubscriptionType::cases(), 'value')
		);
	}

	// =========================================================================
	public function test_column_default_is_free(): void {
		$user = User::factory()->create();

		$this->assertSame(SubscriptionType::Free, $user->fresh()->subscription_type);
	}

	// =========================================================================
	public function test_every_case_round_trips_through_the_database(): void {
		foreach (SubscriptionType::cases() as $type) {
			$user = User::factory()->create(['subscription_type' => $type]);

			$this->assertSame(
				$type->value,
				DB::table('users')->where('id', $user->id)->value('subscription_type')
			);
			$this->assertSame($type, $user->fresh()->subscription_type);
		}
	}

	// =========================================================================
	public function test_string_assignment_still_works(): void {
		$user = User::factory()->create(['subscription_type' => 'family']);
		$this->assertSame(SubscriptionType::Family, $user->fresh()->subscription_type);

		$user->subscription_type = 'super';
		$user->save();
		$this->assertSame(SubscriptionType::Super, $user->fresh()->subscription_type);
	}

	// =========================================================================
	public function test_rows_written_without_the_model_are_cast(): void {
		$user = User::factory()->create();

		DB::table('users')->where('id', $user->id)->update(['subscription_type' => 'frequent']);

		$this->assertSame(SubscriptionType::Frequent, $user->fresh()->subscription_type);
	}

	// =========================================================================
	public function test_queries_accept_strings_and_enums(): void {
		$user = User::factory()->create(['subscription_type' => SubscriptionType::Basic]);
		User::factory()->create();

		$this->assertSame(
			[$user->id],
			User::where('subscription_type', 'basic')->pluck('id')->all()
		);
		$this->assertSame(
			[$user->id],
			User::where('subscription_type', SubscriptionType::Basic)->pluck('id')->all()
		);
	}

	// =========================================================================
	public function test_unknown_value_is_rejected(): void {
		$this->expectException(\ValueError::class);

		User::factory()->create(['subscription_type' => 'platinum']);
	}

	// =========================================================================
	// /api/user returns the raw model, so clients must still see a string.
	public function test_api_user_serializes_as_string(): void {
		$user = User::factory()->create(['subscription_type' => SubscriptionType::Super]);

		$this->actingAs($user, 'sanctum')
			->getJson('/api/user')
			->assertOk()
			->assertJsonPath('subscription_type', 'super');
	}

	// =========================================================================
	// FamilyMemberController creates new members with the string 'family'.
	public function test_family_member_creation_sets_family_type(): void {
		$user = User::factory()->create();

		$this->actingAs($user, 'sanctum')
			->putJson('/api/family-members', [
				'family' => [
					['name' => 'Kid', 'email' => 'kid@example.com'],
				],
			])
			->assertOk();

		$this->assertSame(
			SubscriptionType::Family,
			User::where('email', 'kid@example.com')->first()->subscription_type
		);
	}
}
