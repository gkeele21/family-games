import { onMounted, ref } from 'vue';

/**
 * Sound-effect playback for the TV display boards.
 *
 * The board is passive (nobody clicks it during play), so browsers block audio
 * until the page has seen one user gesture. This composable:
 *   - preloads the effect files up front,
 *   - "unlocks" them on the first pointer/key gesture anywhere on the page
 *     (and via an explicit unlock() the "Enable sound" button can call),
 *   - plays a named effect on demand, restarting it if it's already playing.
 *
 * Drop your own audio in public/sounds/ using the filenames below. A missing
 * file just no-ops (isAvailable() reports false) — callers can fall back.
 */
export type SoundName = 'correct' | 'incorrect' | 'timeout' | 'question' | 'board';

const SOUND_FILES: Record<SoundName, string> = {
    correct: '/sounds/CorrectAnswer.m4a',
    incorrect: '/sounds/IncorrectAnswer.m4a',
    timeout: '/sounds/EndTimer.m4a',
    question: '/sounds/ShowQuestion.m4a',
    board: '/sounds/AnswerBoard.m4a',
};

const ALL_SOUNDS = Object.keys(SOUND_FILES) as SoundName[];

export function useSoundEffects(options: { volume?: number } = {}) {
    const volume = options.volume ?? 0.7;

    // Whether the page has been unlocked for audio via a user gesture.
    const unlocked = ref(false);

    const players: Partial<Record<SoundName, HTMLAudioElement>> = {};
    // true = loaded OK, false = failed to load (missing file), undefined = unknown yet.
    const available: Partial<Record<SoundName, boolean>> = {};
    // Set once a real play() has fired for a sound, so a still-pending unlock
    // bless (see below) won't pause/mute the playback the user actually wants.
    const primed: Partial<Record<SoundName, boolean>> = {};

    const ensureLoaded = (name: SoundName): HTMLAudioElement => {
        let el = players[name];
        if (!el) {
            el = new Audio(SOUND_FILES[name]);
            el.preload = 'auto';
            el.volume = volume;
            el.addEventListener('canplaythrough', () => { available[name] = true; }, { once: true });
            el.addEventListener('error', () => { available[name] = false; }, { once: true });
            players[name] = el;
        }
        return el;
    };

    const preload = () => ALL_SOUNDS.forEach(ensureLoaded);

    /**
     * Bless every audio element for later programmatic playback. Must run inside
     * a user-gesture handler. We play each muted and immediately pause it, which
     * satisfies the autoplay policy for subsequent .play() calls.
     */
    const unlock = () => {
        if (unlocked.value) return;
        unlocked.value = true;
        ALL_SOUNDS.forEach((name) => {
            const el = ensureLoaded(name);
            el.muted = true;
            const p = el.play();
            // Restore the element to a clean, audible, paused state. This may run
            // late (the play promise only resolves once the file can play), so if
            // a real play() has already taken the element over (primed), leave it
            // running and just make sure it isn't left muted.
            const restore = () => {
                el.muted = false;
                if (primed[name]) return;
                el.pause();
                el.currentTime = 0;
            };
            if (p) {
                p.then(restore).catch(() => { el.muted = false; });
            } else {
                restore();
            }
        });
    };

    const isAvailable = (name: SoundName): boolean | undefined => available[name];

    const play = (name: SoundName) => {
        const el = ensureLoaded(name);
        // Take ownership so a still-pending unlock bless won't pause us, and force
        // audible in case that bless hasn't unmuted this element yet.
        primed[name] = true;
        el.muted = false;
        try {
            el.currentTime = 0;
            const p = el.play();
            if (p) p.catch((err) => console.warn(`Sound "${name}" was blocked:`, err));
        } catch (e) {
            console.warn(`Could not play sound "${name}":`, e);
        }
    };

    onMounted(preload);

    return { play, unlock, preload, isAvailable, unlocked };
}
