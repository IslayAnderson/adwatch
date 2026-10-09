{{--
    Google tag (gtag.js), working alongside the server-side Measurement Protocol (App\Services\Analytics):
    - page_view is sent by the server, so the tag doesn't send its own (send_page_view: false);
    - it reuses the server's client id, so browser and server hits are one visitor;
    - Consent Mode v2: everything denied until the visitor accepts the banner below.
--}}
@php
    $ga = app(\App\Services\Analytics::class);
    $gaId = config('services.google_analytics.measurement_id');
    $gaConsented = $ga->consented();
    $gaAsked = ! config('services.google_analytics.require_consent') || request()->hasCookie(\App\Services\Analytics::CONSENT_COOKIE);
@endphp
@if ($gaId)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {
        analytics_storage: @json($gaConsented ? 'granted' : 'denied'),
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    gtag('js', new Date());
    gtag('config', @json($gaId), {
        send_page_view: false,
        client_id: @json($ga->clientId(request())),
        @auth user_id: @json((string) auth()->id()), @endauth
    });
</script>

@unless ($gaAsked)
<div id="cookieBanner" class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4">
    <div class="max-w-3xl mx-auto bg-slate-900 text-slate-100 rounded-xl shadow-2xl p-4 flex flex-col sm:flex-row sm:items-center gap-3 text-sm">
        <p class="flex-1">We use Google Analytics cookies to see how AdWatch is used. Nothing is used for advertising. OK?</p>
        <div class="flex gap-2 shrink-0">
            <button type="button" data-consent="denied" class="px-4 py-2 rounded-lg border border-slate-600 hover:bg-slate-800">Reject</button>
            <button type="button" data-consent="granted" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 font-semibold">Accept</button>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('#cookieBanner [data-consent]').forEach((button) => button.addEventListener('click', () => {
        const choice = button.dataset.consent;
        document.cookie = `{{ \App\Services\Analytics::CONSENT_COOKIE }}=${choice}; max-age=${60 * 60 * 24 * 365}; path=/; SameSite=Lax`;
        gtag('consent', 'update', { analytics_storage: choice });
        document.getElementById('cookieBanner').remove();
    }));
</script>
@endunless
@endif
