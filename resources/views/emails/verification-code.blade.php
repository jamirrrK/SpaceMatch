<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your SpaceMatch Verification Code</title></head>
<body style="margin:0;background:#faf9f6;color:#0f172a;font-family:Arial,sans-serif;line-height:1.6;padding:32px 16px">
    <main style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px">
        <div style="display:inline-block;background:#172033;color:#fff;border-radius:16px;font-weight:700;padding:9px 13px">S</div>
        <p style="margin:24px 0 8px;color:#0d766e;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase">SpaceMatch account security</p>
        <h1 style="margin:0 0 16px;color:#172033;font-size:24px">Your SpaceMatch Verification Code</h1>
        @if($purpose === 'registration')
            <p>Welcome to SpaceMatch!</p>
            <p>Use the following verification code to complete your account registration:</p>
        @else
            <p>Use the following verification code to verify the new email address on your SpaceMatch account:</p>
        @endif
        <p style="margin:24px 0;padding:18px;border:1px solid #e2e8f0;border-radius:6px;background:#faf9f6;text-align:center;color:#172033;font-size:32px;font-weight:700">{{ $code }}</p>
        <p>This code expires in 5 minutes.</p>
        <p>If you did not request this code, you can safely ignore this message.</p>
        <p style="margin:28px 0 0;border-top:1px solid #e2e8f0;padding-top:18px;color:#67788a;font-size:13px">SpaceMatch - Find Your Space, Anywhere in the Philippines.</p>
    </main>
</body>
</html>