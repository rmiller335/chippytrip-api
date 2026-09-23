<?php

namespace App\Http\Controllers;

use App\Http\Resources\FamilyMemberResource;
use App\Models\User;
use Illuminate\Http\Request;

// =============================================================================
class FamilyMemberController extends Controller {
	// =========================================================================
	public function index(Request $request) {
		return FamilyMemberResource::collection($request->user()->family);
	}

	// =========================================================================
	// Replaces the authenticated user's full set of family members. Any email
	// that doesn't already belong to a user creates one (with subscription
	// type 'family' and a null password, marking it as awaiting first-time
	// login) so it can be attached as a listener later on.
	public function update(Request $request) {
		$request->validate([
			'family' =>				'present|array',
			'family.*.name' =>		'required|string',
			'family.*.email' =>		'required|email',
			'family.*.auto_add' =>	'boolean',
		]);

		$user = $request->user();
		$sync = [];

		foreach ($request->family as $entry) {
			$member = User::firstOrCreate(
				[	'email' =>				$entry['email']],
				[
					'name' =>				$entry['name'],
					'password' =>			null,
					'subscription_type' =>	'family',
				]
			);

			if ($member->id === $user->id) {
				continue;
			}

			$sync[$member->id] = ['auto_add' => (bool) ($entry['auto_add'] ?? false)];
		}

		$user->family()->sync($sync);

		return FamilyMemberResource::collection($user->family()->get());
	}
}
