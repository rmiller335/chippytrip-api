<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use Illuminate\Http\Request;

// =============================================================================
class AirportController extends Controller {
	// =========================================================================
	public function search(Request $request) {
		$request->validate([
			'q' => 'required|string|min:1',
		]);

		$q = trim($request->query('q'));

		$airports = Airport::query()
			->where(function ($query) use ($q) {
				$query->where('iata', 'like', "{$q}%")
					->orWhere('icao', 'like', "{$q}%")
					->orWhere('name', 'like', "%{$q}%")
					->orWhere('city', 'like', "%{$q}%");
			})
			->orderByRaw('
				CASE
					WHEN iata = ? THEN 0
					WHEN icao = ? THEN 0
					WHEN iata LIKE ? THEN 1
					WHEN icao LIKE ? THEN 1
					WHEN name LIKE ? THEN 2
					WHEN city LIKE ? THEN 2
					ELSE 3
				END
			', [$q, $q, "{$q}%", "{$q}%", "{$q}%", "{$q}%"])
			->orderBy('name')
			->limit(15)
			->get(['icao', 'iata', 'name', 'city']);

		return response()->json($airports);
	}
}
