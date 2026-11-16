<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | Caraka</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f5; margin: 0; display: grid; min-height: 100vh; place-items: center; }
        main { background: #fff; padding: 24px; border-radius: 8px; width: 100%; max-width: 360px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        h1 { margin: 0 0 4px; }
        p { margin: 0 0 16px; color: #555; }
        label { display: block; margin: 12px 0 4px; font-size: 14px; }
        input[type=email], input[type=password] { width: 100%; padding: 10px; box-sizing: border-box; font-size: 16px; }
        .check { display: flex; gap: 8px; align-items: center; }
        button { margin-top: 16px; width: 100%; padding: 12px; font-size: 16px; cursor: pointer; }
        .error { background: #fee2e2; color: #991b1b; padding: 8px 10px; border-radius: 4px; margin-bottom: 8px; font-size: 14px; }
    </style>
@include('partials.head-tema')
</head>
<body class="caraka">
@include('partials.navbar-warga')
    <main>
        <h1>Caraka</h1>
        <p>Masuk untuk staf (dispatcher, responder, admin)</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">Kata sandi</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <label class="check"><input type="checkbox" name="remember"> Ingat saya</label>

            <button type="submit">Masuk</button>
        </form>
    </main>
@include('partials.versi')
</body>
</html>
