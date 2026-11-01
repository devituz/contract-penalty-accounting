@extends('layout')

@section('title', 'Ruxsat yo\'q')

@section('content')
    <div class="panel">
        <h1>Ruxsat yo'q</h1>
        <p>Bu sahifaga ruxsatingiz yo'q yoki bu amal sizning rolingiz uchun emas.</p>
        <a href="{{ route('contracts.index') }}">Shartnomalarga qaytish</a>
    </div>
@endsection
