@extends('layout')

@section('title', 'Kirish')

@section('content')
    <div class="panel login">
        <h1>Kirish</h1>
        <p class="sub">Demo foydalanuvchilar README da.</p>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Parol</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button>Kirish</button>
        </form>
    </div>
@endsection
