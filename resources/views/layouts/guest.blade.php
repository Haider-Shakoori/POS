<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('pos.locales.'.app()->getLocale().'.direction', 'ltr') }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('ui.sign_in')) · {{ config('app.name') }}</title>
    <script>
        if (localStorage.getItem('pos-theme') === 'dark') document.documentElement.classList.add('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 dark:bg-slate-950">
    @yield('content')
</body>
</html>
