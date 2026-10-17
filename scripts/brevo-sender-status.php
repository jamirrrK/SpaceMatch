<?php

require __DIR__.'/gmail-otp-support.php';
gmail_bootstrap();
$report = ['checked_at' => gmdate(DATE_ATOM), 'sender' => config('mail.from.address'), 'provider_approval_confirmed' => false, 'source' => 'unavailable'];
try {
    // An SMTP key is never sent to the REST API. Use an already configured
    // separate API key only; do not request credentials through chat.
    $apiKey = env('BREVO_API_KEY');
    if (is_string($apiKey) && $apiKey !== '') {
        $response = \Illuminate\Support\Facades\Http::withHeaders(['api-key' => $apiKey])->acceptJson()->timeout(15)->get('https://api.brevo.com/v3/senders');
        unset($apiKey);
        if ($response->successful()) {
            $sender = collect($response->json('senders', []))->first(fn ($entry) => strtolower($entry['email'] ?? '') === strtolower((string) config('mail.from.address')));
            $report['source'] = 'Brevo sender API';
            $report['sender_listed'] = $sender !== null;
            $report['provider_approval_confirmed'] = ($sender['active'] ?? false) === true;
        } else {
            $report['blocked_reason'] = 'Provider sender lookup failed; response content suppressed.';
        }
    } else {
        $confirmationPath = storage_path('app/private/brevo-sender-confirmation.json');
        $confirmation = is_file($confirmationPath) ? json_decode(file_get_contents($confirmationPath), true) : [];
        if (($confirmation['verified_and_transactional_approved'] ?? false) === true
            && strtolower($confirmation['sender'] ?? '') === strtolower((string) config('mail.from.address'))) {
            $report['provider_approval_confirmed'] = true;
            $report['source'] = 'User confirmation of Brevo dashboard';
            $report['independently_checked_with_provider_api'] = false;
            $report['recorded_at'] = $confirmation['recorded_at'];
        } else {
            $report['blocked_reason'] = 'No connected Brevo plugin or separate local API credential is available. Confirm the exact sender status in Brevo account settings; SMTP authentication alone is not sender approval.';
        }
    }
} catch (\Throwable) {
    $report['blocked_reason'] = 'Provider sender lookup could not complete. No secret-bearing exception was displayed.';
}
gmail_safe_json($report, __DIR__.'/../docs/brevo-sender-status.json');
