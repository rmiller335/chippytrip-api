<?php

namespace App\Http\Controllers;

use App\Jobs\ParseConfirmationEmail;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

// =============================================================================
class PostmarkInboundController extends Controller {
	// =========================================================================
	public function handle(Request $request): Response {
		$data = $request->validate([
			'FromFull.Email' => ['required', 'email'],
			'Subject'		 => ['nullable', 'string'],
			'TextBody'		 => ['nullable', 'string'],
			'HtmlBody'		 => ['nullable', 'string'],
			'MessageID'		 => ['nullable', 'string'],
		]);

		$fromAddress = strtolower($data['FromFull']['Email']);

		$user = User::whereRaw(
			'LOWER(email) = ?',
			[$fromAddress]
		)->first();

		$email = InboundEmail::firstOrCreate( [
				'message_id' => $data['MessageID'] ?? null,
			], [
				'user_id'	   => $user?->id,
				'from_address' => $fromAddress,
				'subject'	   => $data['Subject'] ?? null,
				'text_body'    => $data['TextBody'] ?? null,
				'html_body'    => $data['HtmlBody'] ?? null,
				'status'	   => $user ? 'pending' : 'unknown_sender',
			]
		);

		if($user) {
			ParseConfirmationEmail::dispatch($email->id);
		}

		return response('OK', 200);
	}
}
