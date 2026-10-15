<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Keluar</title>
</head>
<body style="font-family: sans-serif; padding: 24px;">
    <p>Masuk sebagai: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->role }})</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</body>
</html>
