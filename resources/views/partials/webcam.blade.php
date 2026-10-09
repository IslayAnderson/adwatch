{{--
    Webcam box with a purely cosmetic "attention check" overlay: nothing is analysed, recorded or uploaded.
    Exposes window.Webcam = { on(), enable(), onChange(fn), alert(false|'lookaway'|'viewer'|'competitor'|'shout'|'smile', { label }), scanning(bool), status(text|null) } for the page to gate playback on.
--}}
<style>
    @keyframes look { 0%, 100% { transform: translate(0, 0) } 20% { transform: translate(-6px, 1px) } 45% { transform: translate(6px, -1px) } 70% { transform: translate(0, 2px) } }
    @keyframes blink { 0%, 94%, 100% { transform: scaleY(1) } 97% { transform: scaleY(0.08) } }
    @keyframes wide { 0%, 100% { transform: scale(1) } 50% { transform: scale(1.12) } }
    .bb-pupil { animation: look 5s ease-in-out infinite; }
    .bb-eye { animation: blink 6s infinite; transform-origin: center; transform-box: fill-box; }
    .bb-brow { transition: transform .3s; }
    #camOverlay.alerting .bb-pupil { animation: none; transform: translate(0, 0); }
    #camOverlay.alerting .bb-eye { animation: wide 1s ease-in-out infinite; }
    #camOverlay.alerting .bb-brow { transform: translateY(-7px); }
    @keyframes alarm { 0%, 100% { opacity: .35 } 50% { opacity: .55 } }
    .bb-alarm { opacity: 0; transition: opacity .3s; }
    #camOverlay.alerting .bb-alarm { animation: alarm 1s ease-in-out infinite; }
    .bb-alarm-frame { opacity: 0; transition: opacity .3s; }
    #camOverlay.alerting .bb-alarm-frame { opacity: 1; }
    /* Undeclared viewer / competitor product: eyes dart to a fake detection box. */
    #bbBox { display: none; }
    #camOverlay.alert-box #bbBox { display: inline; }
    #camOverlay.alerting.look-left .bb-pupil { transform: translate(-10px, 1px); }
    #camOverlay.alerting.look-right .bb-pupil { transform: translate(10px, 1px); }
</style>
<div class="relative aspect-[4/3] bg-slate-800 rounded-xl overflow-hidden">
    <video id="camVideo" autoplay muted playsinline class="w-full h-full object-cover -scale-x-100 hidden"></video>

    <div id="camOff" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 text-sm text-center p-4">
        <div class="text-3xl mb-2">📷</div>
        Webcam off
        <p id="camError" class="text-red-400 text-xs mt-2 font-semibold"></p>
    </div>

    {{-- A pair of watchful eyes: attentive rather than menacing. Purely cosmetic: nothing is analysed, recorded or uploaded. --}}
    <svg id="camOverlay" viewBox="0 0 300 225" preserveAspectRatio="xMidYMid slice" class="hidden absolute inset-0 w-full h-full pointer-events-none">
        <defs>
            @foreach ([90, 210] as $cx)
                <clipPath id="bbClip{{ $cx }}"><path d="M{{ $cx - 42 }} 118 Q{{ $cx }} 88 {{ $cx + 42 }} 118 Q{{ $cx }} 142 {{ $cx - 42 }} 118 Z"/></clipPath>
            @endforeach
        </defs>
        {{-- Red wash over the whole feed while an alert is showing. --}}
        <rect class="bb-alarm" x="0" y="0" width="300" height="225" fill="#dc2626"/>
        <rect class="bb-alarm-frame" x="3" y="3" width="294" height="219" rx="10" fill="none" stroke="#ef4444" stroke-width="5"/>
        <g opacity=".75">
            <rect x="28" y="62" width="244" height="96" rx="30" fill="#17212b" opacity=".3"/>
            @foreach ([90 => 1, 210 => -1] as $cx => $side)
                {{-- Level brow, inner end dipping just slightly. --}}
                <path class="bb-brow" d="M{{ $cx - 34 * $side }} 84 Q{{ $cx }} 78 {{ $cx + 32 * $side }} 88" fill="none" stroke="#e5e7eb" stroke-width="5" stroke-linecap="round" stroke-opacity=".85"/>
                <g class="bb-eye">
                    <path d="M{{ $cx - 42 }} 118 Q{{ $cx }} 88 {{ $cx + 42 }} 118 Q{{ $cx }} 142 {{ $cx - 42 }} 118 Z" fill="#e5e7eb" fill-opacity=".75"/>
                    <g clip-path="url(#bbClip{{ $cx }})">
                        <g class="bb-pupil">
                            <circle cx="{{ $cx }}" cy="117" r="15" fill="#4b5563"/>
                            <circle cx="{{ $cx }}" cy="117" r="15" fill="none" stroke="#17212b" stroke-width="2.5"/>
                            <circle cx="{{ $cx }}" cy="117" r="7" fill="#0b1118"/>
                            <circle cx="{{ $cx - 5 }}" cy="112" r="2.6" fill="#f9fafb"/>
                        </g>
                    </g>
                    {{-- Upper lid line gives the stare some weight. --}}
                    <path d="M{{ $cx - 42 }} 118 Q{{ $cx }} 88 {{ $cx + 42 }} 118" fill="none" stroke="#17212b" stroke-width="3.5" stroke-linecap="round"/>
                </g>
            @endforeach
        </g>

        {{-- Fake detection box: a second "face" or a competitor's product, sized and labelled from JS. --}}
        <g id="bbBox">
            <rect id="bbBoxRect" x="0" y="0" width="74" height="96" rx="6" fill="#dc2626" fill-opacity=".15" stroke="#fecaca" stroke-width="2.5" stroke-dasharray="7 4">
                <animate attributeName="stroke-dashoffset" values="0;22" dur="1s" repeatCount="indefinite"/>
            </rect>
            <rect id="bbBoxTag" x="0" y="-14" width="74" height="13" rx="3" fill="#dc2626"/>
            <text id="bbBoxLabel" x="37" y="-4.5" text-anchor="middle" font-family="ui-monospace, monospace" font-size="7.5" font-weight="700" fill="#fff" letter-spacing=".5"></text>
        </g>

        <circle id="camDot" cx="282" cy="217" r="3" fill="#c4262e"><animate attributeName="opacity" values="1;.2;1" dur="1.4s" repeatCount="indefinite"/></circle>
        <text id="gaze" x="150" y="216" text-anchor="middle" font-family="ui-monospace, monospace" font-size="8.5" font-weight="700" fill="#f9fafb" stroke="#17212b" stroke-width="2.5" paint-order="stroke" letter-spacing="1">EYES ON SCREEN ✓</text>
    </svg>
</div>
<p class="text-xs text-slate-400 mt-2">Your camera feed stays in this browser. Nothing is recorded or uploaded.</p>

<script>
window.Webcam = (() => {
    let stream = null, alerting = false;
    const ALERT_TEXT = { lookaway: 'EYES NOT DETECTED ✗', viewer: 'UNDECLARED VIEWER ✗', competitor: 'COMPETITOR PRODUCT ✗', shout: 'SAY IT LOUD 🔊', smile: 'NEGATIVE EMOTION ✗' };
    let scanText = null;
    const listeners = [];
    const el = (id) => document.getElementById(id);

    const on = () => stream !== null && stream.getVideoTracks().some(t => t.readyState === 'live');

    function render() {
        const live = on();
        el('camVideo').classList.toggle('hidden', !live);
        el('camOverlay').classList.toggle('hidden', !live);
        el('camOff').classList.toggle('hidden', live);
        el('camOverlay').classList.toggle('alerting', !!alerting);
        el('camOverlay').classList.toggle('alert-box', alerting === 'viewer' || alerting === 'competitor');
        el('gaze').setAttribute('fill', alerting ? '#f87171' : '#f9fafb');
        if (alerting) el('gaze').textContent = scanText || ALERT_TEXT[alerting];
        listeners.forEach(fn => fn(live));
    }

    function lost() {
        stream = null;
        render();
    }

    async function enable() {
        el('camError').textContent = '';
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
            el('camVideo').srcObject = stream;
            stream.getVideoTracks().forEach(t => t.addEventListener('ended', lost));
        } catch (e) {
            stream = null;
            el('camError').textContent = e.name === 'NotAllowedError'
                ? 'Camera permission denied. Allow camera access to watch ads.'
                : 'No usable webcam found (' + e.name + ').';
        }
        render();
    }

    // Camera unplugged or revoked without an 'ended' event.
    setInterval(() => { if (stream !== null && !on()) lost(); }, 500);

    // Fake gaze readout to sell the "we're watching you watch" effect.
    setInterval(() => {
        if (on() && !alerting) el('gaze').textContent = ['EYES ON SCREEN ✓', 'TRACKING ATTENTION…', 'EYES ON SCREEN ✓', 'FACE DETECTED ✓'][Math.floor(Math.random() * 4)];
    }, 1800);

    // Re-use an already-granted camera permission so the next page starts without another click.
    document.addEventListener('DOMContentLoaded', () => {
        navigator.permissions?.query({ name: 'camera' }).then(p => { if (p.state === 'granted') enable(); }).catch(() => {});
    });

    return {
        on,
        enable,
        onChange: (fn) => listeners.push(fn),
        alert: (type, { label = '' } = {}) => {
            alerting = type === true ? 'lookaway' : (type || false);
            scanText = null;
            if (alerting === 'viewer' || alerting === 'competitor') {
                // Put the fake detection box on a random side and have the eyes dart to it:
                // a face-sized box up top for a viewer, a product-sized one lower down for a competitor.
                const product = alerting === 'competitor';
                const text = product ? `${label.toUpperCase()} ${90 + Math.floor(Math.random() * 10)}%` : 'UNKNOWN 1';
                const w = product ? Math.max(60, text.length * 4.8 + 14) : 74;
                const h = product ? 58 : 96;
                const left = Math.random() < 0.5;
                const y = product ? 128 + Math.random() * 20 : 50 + Math.random() * 40;
                el('bbBox').setAttribute('transform', `translate(${left ? 12 : 288 - w} ${y})`);
                el('bbBoxRect').setAttribute('width', w);
                el('bbBoxRect').setAttribute('height', h);
                el('bbBoxTag').setAttribute('width', w);
                el('bbBoxLabel').setAttribute('x', w / 2);
                el('bbBoxLabel').textContent = text;
                el('camOverlay').classList.toggle('look-left', left);
                el('camOverlay').classList.toggle('look-right', !left);
            } else {
                el('camOverlay').classList.remove('look-left', 'look-right');
            }
            render();
        },
        // Override the alert readout while an alert is being cleared, e.g. "RE-SCANNING FEED…".
        status: (text) => { scanText = text || null; render(); },
        scanning: (on) => { scanText = on ? 'RE-SCANNING FEED…' : null; render(); },
    };
})();
</script>
