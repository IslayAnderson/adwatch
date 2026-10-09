@extends('layouts.app', ['title' => 'Log in'])
@section('content')
<form method="POST" action="{{ route('login') }}" class="max-w-sm mx-auto bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    @csrf
    <h1 class="text-xl font-semibold">Log in</h1>
    <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" class="w-full border rounded px-3 py-2" required>
    <input name="password" type="password" placeholder="Password" class="w-full border rounded px-3 py-2" required>
    <button class="w-full bg-emerald-600 text-white rounded py-2 font-semibold">Log in</button>
    <p class="text-xs text-slate-500">Demo accounts are listed in the project README.</p>
</form>
@endsection
