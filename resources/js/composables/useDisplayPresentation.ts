import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Presentation controls for the TV/projector display boards.
 *
 * The board is opened on whatever screen is at hand — a laptop plugged into an
 * AirBnB TV over HDMI, or an iPhone mirrored via AirPlay — so it has to make
 * itself at home on hardware we don't control. This composable owns the
 * "make it look like an app, not a web page" concerns:
 *
 *   - Fullscreen: a real Fullscreen API toggle (works on desktop browsers; a
 *     harmless no-op on iOS Safari, where Add-to-Home-Screen standalone mode is
 *     the chromeless path instead).
 *   - Wake lock: keeps the driving device awake so the screen never sleeps and
 *     an AirPlay mirror never drops mid-game. Re-acquired when the tab returns
 *     to the foreground (the lock is dropped whenever the page is hidden).
 *   - UI auto-hide: tracks recent pointer/key activity so the cursor and the
 *     floating controls can fade away during play and reappear on movement.
 *   - Overscan fit: some TVs crop the edges of an HDMI/cast signal. `fitScale`
 *     lets whoever's running the display shrink the board a notch on the fly
 *     (no code editing at the venue) so nothing important lands in the crop.
 */

// Wake Lock is still behind a vendor type in some TS DOM lib versions.
interface WakeLockSentinelLike {
    released: boolean;
    release: () => Promise<void>;
    addEventListener: (type: 'release', cb: () => void) => void;
}

// How long the cursor + controls linger after the last pointer/key activity.
const IDLE_HIDE_MS = 3000;

// Overscan fit steps: 1 = edge-to-edge (the default most TVs want), then two
// progressively inset levels for panels that crop.
const FIT_STEPS = [1, 0.975, 0.95];

export function useDisplayPresentation() {
    const isFullscreen = ref(false);
    const uiActive = ref(true);
    const fitStepIndex = ref(0);

    const fitScale = computed(() => FIT_STEPS[fitStepIndex.value]);
    const isFitInset = computed(() => fitStepIndex.value > 0);

    let idleTimer: number | null = null;
    let wakeLock: WakeLockSentinelLike | null = null;

    // --- Fullscreen -----------------------------------------------------------

    const fullscreenElement = () =>
        document.fullscreenElement ?? (document as any).webkitFullscreenElement ?? null;

    const syncFullscreen = () => {
        isFullscreen.value = fullscreenElement() !== null;
    };

    const toggleFullscreen = async () => {
        try {
            if (fullscreenElement()) {
                const exit = document.exitFullscreen ?? (document as any).webkitExitFullscreen;
                await exit?.call(document);
            } else {
                const el = document.documentElement as any;
                const request = el.requestFullscreen ?? el.webkitRequestFullscreen;
                await request?.call(el);
            }
        } catch {
            // iOS Safari (and some TV browsers) reject the request — nothing we
            // can do, and the standalone-launch path covers those cases.
        }
    };

    // --- Wake lock ------------------------------------------------------------

    const requestWakeLock = async () => {
        try {
            const wl = (navigator as any).wakeLock;
            if (!wl) return;
            wakeLock = await wl.request('screen');
            wakeLock?.addEventListener('release', () => {
                wakeLock = null;
            });
        } catch {
            // Not granted (e.g. battery saver) — non-fatal.
        }
    };

    const handleVisibility = () => {
        if (document.visibilityState === 'visible' && !wakeLock) {
            requestWakeLock();
        }
    };

    // --- UI / cursor auto-hide ------------------------------------------------

    const markActive = () => {
        uiActive.value = true;
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = window.setTimeout(() => {
            uiActive.value = false;
        }, IDLE_HIDE_MS);
    };

    // --- Overscan fit ---------------------------------------------------------

    const cycleFit = () => {
        fitStepIndex.value = (fitStepIndex.value + 1) % FIT_STEPS.length;
        markActive();
    };

    // --- Keyboard shortcuts ---------------------------------------------------

    const handleKeydown = (e: KeyboardEvent) => {
        markActive();
        // Ignore when typing into an input (e.g. a code field on the entry page).
        const target = e.target as HTMLElement | null;
        if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return;
        if (e.key === 'f' || e.key === 'F') {
            toggleFullscreen();
        }
    };

    const activityEvents: Array<keyof DocumentEventMap> = [
        'mousemove',
        'pointerdown',
        'touchstart',
    ];

    onMounted(() => {
        syncFullscreen();
        markActive();
        requestWakeLock();

        document.addEventListener('fullscreenchange', syncFullscreen);
        document.addEventListener('webkitfullscreenchange' as any, syncFullscreen);
        document.addEventListener('visibilitychange', handleVisibility);
        activityEvents.forEach((evt) => document.addEventListener(evt, markActive, { passive: true }));
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        if (idleTimer) clearTimeout(idleTimer);
        document.removeEventListener('fullscreenchange', syncFullscreen);
        document.removeEventListener('webkitfullscreenchange' as any, syncFullscreen);
        document.removeEventListener('visibilitychange', handleVisibility);
        activityEvents.forEach((evt) => document.removeEventListener(evt, markActive));
        window.removeEventListener('keydown', handleKeydown);
        wakeLock?.release().catch(() => {});
        wakeLock = null;
    });

    return {
        isFullscreen,
        toggleFullscreen,
        uiActive,
        fitScale,
        isFitInset,
        cycleFit,
    };
}
