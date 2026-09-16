<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use Illuminate\Http\Request;

// =============================================================================
class AirlineController extends Controller {
	// =========================================================================
	public function search(Request $request) {
		$request->validate([
			'q' => 'required|string|min:1',
		]);

		$q = trim($request->query('q'));

		$airlines = Airline::query()
			->where(function ($query) use ($q) {
				$query->where('iata', 'like', "{$q}%")
					->orWhere('icao', 'like', "{$q}%")
					->orWhere('name', 'like', "%{$q}%");
			})
			->orderByRaw('
				CASE
					WHEN iata = ? THEN 0
					WHEN icao = ? THEN 0
					WHEN iata LIKE ? THEN 1
					WHEN icao LIKE ? THEN 1
					WHEN name LIKE ? THEN 2
					ELSE 3
				END
			', [$q, $q, "{$q}%", "{$q}%", "{$q}%"])
			->orderBy('name')
			->limit(15)
			->get(['icao', 'iata', 'name']);

		return response()->json($airlines);
	}
}
