<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sign in to {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>Sign in to Global English</h1>
        <p>Enter your mobile number with its country code.</p>

        @isset($error)
            <p role="alert">{{ $error }}</p>
        @endisset

        <form method="post" action="{{ route('auth.mobile.otp.requests.store') }}">
            @csrf
            <label for="mobile_number">Mobile number</label>
            <input
                id="mobile_number"
                name="mobile_number"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                required
                maxlength="32"
            >
            <label for="institution_invite_code">
                Institution invite code (optional)
            </label>
            <input
                id="institution_invite_code"
                name="institution_invite_code"
                type="password"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                minlength="12"
                maxlength="128"
            >
            <button type="submit">Send verification code</button>
        </form>
    </main>
</body>
</html>
