<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Verify sign-in to {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>Enter the verification code</h1>
        <p>Enter the six-digit code sent for this sign-in attempt.</p>

        @isset($error)
            <p role="alert">{{ $error }}</p>
        @endisset

        <form method="post" action="{{ route('auth.mobile.otp.verifications.store') }}">
            @csrf
            <label for="code">Verification code</label>
            <input
                id="code"
                name="code"
                type="password"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                minlength="6"
                maxlength="6"
                pattern="[0-9]{6}"
            >
            <button type="submit">Continue</button>
        </form>
    </main>
</body>
</html>
