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

		// Only registered users' emails are kept and parsed. Anything else is
		// dropped, but still gets a 200 so Postmark doesn't retry it.
		if (null === $user) {
			Log::info('PostmarkInboundController: discarded email from unknown sender', [
				'from' => $fromAddress,
			]);

			return response('OK', 200);
		}

		$fields = [
			'user_id'	   => $user->id,
			'from_address' => $fromAddress,
			'subject'	   => $data['Subject'] ?? null,
			'text_body'    => $data['TextBody'] ?? null,
			'html_body'    => $data['HtmlBody'] ?? null,
			'status'	   => 'pending',
		];

		// A Postmark retry repeats the MessageID; store and parse it once.
		$email = empty($data['MessageID'])
			? InboundEmail::create($fields)
			: InboundEmail::firstOrCreate(['message_id' => $data['MessageID']], $fields);

		if ($email->wasRecentlyCreated) {
			ParseConfirmationEmail::dispatch($email->id);
		}

		return response('OK', 200);
	}
}
