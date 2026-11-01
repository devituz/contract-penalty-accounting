<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Shartnomalar') · Yetkazib berish</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@auth
    <header class="top">
        <div class="inner">
            <strong>Yetkazib berish</strong>
            <nav>
                <a href="{{ route('contracts.index') }}" @class(['current' => request()->routeIs('contracts.*')])>Shartnomalar</a>
                @can('viewAccounting', App\Models\Contract::class)
                    <a href="{{ route('accounting.index') }}" @class(['current' => request()->routeIs('accounting.index', 'accounting.show')])>Buxgalteriya</a>
                    <a href="{{ route('accounting.register') }}" @class(['current' => request()->routeIs('accounting.register')])>Reestr</a>
                    <a href="{{ route('budget.index') }}" @class(['current' => request()->routeIs('budget.*')])>Smeta</a>
                @endcan
            </nav>
            <span class="who">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
            <form method="post" action="{{ route('logout') }}" class="inline">
                @csrf
                <button class="ghost">Chiqish</button>
            </form>
        </div>
    </header>
@endauth
<main>
    @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
