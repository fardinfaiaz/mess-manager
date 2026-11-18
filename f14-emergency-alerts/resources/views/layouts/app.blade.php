<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mess-Manager')</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #222; }
        nav { background: #1f3b57; color: #fff; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        nav a, nav button { color: #fff; background: none; border: none; font-size: 15px; cursor: pointer; margin-left: 14px; text-decoration: none; }
        main { max-width: 720px; margin: 40px auto; background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        label { display: block; margin-top: 14px; font-weight: bold; }
        input[type=text], input[type=email], input[type=password], input[type=number], select, textarea { width: 100%; padding: 9px; margin-top: 4px; box-sizing: border-box; border: 1px solid #bbb; border-radius: 4px; }
        .btn { margin-top: 18px; width: 100%; padding: 10px; background: #1f3b57; color: #fff; border: none; border-radius: 4px; font-size: 15px; cursor: pointer; }
        .error { color: #b00020; font-size: 14px; margin-top: 4px; }
        a.btn { display: block; text-align: center; text-decoration: none; }
        .flash { padding: 10px; border-radius: 4px; margin-bottom: 16px; }
        .flash-success { background: #e5f4e4; color: #1b5e20; }
        .flash-error { background: #fbebeb; color: #b00020; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 14px; margin-top: 16px; }
        .alert-banner { background: #fbebeb; border: 1px solid #b00020; color: #b00020; padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
        .btn-sm { width: auto; padding: 5px 12px; margin-top: 6px; font-size: 13px; }
    </style>
</head>
<body>
    <nav>
        <strong>Mess-Manager</strong>
        <div>
            @auth
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('register') }}">Register</a>
            @endauth
        </div>
    </nav>
    <main>
	@if (session('success')) <div class="flash flash-success">{{ session('success') }}</div> @endif
	@if (session('error')) <div class="flash flash-error">{{ session('error') }}</div> @endif
        @if (! empty($bannerAlerts) && $bannerAlerts->isNotEmpty() && ! request()->routeIs('alerts.*'))
            @foreach ($bannerAlerts as $alert)
                <div class="alert-banner">
                    <strong>Emergency ({{ ucfirst($alert->type) }}):</strong> {{ $alert->message }}
                    <a href="{{ route('alerts.index') }}">Details</a>
                </div>
            @endforeach
        @endif
        @yield('content')
    </main>
</body>
</html>