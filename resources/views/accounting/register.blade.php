@extends('layout')

@section('title', 'To\'lov reestri')

@section('content')
    <h1>To'lov reestri</h1>
    <p class="sub">To'lov o'chirilmaydi: xato yozuv sabab bilan bekor qilinadi va shu yerda saqlanadi.</p>
    <div class="panel">
        <h2>Faol to'lovlar</h2>
        @include('partials.payments-table', ['payments' => $activePayments, 'voided' => false, 'showContract' => true])
    </div>
    <div class="panel">
        <h2>Bekor qilingan to'lovlar</h2>
        @include('partials.payments-table', ['payments' => $voidedPayments, 'voided' => true, 'showContract' => true])
    </div>
@endsection
