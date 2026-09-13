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
        <p>{{ ($passwordMode ?? false) ? 'Enter your username and password.' : 'Enter your mobile number with its country code.' }}</p>

        @isset($error)
            <p role="alert">{{ $error }}</p>
        @endisset

        @if ($passwordMode ?? false)
        <form method="post" action="{{ route('auth.mobile.password.store') }}">
            @csrf
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required maxlength="64">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="512">
            <button type="submit">Sign in</button>
        </form>
        @else
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
        @endif
    </main>
</body>
</html>
