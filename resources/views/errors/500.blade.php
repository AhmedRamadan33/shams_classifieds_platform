<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('app.errors.500.title') }} | {{ __('app.brand') }}</title>
    <style>
        body { margin: 0; font-family: Tajawal, system-ui, sans-serif; background: #f8fafc; color: #1e293b; }
        main { max-width: 32rem; margin: 0 auto; padding: 6rem 1rem; text-align: center; }
        .code { font-size: 4rem; font-weight: 700; color: #ea580c; direction: ltr; }
        a { display: inline-block; margin-top: 1.5rem; padding: .75rem 1.5rem; background: #c2410c; color: #fff; border-radius: .5rem; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <p class="code">500</p>
        <h1>{{ __('app.errors.500.title') }}</h1>
        <p>{{ __('app.errors.500.message') }}</p>
        <a href="/">{{ __('app.errors.back_home') }}</a>
    </main>
</body>
</html>
