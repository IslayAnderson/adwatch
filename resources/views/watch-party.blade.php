@extends('layouts.app', ['title' => 'Watch party'])
@use('App\Support\Money')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">🎉 Watch party</h1>
        <p class="text-sm text-slate-500">Sit back. We play random ads back to back and bank each one automatically.</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-sm">
        <div class="bg-white border border-slate-200 rounded-lg px-3 py-2"><div class="text-xs text-slate-500">Ads this party</div><div id="statAds" class="font-bold">0</div></div>
        <div class="bg-white border border-slate-200 rounded-lg px-3 py-2"><div class="text-xs text-slate-500">Earned this party</div><div id="statEarned" class="font-bold text-emerald-600">$0.00000</div></div>
        <div class="bg-white border border-slate-200 rounded-lg px-3 py-2"><div class="text-xs text-slate-500">In escrow</div><div id="statEscrow" class="font-bold text-amber-600">{{ Money::usd($wallet->escrowMicros(), 3) }}</div></div>
        <div class="bg-white border border-slate-200 rounded-lg px-3 py-2"><div class="text-xs text-slate-500">Available</div><div id="statAvailable" class="font-bold">{{ Money::usdFloor($wallet->availableMicros()) }}</div></div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 min-w-0">
        <div class="aspect-video bg-black rounded-xl overflow-hidden relative">
            <div id="player" class="w-full h-full"></div>
            {{-- Blocks clicks so the automated player can't be paused or scrubbed. --}}
            <div class="absolute inset-0"></div>

            <div id="startScreen" class="absolute inset-0 z-10 bg-gradient-to-br from-emerald-700 to-slate-900 flex flex-col items-center justify-center text-center text-white p-4">
                <div class="text-4xl sm:text-5xl mb-2">🍿</div>
                <p class="font-bold text-lg sm:text-xl">Ready to party?</p>
                <p class="hidden sm:block text-sm text-emerald-100 mt-1 max-w-sm">Random ads from every category, claimed for you as soon as each one qualifies. Webcam required.</p>
                <button id="startBtn" type="button" disabled class="mt-4 bg-white text-emerald-700 hover:bg-emerald-50 px-6 py-2.5 rounded-lg font-bold disabled:opacity-50">Loading player…</button>
                <label class="mt-3 flex items-center gap-2 text-sm text-emerald-50 cursor-pointer select-none">
                    <input id="britishOnly" type="checkbox" class="w-4 h-4 accent-white" @checked(request('region') === 'GB')> 🇬🇧 British ads only
                </label>
            </div>

            <div id="camGate" class="hidden absolute inset-0 z-20 bg-slate-950/90 flex flex-col items-center justify-center text-center text-white p-4">
                <div class="text-2xl sm:text-4xl sm:mb-3">🔒</div>
                <p class="font-semibold sm:text-lg">Webcam off — party paused</p>
                <button type="button" onclick="Webcam.enable()" class="mt-3 bg-emerald-600 hover:bg-emerald-500 px-5 py-2 rounded-lg font-semibold">Turn webcam back on</button>
            </div>

            {{-- Shared prompt for the fake attention checks; content is filled from ALERTS below. --}}
            <div id="alertBox" class="hidden absolute inset-0 z-20 bg-red-950/85 flex flex-col items-center justify-center text-center text-white p-4">
                <div id="alertIcon" class="text-3xl sm:text-5xl mb-1 sm:mb-2 animate-bounce"></div>
                <p id="alertTitle" class="font-bold text-lg sm:text-2xl"></p>
                <p id="alertBody" class="text-xs sm:text-sm text-red-100 mt-1 max-w-sm"></p>
                {{-- Live meter for the "yell" (loudness) and "smile" checks; the line marks the pass mark. --}}
                <div id="alertMeter" class="hidden w-full max-w-xs mt-3">
                    <div class="relative h-4 bg-white/20 rounded-full overflow-hidden">
                        <div id="meterFill" class="h-full bg-amber-400" style="width:0%"></div>
                        <div class="absolute inset-y-0 w-0.5 bg-white" style="left:80%"></div>
                    </div>
                    <p id="meterStatus" class="text-xs text-red-100 mt-1"></p>
                    <button id="meterTap" type="button" class="hidden mt-2 bg-white text-red-700 px-4 py-1.5 rounded-lg text-sm font-bold">🎤 Tap to start listening</button>
                </div>
                <button id="continueBtn" type="button" class="mt-3 sm:mt-4 bg-white text-red-700 hover:bg-red-50 px-6 py-2.5 rounded-lg font-bold disabled:opacity-70 disabled:cursor-wait"></button>
            </div>

            <div id="upNext" class="hidden absolute inset-0 z-20 bg-slate-950/85 flex flex-col items-center justify-center text-center text-white p-4">
                <div id="upNextEarned" class="text-emerald-400 font-bold text-2xl sm:text-3xl"></div>
                <p class="text-sm text-slate-300 mt-1">banked to escrow</p>
                <p class="mt-4 text-sm">Next ad in <span id="upNextCount" class="font-bold">3</span>…</p>
            </div>

            <div id="endScreen" class="hidden absolute inset-0 z-30 bg-slate-950/95 flex flex-col items-center justify-center text-center text-white p-4">
                <div class="text-4xl mb-2">🏁</div>
                <p class="font-bold text-lg">Party's over</p>
                <p id="endMessage" class="text-sm text-slate-300 mt-1 max-w-sm"></p>
                <a href="{{ route('wallet') }}" class="mt-4 bg-emerald-600 px-5 py-2 rounded-lg font-semibold">View wallet</a>
            </div>
        </div>

        <div class="mt-4 bg-white border border-slate-200 rounded-xl p-4">
            <div class="flex flex-wrap justify-between gap-2">
                <div class="min-w-0">
                    <div id="nowMeta" class="text-xs text-slate-500">Nothing playing yet</div>
                    <div id="nowTitle" class="font-semibold truncate">—</div>
                </div>
                <div id="nowPayout" class="text-emerald-600 font-bold"></div>
            </div>
            <div class="flex justify-between text-sm mt-3 mb-1">
                <span id="label">Press start</span>
                <span><span id="secs">0</span> / <span id="req">30</span>s</span>
            </div>
            <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                <div id="bar" class="h-full bg-emerald-500 transition-all" style="width:0%"></div>
            </div>
        </div>

        <h2 class="font-semibold mt-6 mb-2">This party</h2>
        <ul id="log" class="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 text-sm">
            <li class="p-3 text-slate-400">Claimed ads will show up here.</li>
        </ul>
    </div>

    <aside class="min-w-0">
        @include('partials.webcam')
    </aside>
</div>

<script>
const NEXT_URL = @json(route('watch-party.next'));
const ALERT_URL = @json(route('watch-party.alert'));
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const $ = (id) => document.getElementById(id);
const show = (id, on) => { $(id).classList.toggle('hidden', !on); $(id).classList.toggle('flex', on); };

let player, current = null, watched = 0, lastTick = null;
let started = false, alerting = false, busy = false, checks = [];

// Fake attention checks. Each ad independently rolls for each one at a random point in the ad.
const ALERTS = {
    lookaway: { chance: 0.5, icon: '👀', title: "You're not looking at the screen!", body: "Ads only pay while you're watching. The timer is paused.", button: "I'm watching — continue", label: 'Paused — look at the screen' },
    competitor: { chance: 0.2, icon: '🚫', title: 'Competitor product detected', body: (ad) => `We spotted ${ad.competitor} in your webcam feed during a ${ad.ad.advertiser} ad. Remove it from view to continue watching.`, button: "I've removed it", rescan: true, label: 'Paused — competitor product in view' },
    smile: { chance: 0.15, icon: '😠', title: 'Negative emotions detected', body: 'Please smile to restart the ads. Our brow micro-furrow AI needs to see you enjoying them. Your face is analysed in this browser and never leaves it.', button: "I'm smiling — continue", smile: true, label: 'Paused — waiting for a smile' },
    shout: { chance: 0.15, icon: '📣', title: (ad) => `Yell "${ad.ad.advertiser.toUpperCase()}!" at your screen`, body: 'Shout the brand name to keep watching. We only measure how loud it is: nothing is recorded or sent anywhere.', button: 'I yelled it — continue', shout: true, label: 'Paused — waiting for you to yell' },
    viewer: { chance: 0.3, icon: '👥', title: 'Undeclared viewer detected', body: 'Someone else appears to be watching. Each ad view is for the account holder only. The timer is paused.', button: "It's just me — continue", label: 'Paused — undeclared viewer' },
};

// ?alert=lookaway,viewer forces those checks on every ad, a few seconds apart, instead of the random rolls.
const FORCED = (new URLSearchParams(location.search).get('alert') || '').split(',').filter(t => t in ALERTS);

let partyAds = 0, partyMicros = 0;

async function post(url, body = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || 'Something went wrong.');
    return json;
}

function endParty(message) {
    started = false;
    if (player && player.stopVideo) player.stopVideo();
    $('endMessage').textContent = message;
    show('endScreen', true);
    $('label').textContent = 'Party over';
}

async function nextAd() {
    busy = true;
    show('upNext', false);
    try {
        current = await post(NEXT_URL, $('britishOnly').checked ? { region: 'GB' } : {});
    } catch (e) {
        return endParty(e.message);
    }
    watched = 0; lastTick = null;
    checks = Object.entries(ALERTS)
        .filter(([, a]) => Math.random() < a.chance)
        .map(([type]) => ({ type, at: 4 + Math.random() * Math.max(current.required_seconds - 8, 1) }))
        .sort((a, b) => a.at - b.at);
    checks.forEach(c => c.trigger = 'random');
    if (FORCED.length) checks = FORCED.map((type, i) => ({ type, at: 3 + i * 5, trigger: 'forced' }));

    $('nowMeta').textContent = `${current.ad.category} · ${current.cpm} CPM`;
    $('nowTitle').textContent = `${current.ad.advertiser} — ${current.ad.title}`;
    $('nowPayout').textContent = '+' + current.payout;
    $('req').textContent = current.required_seconds;
    $('label').textContent = 'Loading next ad…';
    player.loadVideoById(current.ad.youtube_id);
    busy = false;

    // If the browser blocks autoplay with sound, fall back to muted playback.
    const id = current.ad.youtube_id;
    setTimeout(() => {
        if (started && current.ad.youtube_id === id && !alerting && Webcam.on()
            && player.getPlayerState() !== YT.PlayerState.PLAYING && watched === 0) {
            player.mute();
            player.playVideo();
        }
    }, 4000);
}

async function claim() {
    busy = true;
    player.pauseVideo();
    $('label').textContent = 'Claiming…';
    try {
        const r = await post(current.complete_url, { watched_seconds: Math.floor(watched) });
        partyAds++; partyMicros += r.earned_micros;
        $('statAds').textContent = partyAds;
        $('statEarned').textContent = '$' + (partyMicros / 1e6).toFixed(5);
        $('statEscrow').textContent = r.escrow;
        $('statAvailable').textContent = r.available;
        if (partyAds === 1) $('log').innerHTML = '';
        const li = document.createElement('li');
        li.className = 'p-3 flex justify-between gap-3';
        li.innerHTML = '<span class="truncate"></span><span class="text-emerald-600 font-semibold shrink-0"></span>';
        li.children[0].textContent = `${current.ad.advertiser} — ${current.ad.title}`;
        li.children[1].textContent = '+' + r.earned;
        $('log').prepend(li);
        $('upNextEarned').textContent = '+' + r.earned;
    } catch (e) {
        $('upNextEarned').textContent = 'Not counted: ' + e.message;
    }
    show('upNext', true);
    for (let n = 3; n > 0; n--) {
        $('upNextCount').textContent = n;
        await new Promise(r => setTimeout(r, 1000));
    }
    if (started) nextAd();
}

// Manual triggers for testing: L = look-away, V = undeclared viewer, C = competitor product, Y = yell the brand,
// S = smile.
// Documented in the console only.
console.info(
    '%c🎉 Watch party test controls%c\n' +
    '  L  trigger "not looking at the screen" alert\n' +
    '  V  trigger "undeclared viewer" alert\n' +
    '  C  trigger "competitor product detected" alert\n' +
    '  Y  trigger "yell the brand name" alert\n' +
    '  S  trigger "negative emotions — smile to restart" alert\n' +
    '  (keys work while an ad is playing and no alert is showing)\n\n' +
    'Force alerts on every ad with a URL query:\n' +
    '  ' + location.origin + location.pathname + '?alert=lookaway\n' +
    '  ' + location.origin + location.pathname + '?alert=viewer\n' +
    '  ' + location.origin + location.pathname + '?alert=competitor\n' +
    '  ' + location.origin + location.pathname + '?alert=shout\n' +
    '  ' + location.origin + location.pathname + '?alert=smile\n' +
    '  ' + location.origin + location.pathname + '?alert=lookaway,viewer,competitor,shout,smile',
    'font-weight:bold;font-size:13px', ''
);
document.addEventListener('keydown', (e) => {
    if (e.repeat || e.ctrlKey || e.metaKey || e.altKey || ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    const type = { l: 'lookaway', v: 'viewer', c: 'competitor', y: 'shout', s: 'smile' }[e.key.toLowerCase()];
    if (type && started && current && !busy && !alerting && Webcam.on()) raiseAlert(type, 'keyboard');
});

let activeAlert = null, alertShownAt = 0;

// Report attention checks to the server, which forwards them to GA4. Fire-and-forget.
function reportAlert(body) {
    fetch(ALERT_URL, {
        method: 'POST', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    }).catch(() => {});
}

function raiseAlert(type, trigger = 'random') {
    const a = activeAlert = ALERTS[type];
    alertShownAt = performance.now();
    reportAlert({ type, action: 'shown', trigger });
    alerting = true;
    player.pauseVideo();
    Webcam.alert(type, { label: current.competitor });
    const text = (v) => typeof v === 'function' ? v(current) : v;
    $('alertIcon').textContent = a.icon;
    $('alertTitle').textContent = text(a.title);
    $('alertBody').textContent = text(a.body);
    $('continueBtn').textContent = a.button;
    $('continueBtn').disabled = false;
    // The yell and smile checks have no button: they clear themselves once the mic / camera sees it done.
    const measured = !!(a.shout || a.smile);
    $('continueBtn').classList.toggle('hidden', measured);
    $('alertMeter').classList.toggle('hidden', !measured);
    show('alertBox', true);
    $('label').textContent = a.label;
    if (a.shout) listenForShout();
    if (a.smile) watchForSmile();
}

function clearAlert() {
    stopListening();
    stopSmileWatch();
    const type = Object.keys(ALERTS).find(k => ALERTS[k] === activeAlert);
    if (type) reportAlert({ type, action: 'cleared', duration_ms: Math.round(performance.now() - alertShownAt) });
    activeAlert = null;
    alerting = false;
    Webcam.alert(false);
    show('alertBox', false);
    player.playVideo();
}

$('continueBtn').addEventListener('click', async () => {
    if (activeAlert?.rescan) {
        // Pretend to check the feed again before letting playback resume.
        $('continueBtn').disabled = true;
        $('continueBtn').textContent = 'Re-scanning feed…';
        Webcam.scanning(true);
        await new Promise(r => setTimeout(r, 1800));
    }
    clearAlert();
});

// "Yell the brand": a live loudness meter from the microphone. Volume only, no speech recognition;
// the audio never leaves the browser and the mic is released as soon as the check clears.
//
// The pass mark eases off the longer you try (phone mics, especially iPhones with their always-on gain
// control, read much quieter than laptops): it starts at SHOUT_RMS and halves towards SHOUT_FLOOR every
// SHOUT_EASE_MS. The floor is still well above a quiet room (roughly 0.001-0.005), so silence can't pass.
const SHOUT_RMS = 0.05;       // starting pass mark (RMS 0-1; normal speech is roughly 0.02-0.08)
const SHOUT_FLOOR = 0.012;    // the easiest it ever gets
const SHOUT_EASE_MS = 6000;   // half-life of the easing
const SHOUT_HOLD_MS = 300;
let shout = null;

// iOS Safari only lets audio processing run if the AudioContext was started by a tap. Create/resume it
// on the "Start the party" tap (and any tap on the alert), then reuse it for every yell check.
let audioCtx = null;
function unlockAudio() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    audioCtx ??= new Ctx();
    if (audioCtx.state !== 'running') audioCtx.resume().catch(() => {});
    return audioCtx;
}
$('alertBox').addEventListener('pointerdown', unlockAudio);

async function listenForShout() {
    $('meterFill').style.width = '0%';
    $('meterFill').className = 'h-full bg-amber-400';
    $('meterStatus').textContent = 'Listening…';
    // A permission prompt can sit unanswered (or never resolve); don't leave the viewer stuck.
    setTimeout(() => {
        if (activeAlert === ALERTS.shout && !shout) {
            $('meterStatus').textContent = 'Still waiting for microphone access…';
            $('continueBtn').classList.remove('hidden');
        }
    }, 6000);
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false } });
        if (activeAlert !== ALERTS.shout) return stream.getTracks().forEach(t => t.stop());
        const ctx = unlockAudio();
        if (!ctx) throw new DOMException('Web Audio not supported', 'NotSupportedError');
        const analyser = ctx.createAnalyser();
        analyser.fftSize = 1024;
        const source = ctx.createMediaStreamSource(stream);
        source.connect(analyser);
        const buf = new Float32Array(analyser.fftSize);
        const started = performance.now();
        let loudFor = 0, last = started;
        shout = { stream, source, timer: null };

        shout.timer = setInterval(() => {
            const now = performance.now();
            if (ctx.state !== 'running') {
                // iOS kept the audio suspended: it needs a tap from the viewer.
                $('meterStatus').textContent = 'Tap here to start listening';
                $('meterTap').classList.remove('hidden');
                last = now;
                return;
            }
            $('meterTap').classList.add('hidden');
            analyser.getFloatTimeDomainData(buf);
            const rms = Math.sqrt(buf.reduce((sum, v) => sum + v * v, 0) / buf.length);
            const passMark = SHOUT_FLOOR + (SHOUT_RMS - SHOUT_FLOOR) * Math.pow(0.5, (now - started) / SHOUT_EASE_MS);
            const level = rms / passMark; // 1 = pass mark, drawn at 80% of the bar
            $('meterFill').style.width = Math.min(level * 80, 100) + '%';
            if (level < 1) $('meterStatus').textContent = now - started > 5000 ? 'Getting easier… keep going!' : 'Louder! Get past the line.';

            loudFor = level >= 1 ? loudFor + (now - last) : Math.max(0, loudFor - (now - last) / 2);
            last = now;
            if (loudFor >= SHOUT_HOLD_MS) heardShout();
        }, 50);
        $('meterStatus').textContent = 'Louder! Get past the line.';
    } catch (e) {
        // No mic (blocked or missing): fall back to the honour system.
        $('meterStatus').textContent = 'Microphone unavailable (' + e.name + ').';
        $('continueBtn').classList.remove('hidden');
    }
}

function stopListening() {
    if (!shout) return;
    clearInterval(shout.timer);
    shout.source.disconnect();
    shout.stream.getTracks().forEach(t => t.stop());
    $('meterTap').classList.add('hidden');
    shout = null; // the AudioContext itself is kept for the next check (iOS would need another tap)
}

async function heardShout() {
    stopListening();
    $('meterFill').style.width = '100%';
    $('meterFill').className = 'h-full bg-emerald-400';
    $('meterStatus').textContent = 'Heard you! 🔊';
    await new Promise(r => setTimeout(r, 1000));
    if (activeAlert === ALERTS.shout) clearAlert();
}

// "Smile to restart": real smile detection with Google's MediaPipe Face Landmarker, run entirely in this
// browser on the webcam feed (no frames are uploaded). It reads the mouthSmileLeft/Right blendshapes and
// needs a smile held for half a second. Loaded lazily (a few MB) and cached for the rest of the party.
const MEDIAPIPE = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';
const FACE_MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';
const SMILE_SCORE = 0.5;   // average smile blendshape (0-1) that counts as a smile
const SMILE_HOLD_MS = 500;
let faceLandmarker = null, faceLandmarkerLoading = null, smileWatch = null;

function loadFaceLandmarker() {
    faceLandmarkerLoading ??= (async () => {
        const { FaceLandmarker, FilesetResolver } = await import(`${MEDIAPIPE}/vision_bundle.mjs`);
        const fileset = await FilesetResolver.forVisionTasks(`${MEDIAPIPE}/wasm`);
        const options = (delegate) => ({ baseOptions: { modelAssetPath: FACE_MODEL, delegate }, outputFaceBlendshapes: true, runningMode: 'VIDEO', numFaces: 1 });
        try {
            faceLandmarker = await FaceLandmarker.createFromOptions(fileset, options('GPU'));
        } catch {
            faceLandmarker = await FaceLandmarker.createFromOptions(fileset, options('CPU'));
        }
        return faceLandmarker;
    })().catch((e) => { faceLandmarkerLoading = null; throw e; }); // allow a retry next time
    return faceLandmarkerLoading;
}

async function watchForSmile() {
    $('meterFill').style.width = '0%';
    $('meterFill').className = 'h-full bg-amber-400';
    $('meterStatus').textContent = faceLandmarker ? 'Smile!' : 'Loading smile detector…';
    // If the detector can't load or can't find a face, don't leave the viewer stuck.
    let smiledAtAll = false;
    setTimeout(() => {
        if (activeAlert === ALERTS.smile && !smiledAtAll) $('continueBtn').classList.remove('hidden');
    }, 8000);

    try {
        await loadFaceLandmarker();
    } catch (e) {
        if (activeAlert !== ALERTS.smile) return;
        $('meterStatus').textContent = 'Smile detector unavailable.';
        $('continueBtn').classList.remove('hidden');
        return;
    }
    if (activeAlert !== ALERTS.smile) return;

    const video = document.getElementById('camVideo');
    let heldFor = 0, last = performance.now(), lastFrame = -1;
    $('meterStatus').textContent = 'Smile! Hold it past the line.';

    smileWatch = setInterval(() => {
        if (video.readyState < 2 || video.currentTime === lastFrame) return;
        lastFrame = video.currentTime;
        const now = performance.now();
        const shapes = faceLandmarker.detectForVideo(video, now).faceBlendshapes?.[0]?.categories;
        if (!shapes) {
            $('meterFill').style.width = '0%';
            $('meterStatus').textContent = 'No face found. Look at the camera.';
            heldFor = 0;
            last = now;
            return;
        }
        const score = (name) => shapes.find(c => c.categoryName === name)?.score ?? 0;
        const level = (score('mouthSmileLeft') + score('mouthSmileRight')) / 2 / SMILE_SCORE; // 1 = pass mark (80% of bar)
        $('meterFill').style.width = Math.min(level * 80, 100) + '%';
        $('meterStatus').textContent = level >= 1 ? 'Hold that smile…' : 'Bigger smile! Get past the line.';
        if (level >= 1) smiledAtAll = true;
        heldFor = level >= 1 ? heldFor + (now - last) : Math.max(0, heldFor - (now - last) / 2);
        last = now;
        if (heldFor >= SMILE_HOLD_MS) sawSmile();
    }, 100);
}

function stopSmileWatch() {
    clearInterval(smileWatch);
    smileWatch = null;
}

// Save the winning grin to the viewer's own downloads (nothing is uploaded): the webcam frame, mirrored
// like the on-screen preview, with a caption strip.
function downloadGrin() {
    const video = document.getElementById('camVideo');
    if (!video.videoWidth) return;
    const w = video.videoWidth, h = video.videoHeight, bar = Math.round(h * 0.12);
    const canvas = Object.assign(document.createElement('canvas'), { width: w, height: h + bar });
    const ctx = canvas.getContext('2d');
    ctx.save();
    ctx.translate(w, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, w, h);
    ctx.restore();

    const now = new Date();
    ctx.fillStyle = '#022c22';
    ctx.fillRect(0, h, w, bar);
    ctx.fillStyle = '#34d399';
    ctx.font = `bold ${Math.round(bar * 0.36)}px ui-monospace, monospace`;
    ctx.textBaseline = 'middle';
    ctx.fillText('SMILE DETECTED ✓', bar * 0.3, h + bar * 0.35);
    ctx.fillStyle = '#a7f3d0';
    ctx.font = `${Math.round(bar * 0.24)}px ui-monospace, monospace`;
    ctx.fillText(`AdWatch Brow Micro-Furrow™ AI · ${current?.ad.advertiser ?? ''} · ${now.toLocaleString('en-GB')}`, bar * 0.3, h + bar * 0.72);

    canvas.toBlob((blob) => {
        if (!blob) return;
        // Local time, matching the caption: good_girl_2026-10-09_18-37-59.jpeg
        const pad = (n) => String(n).padStart(2, '0');
        const stamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `good_girl_${stamp}.jpeg` });
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 10000);
    }, 'image/jpeg', 0.9);
}

async function sawSmile() {
    stopSmileWatch();
    downloadGrin();
    $('meterFill').style.width = '100%';
    $('meterFill').className = 'h-full bg-emerald-400';
    $('meterStatus').textContent = 'Lovely smile! 😊';
    Webcam.status('SMILE DETECTED ✓');
    await new Promise(r => setTimeout(r, 1000));
    if (activeAlert === ALERTS.smile) clearAlert();
}

$('startBtn').addEventListener('click', async () => {
    unlockAudio(); // a tap: the one moment iOS will allow audio processing to start
    if (!Webcam.on()) await Webcam.enable();
    if (!Webcam.on()) {
        $('label').textContent = 'The party needs your webcam on';
        return;
    }
    started = true;
    show('startScreen', false);
    nextAd();
    // Warm up the smile detector in the background so the first smile check isn't a wait.
    setTimeout(() => loadFaceLandmarker().catch(() => {}), 5000);
});

Webcam.onChange((on) => {
    if (!started) return;
    show('camGate', !on);
    if (!on) player.pauseVideo();
    else if (!alerting && !busy) player.playVideo();
});

function render() {
    const req = current ? current.required_seconds : 30;
    const s = Math.min(Math.floor(watched), req);
    $('secs').textContent = s;
    $('bar').style.width = (s / req * 100) + '%';
}

setInterval(() => {
    if (!started || !current || busy || !player || !player.getPlayerState) return;
    const state = player.getPlayerState();

    if (state === YT.PlayerState.ENDED && watched < current.required_seconds) {
        if (watched >= player.getDuration() - 2) {
            watched = current.required_seconds; // short ad watched in full
        } else if (!alerting) {
            // Ended before it qualified (the timer lost time to buffering or pauses): there are no
            // controls in the party, so replay it automatically and keep counting.
            player.seekTo(0, true);
            player.playVideo();
            $('label').textContent = 'Replaying — keep watching to qualify';
        }
    }
    const playing = state === YT.PlayerState.PLAYING && Webcam.on() && !alerting && !document.hidden;
    if (playing) {
        const now = player.getCurrentTime();
        if (lastTick !== null) {
            const delta = now - lastTick;
            if (delta > 0 && delta < 1.5) watched += delta;
        }
        lastTick = now;
        $('label').textContent = 'Watching…';
        if (checks.length && watched >= checks[0].at) { const c = checks.shift(); raiseAlert(c.type, c.trigger); }
    } else {
        lastTick = null;
    }
    render();
    if (watched >= current.required_seconds) claim();
}, 250);

window.onYouTubeIframeAPIReady = function () {
    player = new YT.Player('player', {
        width: '100%', height: '100%',
        playerVars: { controls: 0, disablekb: 1, rel: 0, modestbranding: 1, playsinline: 1, autoplay: 1 },
        events: {
            onReady: () => { $('startBtn').disabled = false; $('startBtn').textContent = 'Start the party'; },
            onStateChange: (e) => {
                // Refuse playback without the webcam or while the look-away prompt is up.
                if (e.data === YT.PlayerState.PLAYING && (!Webcam.on() || alerting)) player.pauseVideo();
            },
            // Unembeddable or removed video: skip it (the server marks the unfinished view abandoned).
            onError: () => { if (started && !busy) nextAd(); },
        },
    });
};
</script>
<script src="https://www.youtube.com/iframe_api"></script>
@endsection
