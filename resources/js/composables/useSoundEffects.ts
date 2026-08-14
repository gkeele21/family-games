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
 * Effects live per game under public/sounds/<game>/, served from
 * /sounds/<game>/<file>. Pick the game when you call the composable:
 *   const sounds = useSoundEffects('family-feud');
 *   sounds.play('strike');
 * A missing file just no-ops (isAvailable() reports false) — callers can fall back.
 */

// Each game maps its own semantic effect names to filenames in its folder.
const SOUND_LIBRARY = {
    'america-says': {
        correct: 'CorrectAnswer.m4a',
        incorrect: 'IncorrectAnswer.m4a',
        timeout: 'EndTimer.m4a',
        question: 'ShowQuestion.m4a',
        board: 'AnswerBoard.m4a',
    },
    'family-feud': {
        intro: 'Intro.m4a',
        answerReveal: 'AnswerReveal.m4a',
        strike: 'Strike.m4a',
        buzzer: 'Buzzer.m4a',
        fastMoneyTimer1: 'FastMoneyTimer1.m4a',
        fastMoneyTimer2: 'FastMoneyTimer2.m4a',
        fastMoneyAnswerReveal: 'FastMoneyAnswerReveal.m4a',
        fastMoneyPointsReveal: 'FastMoneyPointsReveal.m4a',
        fastMoneyZeroPoints: 'FastMoneyZeroPoints.m4a',
    },
} as const;

export type Game = keyof typeof SOUND_LIBRARY;
// The valid effect names for a given game (e.g. 'strike' for 'family-feud').
export type SoundName<G extends Game> = Extract<keyof (typeof SOUND_LIBRARY)[G], string>;

export function useSoundEffects<G extends Game>(game: G, options: { volume?: number } = {}) {
    type Name = SoundName<G>;

    const volume = options.volume ?? 0.7;
    const files = SOUND_LIBRARY[game] as Record<Name, string>;
    const srcFor = (name: Name) => `/sounds/${game}/${files[name]}`;
    const ALL_SOUNDS = Object.keys(files) as Name[];

    // Whether the page has been unlocked for audio via a user gesture.
    const unlocked = ref(false);

    const players: Partial<Record<Name, HTMLAudioElement>> = {};
    // true = loaded OK, false = failed to load (missing file), undefined = unknown yet.
    const available: Partial<Record<Name, boolean>> = {};
    // Set once a real play() has fired for a sound, so a still-pending unlock
    // bless (see below) won't pause/mute the playback the user actually wants.
    const primed: Partial<Record<Name, boolean>> = {};

    const ensureLoaded = (name: Name): HTMLAudioElement => {
        let el = players[name];
        if (!el) {
            el = new Audio(srcFor(name));
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

    const isAvailable = (name: Name): boolean | undefined => available[name];

    const play = (name: Name) => {
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
