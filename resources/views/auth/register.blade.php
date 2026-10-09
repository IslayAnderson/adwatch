@extends('layouts.app', ['title' => 'Sign up'])
@section('content')
<form method="POST" action="{{ route('register') }}" class="max-w-sm mx-auto bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    @csrf
    <h1 class="text-xl font-semibold">Create an account</h1>
    <input name="name" value="{{ old('name') }}" placeholder="Name" class="w-full border rounded px-3 py-2" required>
    <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" class="w-full border rounded px-3 py-2" required>
    <input name="password" type="password" placeholder="Password (8+ chars)" class="w-full border rounded px-3 py-2" required>
    <input name="password_confirmation" type="password" placeholder="Confirm password" class="w-full border rounded px-3 py-2" required>
    <button class="w-full bg-emerald-600 text-white rounded py-2 font-semibold">Sign up</button>
</form>
@endsection
