<?php

namespace App\Http\Controllers;

use App\Http\Resources\WatchListenerResource;
use App\Models\Watch;
use Illuminate\Http\Request;

// =============================================================================
class WatchListenerController extends Controller {
	// =========================================================================
	// Family-member listeners the authenticated user has added to this watch.
	// The user's own listener row is implicitly excluded.
	public function index(Request $request, Watch $watch) {
		$user = $request->user();

		abort_unless($watch->listeners()->where('user_id', $user->id)->exists(), 403);

		$listeners = $watch->listeners()
			->whereIn('user_id', $user->family->pluck('id'))
			->with('user')
			->get()
		;

		return WatchListenerResource::collection($listeners);
	}

	// =========================================================================
	// Replaces the authenticated user's family-member listeners on this watch.
	// Only touches listener rows belonging to the user's own family members —
	// the user's own listener row and any other users' listeners (e.g. from
	// an unrelated user who happens to watch the same flight) are untouched.
	public function update(Request $request, Watch $watch) {
		$user = $request->user();

		abort_unless($watch->listeners()->where('user_id', $user->id)->exists(), 403);

		$family = $user->family->keyBy('email');

		$request->validate([
			'listeners' =>			'present|array',
			'listeners.*.email' =>	[
				'required',
				'email',
				function ($attribute, $value, $fail) use ($family) {
					if (! $family->has($value)) {
						$fail('The selected email is not one of your family members.');
					}
				},
			],
		]);

		$targetUsers = collect($request->listeners)
			->pluck('email')
			->unique()
			->map(fn ($email) => $family[$email])
		;

		$familyIds = $family->pluck('id');
		$targetIds = $targetUsers->pluck('id');

		$watch->listeners()
			->whereIn('user_id', $familyIds)
			->whereNotIn('user_id', $targetIds)
			->delete()
		;

		$targetUsers->each(fn ($member) => $watch->listeners()->firstOrCreate(
			['user_id' => $member->id],
			['travelers' => $member->name],
		));

		$listeners = $watch->listeners()
			->whereIn('user_id', $familyIds)
			->with('user')
			->get()
		;

		return WatchListenerResource::collection($listeners);
	}
}
