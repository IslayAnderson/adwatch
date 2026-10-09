@extends('layouts.app', ['title' => 'Watching: '.$view->ad->advertiser])
@use('App\Support\Money')
@section('content')
<div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
    <div class="min-w-0">
        <div class="text-xs text-slate-500">{{ $view->ad->category->name }} · billed at ${{ number_format($payout['cpm_cents'] / 100, 2) }} CPM</div>
        <h1 class="text-xl font-bold truncate">{{ $view->ad->advertiser }} — {{ $view->ad->title }}</h1>
    </div>
    <div class="text-right">
        <div class="text-emerald-600 font-bold text-lg">+{{ Money::precise($payout['user_micros']) }}</div>
        <div class="text-xs text-slate-400">gross {{ Money::precise($payout['gross_micros']) }}</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 min-w-0">
        <div class="aspect-video bg-black rounded-xl overflow-hidden relative">
            <div id="player" class="w-full h-full"></div>
            <div id="fallback" class="hidden absolute inset-0 flex-col items-center justify-center text-white text-center p-6">
                <img src="{{ $view->ad->thumbnailUrl() }}" class="absolute inset-0 w-full h-full object-cover opacity-30" alt="">
                <div class="relative">
                    <p class="font-semibold">The video owner blocks embedding.</p>
                    <p class="text-sm opacity-80">Simulated playback is running instead — keep this tab visible.</p>
                </div>
            </div>
            {{-- Covers the player (and swallows clicks) until the webcam is on. --}}
            <div id="camGate" class="absolute inset-0 z-10 bg-slate-950/90 flex flex-col items-center justify-center text-center text-white p-4">
                <div class="text-2xl sm:text-4xl sm:mb-3">🔒</div>
                <p class="font-semibold sm:text-lg">Turn on your webcam to play this ad</p>
                <p class="hidden sm:block text-sm text-slate-300 mt-1 max-w-sm">Ads only play while we can confirm you're watching.</p>
                <button type="button" onclick="Webcam.enable()" class="mt-3 sm:mt-4 bg-emerald-600 hover:bg-emerald-500 px-5 py-2 rounded-lg font-semibold">Enable webcam</button>
            </div>
        </div>

        <div class="mt-4">
            <div class="flex justify-between text-sm mb-1">
                <span id="label">Turn on your webcam to start</span>
                <span><span id="secs">0</span> / {{ $view->required_seconds }}s</span>
            </div>
            <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                <div id="bar" class="h-full bg-emerald-500 transition-all" style="width:0%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-2">Only real playback counts: pausing, seeking ahead, switching tabs or turning off the webcam stops the timer. The server also checks elapsed time.</p>
        </div>

        <form id="claim" method="POST" action="{{ route('watch.complete', $view) }}" class="mt-6 flex flex-wrap items-center gap-4">
            @csrf
            <input type="hidden" name="watched_seconds" id="watched" value="0">
            <button id="claimBtn" disabled class="bg-emerald-600 text-white px-6 py-3 rounded-lg font-semibold disabled:bg-slate-300 disabled:cursor-not-allowed">
                Claim {{ Money::precise($payout['user_micros']) }} &amp; next ad →
            </button>
            <a href="{{ route('ads.index') }}" class="text-sm text-slate-500">Skip ad (no payout)</a>
        </form>
    </div>

    <aside class="min-w-0">
        @include('partials.webcam')
    </aside>
</div>

<script>
const REQUIRED = {{ $view->required_seconds }};
let watched = 0, lastTick = null, player, simulated = false;
const $ = (id) => document.getElementById(id);
const camOn = () => Webcam.on();

Webcam.onChange((on) => {
    $('camGate').classList.toggle('hidden', on);
    if (on && player && player.playVideo) player.playVideo();
    if (!on && player && player.pauseVideo) player.pauseVideo();
    if (!on && watched < REQUIRED) $('label').textContent = watched > 0 ? 'Webcam off — turn it back on to continue' : 'Turn on your webcam to start';
    else if (on && watched === 0) $('label').textContent = 'Press play to start';
});

function render() {
    const s = Math.min(Math.floor(watched), REQUIRED);
    $('secs').textContent = s;
    $('bar').style.width = (s / REQUIRED * 100) + '%';
    $('watched').value = Math.floor(watched);
    if (watched >= REQUIRED) {
        $('claimBtn').disabled = false;
        $('label').textContent = 'Qualified! Claim your earnings.';
    }
}

function isPlaying() {
    if (document.hidden || !camOn()) return false;
    if (simulated) return true;
    return player && player.getPlayerState && player.getPlayerState() === YT.PlayerState.PLAYING;
}

// Real player: count advances of the video position (ignores buffering; seeks jump > 1.5s and are dropped).
// Simulated: count wall-clock time while the tab is visible.
function position() {
    return simulated ? performance.now() / 1000 : player.getCurrentTime();
}

setInterval(() => {
    if (!simulated && player && player.getPlayerState && player.getPlayerState() === YT.PlayerState.ENDED && watched < REQUIRED) {
        if (watched >= player.getDuration() - 2) {
            watched = REQUIRED; // watched the whole ad, which is shorter than the threshold
        } else {
            // Ended before it qualified (the timer lost time to buffering or pauses): play it again,
            // and keep counting from where the timer got to.
            player.seekTo(0, true);
            player.playVideo();
            $('label').textContent = 'Replaying — keep watching to qualify';
        }
    }
    if (isPlaying()) {
        const now = position();
        if (lastTick !== null) {
            const delta = now - lastTick;
            if (delta > 0 && delta < 1.5) watched += delta;
        }
        lastTick = now;
        if (watched < REQUIRED) $('label').textContent = simulated ? 'Simulated playback…' : 'Watching…';
    } else {
        lastTick = null;
        if (camOn() && watched > 0 && watched < REQUIRED) $('label').textContent = 'Paused — timer stopped';
    }
    render();
}, 250);

function startSimulation() {
    simulated = true;
    $('player').classList.add('hidden');
    const fb = $('fallback');
    fb.classList.remove('hidden'); fb.classList.add('flex');
}

window.onYouTubeIframeAPIReady = function () {
    player = new YT.Player('player', {
        videoId: @json($view->ad->youtube_id),
        width: '100%', height: '100%',
        playerVars: { controls: 1, disablekb: 1, rel: 0, modestbranding: 1, playsinline: 1 },
        events: {
            // Refuse playback without the webcam, even if started via keyboard or the YouTube UI.
            onStateChange: (e) => { if (e.data === YT.PlayerState.PLAYING && !camOn()) player.pauseVideo(); },
            // 101/150 = embedding disabled by owner; 100 = removed. Fall back to a simulated timer.
            onError: (e) => { if ([100, 101, 150].includes(e.data)) startSimulation(); },
        },
    });
};
</script>
<script src="https://www.youtube.com/iframe_api"></script>
@endsection
