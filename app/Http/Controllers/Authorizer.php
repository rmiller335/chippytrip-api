<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

// =============================================================================
class Authorizer extends Controller {
	// =========================================================================
	public function checkToken(Request $request) {
	}

	// =========================================================================
	public function genToken(Request $request) {
		$request->validate([
			'email' => 'required|email',
			'password' => 'required',
			'device_name' => 'required|string|max:255',
		]);

		$user = User::where('email', $request->email)->first();

		if (! $user || ! Hash::check($request->password, $user->password)) {
			throw ValidationException::withMessages([
				'email' => ['The provided credentials are incorrect.'],
			]);
		}

		// One token per device (device_name is the app's device ID):
		// signing in again replaces that device's token.
		return DB::transaction(function () use ($user, $request) {
			$user->tokens()->where('name', $request->device_name)->delete();

			return $user->createToken($request->device_name)->plainTextToken;
		});
	}
}
