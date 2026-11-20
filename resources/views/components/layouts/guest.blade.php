@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Masuk' }} · Manajemen Rapat</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo-joglo-quran-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#592635">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 p-4 font-sans antialiased">
    {{ $slot }}
</body>
</html>
