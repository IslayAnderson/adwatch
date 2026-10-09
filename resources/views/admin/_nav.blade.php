<nav class="flex gap-1 mb-6 border-b border-slate-200 text-sm">
    @foreach (['admin.index' => 'Overview', 'admin.users.index' => 'Users'] as $route => $label)
        @php $on = request()->routeIs($route) || ($route === 'admin.users.index' && request()->routeIs('admin.users.*')); @endphp
        <a href="{{ route($route) }}" class="px-4 py-2 -mb-px border-b-2 {{ $on ? 'border-emerald-600 text-emerald-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
    @endforeach
</nav>
