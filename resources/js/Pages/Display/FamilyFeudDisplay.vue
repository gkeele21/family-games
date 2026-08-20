<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useSoundEffects } from '@/composables/useSoundEffects';

/* =====================================================================
   Family Feud projector board — a data-driven port of the approved mockup
   at docs/specs/family-feud-authentic.html. It renders every session
   status/phase off the same /display/{code}/state poll that America Says
   uses, and is wired into Display/Game.vue (isFamilyFeud branch).

   Colors are intentionally OFF the Keeler palette — this board is a replica
   of the real show's gameboard (a sanctioned exception, projector-only,
   nothing sold), exactly like AmericaSaysDisplay.vue. Only the sound-check
   card (keeler-app scope) uses the palette tokens.

   ── Phase model (gameState.phase) ────────────────────────────────────
   Family Feud reuses the existing America-Says guided host flow where it
   lines up, and adds a few Feud-only phases. What the host must set:

     'intro'      Round-N intro slide (team up next).           [reuse AS]
     'faceoff'    Board + survey shown, proscenium up, no answers
                  revealed — the buzz-in moment. (Optional: if the host
                  never sets it, intro → 'question' still works.)   [NEW]
     'question'   Main play: board + answers reveal as guessed.   [reuse AS]
     'steal'      Other team is stealing — STEAL banner, board stays,
                  control glow moves to the stealing team.        [reuse AS]
     'reveal'     Steal missed — reveal the leftovers, banner off. [reuse AS]
     'recap'      End-of-round scores (crowns leader before FM).  [reuse AS]
     'fast_money' Fast Money board (5 Q's, running total).         [NEW]

   Result: session status 'completed' → winner slide (top score).

   Strikes: the board flashes N red X's and plays the strike/buzzer cue
   whenever the strike count rises. Source of truth, in order:
     1. gameState.strikes (authoritative, if the host publishes it), else
     2. derived from the host's existing "Wrong Answer" buzzer
        (gameState.wrong_buzz) counted within the current question — so
        strikes work TODAY with the current host controls, no backend
        change. Reset on question change / return to faceoff.

   Fast Money: renders from gameState.fast_money when the host publishes it
   (see the FastMoney interface). Until then it degrades to the single
   current fast_money question as one row. TODO(host): publish the FM
   aggregate + per-row reveals + timer counters.

   Sounds: files live in public/sounds/family-feud/ and are owned by
   another agent; this board only TRIGGERS them off phase/reveal changes,
   the same way AmericaSaysDisplay does. Intro.m4a doubles as the theme
   sting on the start curtain.
   ===================================================================== */

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
    display_order?: number;
}

interface Answer {
    id: number;
    answer_text: string;
    points: number | null;
    display_order: number;
    revealed: boolean;
    // Feud pool contribution (0 for a steal reveal).
    pool_points?: number;
}

// Fast Money board the host publishes on gameState. Each of the 5 questions has a
// cell per player (Player 1 left column, Player 2 right column). The host captures
// answers hidden, then reveals text (shown) then points (scored) one at a time;
// the display only ever sees text once shown and points once scored.
interface FastMoneyCell {
    shown: boolean;
    scored: boolean;
    text?: string | null;
    points?: number | null;
}
interface FastMoneyRow {
    id: number;
    question: string;
    p1: FastMoneyCell;
    p2: FastMoneyCell;
}
interface FastMoney {
    rows: FastMoneyRow[];
    target: number;
    active_player: number;
    show_previous: boolean;
    p1_total: number;
    p2_total: number;
    combined_total: number;
    result: 'win' | 'lose' | null;
    // Monotonic counters the host bumps to fire the FM timer stings + duplicate cue.
    timer1_buzz?: number;
    timer2_buzz?: number;
    duplicate_buzz?: number;
}

interface GameState {
    round_number?: number;
    active_team_id?: number | null;
    // Guided flow phase (see the header). Absent on old sessions → 'question'.
    phase?: string;
    // Fast Money clock (published like any timer; the FM board shows it running).
    timer_started_at?: string | null;
    timer_duration?: number;
    // Authoritative current-strike count for the team on the board, if the host
    // publishes it. When absent we derive strikes from wrong_buzz (see below).
    strikes?: number | null;
    // Strikes that end a turn (config max_strikes; defaults to 3).
    max_strikes?: number | null;
    // Round-score multiplier for the current round, if the host publishes it.
    // Otherwise derived from round_number (rounds 1-2 = 1×, 3 = 2×, 4 = 3×).
    round_multiplier?: number | null;
    // Monotonic counter bumped by the host's "Wrong Answer" buzzer — each
    // advance is a strike (and plays the strike/buzzer cue).
    wrong_buzz?: number;
    // Face-off cues (monotonic): a buzz-in sounds the buzzer; a wrong face-off
    // answer flashes a strike X.
    faceoff_buzz?: number;
    faceoff_strike?: number;
    // The host armed the face-off from the round intro — fire the face-off music and
    // light the bulbs (bright-yellow flash) while still on the matchup slide.
    faceoff_armed?: boolean;
    // Fast Money board data (optional; see FastMoney above).
    fast_money?: FastMoney | null;
    // Family Feud: a team reached the target (300) — the recap becomes the
    // "Team X Wins the game" celebration before Fast Money.
    feud_target_reached?: boolean;
    // The face-off winner currently deciding Play/Pass (phase 'faceoff') — drives the
    // "{Team} — Play or Pass?" prompt shown while they choose.
    feud_decider?: number | null;
    // Face-off winner's Play/Pass call — a transient bottom banner. Monotonic seq.
    feud_decision?: { team_id: number; choice: 'play' | 'pass'; seq: number } | null;
    // The team that won the round's pool — a bottom "Wins the Round!" banner.
    feud_round_winner?: { team_id: number; amount: number; seq: number } | null;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    controlling_team_id: number | null;
    segment?: string | null;
    answers: Answer[];
}

interface Props {
    teams: Team[];
    gameState: GameState | null;
    currentQuestion: CurrentQuestion | null;
    inviteCode: string;
    // Session status: 'lobby' | 'playing' | 'paused' | 'completed'.
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    status: 'playing',
});

// Sound effects: uploaded clips in public/sounds/family-feud/.
const sounds = useSoundEffects('family-feud');

// The number of physical panels on the board is always 8 (two columns of 4);
// a question with fewer answers leaves the extra panels blank, like the show.
const BOARD_SLOTS = 8;
// Grid is row-major, 2 columns → this maps each grid cell to its answer index so
// numbers run 1-4 down the left column, 5-8 down the right.
const DOM_ORDER = [0, 4, 1, 5, 2, 6, 3, 7];

// Round-multiplier fallback (matches GameTypeSeeder default_config: rounds 1-2 =
// 1×, round 3 = 2×, round 4 = 3×). Used only when the host doesn't publish one.
const MULT_LABEL: Record<number, string> = { 1: '', 2: 'Double', 3: 'Triple' };

// --- Theme / start curtain ---------------------------------------------------
// Intro.m4a is the opening theme; it plays in full on the start curtain. (The
// Fast Money winner sting is its own clip now — Winner.m4a, played via 'win' —
// so the theme is no longer truncated to hide a sting in its tail.)
// Intro.m4a doubles as the theme sting. Loaded as its own <audio> (like the AS
// theme) so we get its 'ended' event to dismiss the curtain. It routes to the TV
// on the media channel exactly like the effect clips.
let theme: HTMLAudioElement | null = null;
const getTheme = (): HTMLAudioElement => {
    if (!theme) {
        theme = new Audio('/sounds/family-feud/Intro.m4a');
        theme.preload = 'auto';
        theme.volume = 0.6;
    }
    return theme;
};
let themePrimed = false;

// "Bless" an audio element inside a user gesture so it can be played
// programmatically later. isPrimed lets a real play() win the race with this
// bless's deferred restore, so we never pause audio the game wants playing.
const blessAudio = (m: HTMLAudioElement, isPrimed?: () => boolean) => {
    m.muted = true;
    const p = m.play();
    if (p) {
        p.then(() => {
            m.muted = false;
            if (isPrimed && isPrimed()) return;
            m.pause();
            m.currentTime = 0;
        }).catch(() => { m.muted = false; });
    }
};

// Theme slide: FF logo + team matchup, shown with the opening music the moment the
// host starts the game. The board needs NO gesture of its own — audio is already
// unlocked by the Entry page's "Open board" tap (same document; Inertia doesn't
// reload), and we prime everything on mount (see onMounted). Fires ONCE, when the
// game goes live.
const themeSlideOpen = ref(false);
// The opening theme has finished playing but the curtain stays up (calm) until the
// host starts the face-off. themeFlashing drives the party bulbs ONLY while the
// music is actually playing.
const themeMusicDone = ref(false);
const themeFlashing = computed(() => themeSlideOpen.value && !themeMusicDone.value && props.status === 'playing');
// Standby: the set sits dark until the host starts the game (Start Game → 'playing').
const isStandby = computed(() => props.status === 'lobby');
// True when the browser blocked the theme's autoplay — no user gesture on THIS page
// yet (a direct load / dev refresh, vs arriving via Entry's "Open board" tap). Shows
// a small "tap for sound" hint; any tap on the board unlocks and starts it.
const audioBlocked = ref(false);
let themeStarted = false;

const startTheme = () => {
    if (themeStarted) return;
    themeStarted = true;
    themeMusicDone.value = false;
    const t = getTheme();
    themeSlideOpen.value = true;
    let done = false;
    let safety = 0;
    const finish = () => {
        if (done) return;
        done = true;
        if (safety) clearTimeout(safety);
        t.pause();
        // Stop the flashing bulbs. Hold the curtain on the matchup slide only if
        // we're still at the round intro (the normal game-open) — it then waits for
        // the host to start the face-off (see the 'faceoff' phase watcher). If the
        // game already moved on (a mid-round reconnect), drop it so nothing's hidden.
        themeMusicDone.value = true;
        if (phase.value !== 'intro') themeSlideOpen.value = false;
    };
    // The theme plays to its natural end, then the curtain holds for the face-off.
    t.addEventListener('ended', finish, { once: true });
    t.addEventListener('error', finish, { once: true });
    t.currentTime = 0;
    themePrimed = true;
    const p = t.play();
    if (p) {
        p.then(() => { audioBlocked.value = false; }).catch(() => {
            // Autoplay blocked — no user gesture on this page yet. Hold silent, drop
            // the curtain back to the plain intro, prompt for a tap, and allow retry.
            if (safety) clearTimeout(safety);
            themeStarted = false;
            themeMusicDone.value = true;
            themeSlideOpen.value = false;
            audioBlocked.value = true;
        });
    }
    // Safety net if the audio stalls without firing 'ended'/'error' (Intro.m4a is
    // ~15.4s; keep this comfortably past that so it never clips a healthy play).
    safety = window.setTimeout(finish, 20000);
};

const maybeStartTheme = () => {
    if (props.status === 'playing') startTheme();
};

// Unlock audio + start the theme if it's pending. Called on mount (rides Entry's
// tap) and on the first interaction with the board (covers a direct load / refresh).
const tryUnlockAndPlay = () => {
    sounds.unlock();
    audioBlocked.value = false;
    if (props.status === 'playing') maybeStartTheme();
};
const onFirstInteraction = () => {
    window.removeEventListener('pointerdown', onFirstInteraction);
    window.removeEventListener('keydown', onFirstInteraction);
    tryUnlockAndPlay();
};

// Skip Intro: cut the theme and drop the curtain immediately.
const skipTheme = () => {
    if (theme) {
        theme.pause();
        theme.currentTime = 0;
    }
    themeSlideOpen.value = false;
};

// Start the theme the moment the host starts the game, and stop it when leaving
// active play. Audio was already unlocked on mount (Entry's tap), so this needs no
// fresh gesture.
watch(() => props.status, (s) => {
    if (s === 'playing') maybeStartTheme();
    else if (theme) { theme.pause(); theme.currentTime = 0; }
});

// --- Derived state -----------------------------------------------------------
// Teams in a fixed order (display_order) — the two family pods, left then right.
const orderedTeams = computed(() =>
    [...props.teams].sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0))
);
const leftTeam = computed<Team | null>(() => orderedTeams.value[0] ?? null);
const rightTeam = computed<Team | null>(() => orderedTeams.value[1] ?? null);

const phase = computed(() => props.gameState?.phase ?? 'question');
const roundNumber = computed(() => props.gameState?.round_number ?? 1);

// A team reached the target (300): the round turns into the "Team X Wins the game"
// celebration. This fires the instant the pool award crosses the target — at the
// recap (a clear) OR during the leftover-reveal beat (a steal), so the win slide
// shows as soon as the points are assigned, not after the host reveals leftovers.
const gameWon = computed(() =>
    !!props.gameState?.feud_target_reached && (phase.value === 'recap' || phase.value === 'reveal')
);

// Face-off "armed" from the intro (host hit Start Face-Off): reveals the face-off
// framing on the intro slide and fires the face-off music. No bulb strobe — the
// bright-yellow flash belongs to the opening theme. "Show Question" then brings up
// the board.
const faceoffArmed = computed(() => !!props.gameState?.faceoff_armed);
// Once the face-off sting finishes, the numbered (still question-less) board comes
// up; it stays up through "Show Question". Set in the faceoffArmed watcher.
const faceoffBoardShown = ref(false);
// The face-off slide (intro + plain recap) carries the live scores under each team
// UNTIL the host arms the face-off — then they drop and only the matchup remains.
const faceoffSlideScores = computed(() => !(phase.value === 'intro' && faceoffArmed.value));

// Fast Money spans a few phases (intro → p1 → p2 → result); the segment is the
// reliable signal since the current question stays a fast_money one throughout.
const isFastMoney = computed(() =>
    phase.value.startsWith('fast_money') || props.currentQuestion?.segment === 'fast_money'
);
const fmActivePlayer = computed(() => props.gameState?.fast_money?.active_player ?? 1);
const isSteal = computed(() => phase.value === 'steal');

// The board (proscenium + slots) is shown during the board phases, once there's a
// question to show and we're not in Fast Money. It ALSO comes up during the armed
// face-off (still 'intro') once the sting has finished — numbered slots, no question
// yet — and stays up when "Show Question" moves us into 'faceoff'.
const boardVisible = computed(() =>
    props.status === 'playing'
    && !!props.currentQuestion
    && !isFastMoney.value
    && !gameWon.value
    && (
        ['faceoff', 'question', 'steal', 'reveal'].includes(phase.value)
        || (phase.value === 'intro' && faceoffArmed.value && faceoffBoardShown.value)
    )
);
// In the face-off (the armed pre-board beat or the buzz-in phase) — used for the tag.
const inFaceoff = computed(() =>
    phase.value === 'faceoff' || (phase.value === 'intro' && faceoffArmed.value)
);
// The survey question text lands on the board only once the host hits "Show Question"
// (phase leaves 'intro'); before that the board shows just its numbered slots.
const questionShown = computed(() => boardVisible.value && phase.value !== 'intro');

// Round multiplier: host-published if present, else the seeder-default schedule.
const multiplier = computed(() => {
    const m = props.gameState?.round_multiplier;
    if (m != null) return m;
    const r = roundNumber.value;
    return r <= 2 ? 1 : r === 3 ? 2 : 3;
});
const multLabel = computed(() => MULT_LABEL[multiplier.value] ?? '');

// Answers sorted by rank (display_order); the number badge is the rank.
const sortedAnswers = computed(() => {
    if (!props.currentQuestion?.answers) return [];
    return [...props.currentQuestion.answers].sort((a, b) => a.display_order - b.display_order);
});
const answerCount = computed(() => Math.min(sortedAnswers.value.length, BOARD_SLOTS));

// The running "pot": sum of each revealed answer's pool contribution × the round
// multiplier. A steal reveal contributes 0 (pool_points), so it lights up on the
// board without inflating the pot the stealer wins.
const pot = computed(() => {
    const sum = sortedAnswers.value.reduce(
        (acc, a) => acc + (a.revealed ? (a.pool_points ?? a.points ?? 0) : 0),
        0,
    );
    return sum * multiplier.value;
});

// The 8 physical panels, in grid (row-major) order. Each cell either carries an
// answer (with its rank number) or is an empty blue bar.
interface Slot {
    key: string;
    exists: boolean;
    open: boolean;
    number: number;
    text: string;
    points: number | null;
}
const slots = computed<Slot[]>(() =>
    DOM_ORDER.map((ai, cell) => {
        const ans = ai < answerCount.value ? sortedAnswers.value[ai] : null;
        return {
            key: `cell-${cell}`,
            exists: !!ans,
            open: !!ans?.revealed,
            number: ans?.display_order ?? ai + 1,
            text: ans?.answer_text ?? '',
            points: ans?.points ?? null,
        };
    })
);

// Control glow: the team holding the board (or the stealing team during a steal).
// The controlling_team_id already tracks the stealing team once the host hands
// over, so it's the single source of truth in either phase.
const holderId = computed(() =>
    props.currentQuestion?.controlling_team_id ?? props.gameState?.active_team_id ?? null
);
const stealTeam = computed<Team | null>(() => {
    const id = props.currentQuestion?.controlling_team_id ?? props.gameState?.active_team_id;
    return id ? props.teams.find((t) => t.id === id) ?? null : null;
});

// --- Strikes -----------------------------------------------------------------
// displayStrikes = authoritative host count if published, else derived from the
// "Wrong Answer" buzzer within the current question. A rise flashes the overlay.
const maxStrikes = computed(() => props.gameState?.max_strikes ?? 3);
const derivedStrikes = ref(0);
const displayStrikes = computed(() =>
    props.gameState?.strikes != null ? props.gameState.strikes : derivedStrikes.value
);
// Number of X's to flash right now (0 = hidden). Flashes briefly then clears, so
// the board isn't permanently covered — matching how the show hits a strike.
const strikeFlash = ref(0);
let strikeFlashTimer: number | null = null;
const flashStrikes = (count: number) => {
    if (count <= 0) return;
    strikeFlash.value = Math.min(count, maxStrikes.value);
    // Always the strike cue — even on the third strike that hands to the steal.
    // The buzzer is the face-off buzz-in sound only; it shouldn't fire when the
    // board flips to the stealing team.
    sounds.play('strike');
    if (strikeFlashTimer) clearTimeout(strikeFlashTimer);
    strikeFlashTimer = window.setTimeout(() => { strikeFlash.value = 0; }, 1500);
};

// Reset the derived strike count when the question changes or we return to the
// face-off (a fresh turn), and clear any lingering flash.
watch(() => props.currentQuestion?.id, () => { derivedStrikes.value = 0; strikeFlash.value = 0; });
watch(phase, (now) => { if (now === 'faceoff' || now === 'intro') { derivedStrikes.value = 0; } });

// The host ARMS the face-off from the round intro: this is the "Start Face-Off"
// beat. Drop the opening curtain if it's still up (so the matchup slide + the
// bright-yellow bulb flash show on the board), and play the face-off music. The
// board itself doesn't appear until the host then hits "Show Question" (→ faceoff).
watch(faceoffArmed, (armed, was) => {
    if (armed && !was && props.status === 'playing') {
        if (themeSlideOpen.value) {
            if (theme) { theme.pause(); theme.currentTime = 0; }
            themeSlideOpen.value = false;
        }
        // Play the face-off sting; when it finishes, bring up the numbered (still
        // question-less) board. Fall back to a timer if 'ended' never fires (e.g.
        // audio blocked), so the board still appears.
        faceoffBoardShown.value = false;
        const reveal = () => { faceoffBoardShown.value = true; };
        const el = sounds.play('faceOff');
        if (el) {
            el.addEventListener('ended', reveal, { once: true });
            window.setTimeout(reveal, 7000);
        } else {
            window.setTimeout(reveal, 1200);
        }
    } else if (!armed) {
        faceoffBoardShown.value = false;
    }
});
// Show Question (intro/arm → 'faceoff'): the board comes up for the buzz-in. The
// music already fired on the arm; just make sure any lingering curtain is gone.
watch(phase, (now, prev) => {
    if (now === 'faceoff' && prev !== 'faceoff' && props.status === 'playing' && themeSlideOpen.value) {
        if (theme) { theme.pause(); theme.currentTime = 0; }
        themeSlideOpen.value = false;
    }
});

// Authoritative path: flash whenever the published count rises.
watch(() => props.gameState?.strikes ?? null, (now, prev) => {
    if (now != null && prev != null && now > prev) flashStrikes(now);
});

// Face-off buzz-in → sound the buzzer. Monotonic counter; the first value is
// adopted silently so a reconnect doesn't replay it.
watch(() => props.gameState?.faceoff_buzz ?? null, (now, prev) => {
    if (now != null && prev != null && now > prev) sounds.play('buzzer');
});
// Wrong face-off answer → flash one strike X (and its cue).
watch(() => props.gameState?.faceoff_strike ?? null, (now, prev) => {
    if (now != null && prev != null && now > prev) flashStrikes(1);
});

// Derived path: the host's wrong-answer buzzer is a strike during the board's
// playing phases. null until the first poll; the first value is adopted as the
// baseline WITHOUT counting, so a mid-game reconnect doesn't replay strikes.
const wrongBuzzCount = computed<number | null>(() =>
    props.gameState ? (props.gameState.wrong_buzz ?? 0) : null
);
watch(wrongBuzzCount, (now, prev) => {
    if (now === null || prev === null || prev === undefined) return;
    if (now <= prev) return;
    // Only count as a strike on the primary board phases; on a steal a wrong
    // guess ends the steal (handled by the host), it isn't a stacking strike.
    if (props.gameState?.strikes != null) return; // authoritative path owns it
    if (phase.value === 'faceoff' || phase.value === 'question') {
        derivedStrikes.value = Math.min(derivedStrikes.value + (now - prev), maxStrikes.value);
        flashStrikes(derivedStrikes.value);
    }
});

// --- Answer-reveal sound -----------------------------------------------------
// Play AnswerReveal.m4a whenever a new answer flips revealed on the SAME
// question. A question switch (or first load with reveals) syncs silently.
const revealedIds = computed(() =>
    sortedAnswers.value.filter((a) => a.revealed).map((a) => a.id)
);
const revealedKey = computed(() => [...revealedIds.value].sort((a, b) => a - b).join(','));
let prevRevealed = new Set<number>(revealedIds.value);
let prevQuestionId: number | null = props.currentQuestion?.id ?? null;

watch(revealedKey, () => {
    const qid = props.currentQuestion?.id ?? null;
    const now = new Set(revealedIds.value);
    if (qid !== prevQuestionId) {
        prevQuestionId = qid;
        prevRevealed = now;
        return;
    }
    let hasNew = false;
    now.forEach((id) => { if (!prevRevealed.has(id)) hasNew = true; });
    prevRevealed = now;
    if (hasNew) sounds.play('answerReveal');
});

// Keep the reveal baseline in sync with the CURRENT question even when it loads/
// changes without a change to its revealed set (gameState is fetched, not
// server-rendered), so the first reveal isn't mis-read as a question switch.
watch(() => props.currentQuestion?.id, (qid) => {
    prevQuestionId = qid ?? null;
    prevRevealed = new Set(revealedIds.value);
});

// --- Bottom banners: Play/Pass decision + round winner -----------------------
const teamById = (id?: number | null): Team | null =>
    id ? props.teams.find((t) => t.id === id) ?? null : null;

// Play or Pass? — the deciding (face-off winner) team is prompted at the bottom of
// the board WHILE they choose (phase 'faceoff', a decider set). Once the host
// records the call, the transient decisionBanner below replaces it.
const feudDeciderTeam = computed(() => teamById(props.gameState?.feud_decider));
const showDecisionPrompt = computed(() => phase.value === 'faceoff' && !!feudDeciderTeam.value);

// Play/Pass: the face-off winner's call. Shown just under that team's name on the
// board — "Play or Pass" while deciding, then the chosen "Play"/"Pass" for a few
// seconds after. Monotonic seq → shown once; the first value on load/reconnect is
// adopted silently.
const decisionBanner = ref<{ team: Team | null; choice: 'play' | 'pass' } | null>(null);
// One consistent status line in white under a team's name on the board. Covers the
// whole round: the Play/Pass call (prompt → chosen), the steal chance, and the round
// winner — all in the same spot instead of separate top/bottom banners.
const sideStatusLabel = (team: Team | null): string => {
    if (!team) return '';
    if (decisionBanner.value?.team?.id === team.id) return decisionBanner.value.choice === 'play' ? 'Play' : 'Pass';
    if (showDecisionPrompt.value && feudDeciderTeam.value?.id === team.id) return 'Play or Pass';
    if (isSteal.value && stealTeam.value?.id === team.id) return 'Chance to Steal';
    if (showRoundWinBanner.value && roundWinTeam.value?.id === team.id) return 'Winner!';
    return '';
};
let decisionTimer: number | null = null;
// seq is absent (null) until the first decision, so treat missing as 0 — the first
// real bump (0 → 1) still fires. A fresh page mid-game captures the current seq as
// its baseline (watch isn't immediate), so a reconnect doesn't replay a stale one.
watch(() => props.gameState?.feud_decision?.seq ?? 0, (now, prev) => {
    if (now <= prev) return;
    const d = props.gameState?.feud_decision;
    if (!d) return;
    decisionBanner.value = { team: teamById(d.team_id), choice: d.choice };
    if (decisionTimer) clearTimeout(decisionTimer);
    decisionTimer = window.setTimeout(() => { decisionBanner.value = null; }, 4500);
});

// Round winner: once the pool is awarded, flash "TEAM WINS THE ROUND!" at the
// bottom through the post-resolve beats. It clears as soon as the host reveals a
// LEFTOVER answer (reveal phase) — tracked against the reveal count at award time
// so the steal answer that triggered the award doesn't clear it immediately.
const roundWinBanner = ref(false);
const roundWinRevealBaseline = ref(0);
let winMusicTimer: number | null = null;
// The winner sting plays ONCE per round (latched). Timing keyed on how the pool
// resolved:
//   • Steal (→ 'reveal', board stays up): wait ~1s so the sting lands a beat AFTER
//     the steal's reveal/strike cue instead of stacking on it.
//   • Sweep (→ 'recap' slide): play it IN SYNC with the transition — the final
//     reveal was already ~2s ago, so there's nothing to stack on and no reason to lag.
let winMusicPlayed = false;
const playRoundWinMusic = (delay: number) => {
    if (winMusicPlayed) return;
    winMusicPlayed = true;
    if (winMusicTimer) clearTimeout(winMusicTimer);
    winMusicTimer = window.setTimeout(() => { sounds.play('win'); }, delay);
};
watch(() => props.gameState?.feud_round_winner?.seq ?? 0, (now, prev) => {
    if (now <= prev) return;
    roundWinBanner.value = true;
    roundWinRevealBaseline.value = revealedIds.value.length;
    playRoundWinMusic(phase.value === 'reveal' ? 1000 : 0);
});
watch(() => revealedIds.value.length, (n) => {
    if (roundWinBanner.value && phase.value === 'reveal' && n > roundWinRevealBaseline.value) {
        roundWinBanner.value = false;
    }
});
const roundWinTeam = computed(() => teamById(props.gameState?.feud_round_winner?.team_id));
const showRoundWinBanner = computed(() =>
    roundWinBanner.value && !!roundWinTeam.value && !gameWon.value
    && (phase.value === 'reveal' || phase.value === 'recap')
);
// A question change ends both banners, re-arms the winner sting for the new round,
// and cancels any pending one.
watch(() => props.currentQuestion?.id, () => {
    roundWinBanner.value = false;
    decisionBanner.value = null;
    winMusicPlayed = false;
    if (winMusicTimer) { clearTimeout(winMusicTimer); winMusicTimer = null; }
});

// --- Question-shown sound ----------------------------------------------------
// The intro sting fires with the start curtain (above). The board appearing on
// face-off gets the strike-library... nothing extra: reveals carry the audio.
// Fast Money reveals get their own cues below.

// --- Fast Money --------------------------------------------------------------
const fastMoney = computed<FastMoney | null>(() => props.gameState?.fast_money ?? null);
const fmRows = computed(() => fastMoney.value?.rows ?? []);
const fmTarget = computed(() => fastMoney.value?.target ?? 200);
const fmCombined = computed(() => fastMoney.value?.combined_total ?? 0);
const fmResult = computed(() => fastMoney.value?.result ?? null);
// Player 1's column is up except while Player 2 is capturing and the host hasn't
// flashed it to the room (Player 2 mustn't see it). Player 2's column appears once
// Player 2 is up (or at the result).
const fmShowP1 = computed(() =>
    phase.value !== 'fast_money_p2_capture' || !!fastMoney.value?.show_previous
);
const fmShowP2 = computed(() =>
    fmActivePlayer.value === 2 || phase.value === 'fast_money_result'
);

// A lightweight clock for the Fast Money capture passes.
const fmRemaining = ref(0);
let fmTimerInterval: number | null = null;
const computeFmRemaining = () => {
    const startedAt = props.gameState?.timer_started_at;
    const dur = props.gameState?.timer_duration ?? 0;
    if (!startedAt) { fmRemaining.value = dur; return; }
    const elapsed = Math.floor((Date.now() - new Date(startedAt).getTime()) / 1000);
    fmRemaining.value = Math.min(dur, Math.max(0, dur - elapsed));
};
const fmTimerDisplay = computed(() => `0:${String(fmRemaining.value).padStart(2, '0')}`);
const fmTimerRunning = computed(() =>
    (phase.value === 'fast_money_p1_capture' || phase.value === 'fast_money_p2_capture')
    && !!props.gameState?.timer_started_at
);

// Typewriter: when an answer is first shown it types onto the board. fmTyped holds
// how many characters of each cell are currently drawn; a cell already shown on
// load/reconnect is set to full length (no re-typing).
const fmCellKey = (rid: number, p: 1 | 2) => `${rid}-${p}`;
const fmTyped = ref<Record<string, number>>({});
const fmTypeTimers: Record<string, number> = {};
const fmStartTypewriter = (key: string, text: string) => {
    if (fmTypeTimers[key]) window.clearInterval(fmTypeTimers[key]);
    fmTyped.value = { ...fmTyped.value, [key]: 0 };
    let i = 0;
    fmTypeTimers[key] = window.setInterval(() => {
        i += 1;
        fmTyped.value = { ...fmTyped.value, [key]: i };
        if (i >= text.length) { window.clearInterval(fmTypeTimers[key]); delete fmTypeTimers[key]; }
    }, 45);
};
// The text to draw for a cell right now (respecting the typewriter progress).
const fmText = (rid: number, p: 1 | 2, cell: FastMoneyCell): string => {
    if (!cell.shown || !cell.text) return '';
    const n = fmTyped.value[fmCellKey(rid, p)];
    return n == null ? cell.text : cell.text.slice(0, n);
};

const cellFor = (rid: string, p: string): FastMoneyCell | null => {
    const row = fmRows.value.find((r) => String(r.id) === rid);
    return row ? (p === '1' ? row.p1 : row.p2) : null;
};

// Which cells' TEXT is shown / whose POINTS are scored (keys "rowId-player").
const fmShownKeys = computed(() => new Set(
    fmRows.value.flatMap((r) => [r.p1.shown ? fmCellKey(r.id, 1) : '', r.p2.shown ? fmCellKey(r.id, 2) : '']).filter(Boolean)
));
const fmScoredKeys = computed(() => new Set(
    fmRows.value.flatMap((r) => [r.p1.scored ? fmCellKey(r.id, 1) : '', r.p2.scored ? fmCellKey(r.id, 2) : '']).filter(Boolean)
));

// Answer shown → type it in + the answer sting. Adopt the first set silently.
let fmShownPrev = new Set<string>();
let fmShownInit = false;
watch(fmShownKeys, (now) => {
    if (!fmShownInit) {
        fmShownInit = true; fmShownPrev = now;
        const full: Record<string, number> = {};
        now.forEach((k) => { const c = cellFor(...k.split('-') as [string, string]); if (c?.text) full[k] = c.text.length; });
        fmTyped.value = { ...fmTyped.value, ...full };
        return;
    }
    let played = false;
    now.forEach((k) => {
        if (fmShownPrev.has(k)) return;
        const c = cellFor(...k.split('-') as [string, string]);
        if (c?.text) fmStartTypewriter(k, c.text);
        // One answer sting per batch — a live reveal adds one cell; flashing Player
        // 1's whole board during Player 2's turn adds all five, but stings just once.
        if (!played) { sounds.play('fastMoneyAnswerReveal'); played = true; }
    });
    fmShownPrev = now;
});

// Points scored → the points sting (or the zero sting for a 0 / no match). Skipped
// while Player 1's board is flashed during Player 2's capture — that's a reveal of
// the answers only, so it gets the answer sting above and no points sound.
let fmScoredPrev = new Set<string>();
let fmScoredInit = false;
watch(fmScoredKeys, (now) => {
    if (!fmScoredInit) { fmScoredInit = true; fmScoredPrev = now; return; }
    const bulkShowP1 = phase.value === 'fast_money_p2_capture';
    now.forEach((k) => {
        if (fmScoredPrev.has(k)) return;
        if (bulkShowP1) return;
        const c = cellFor(...k.split('-') as [string, string]);
        sounds.play((c?.points ?? 0) > 0 ? 'fastMoneyPointsReveal' : 'fastMoneyZeroPoints');
    });
    fmScoredPrev = now;
});

// Duplicate: the host tapped an answer Player 1 already used — the duplicate cue
// sounds so the player guesses again. Monotonic; the first value is adopted silently.
watch(() => fastMoney.value?.duplicate_buzz ?? null, (now, prev) => {
    if (now != null && prev != null && now > prev) sounds.play('duplicate');
});

// Fast Money timer stings: the host bumps a counter to sound each pass's clock.
const makeBuzzWatcher = (get: () => number | undefined, name: 'fastMoneyTimer1' | 'fastMoneyTimer2') => {
    watch(get, (now, prev) => {
        if (now == null || prev == null) return;
        if (now > prev) sounds.play(name);
    });
};
makeBuzzWatcher(() => props.gameState?.fast_money?.timer1_buzz, 'fastMoneyTimer1');
makeBuzzWatcher(() => props.gameState?.fast_money?.timer2_buzz, 'fastMoneyTimer2');

// --- Winner ------------------------------------------------------------------
const winningTeam = computed<Team | null>(() => {
    if (!props.teams.length) return null;
    return [...props.teams].sort(
        (a, b) => b.total_score - a.total_score || (a.display_order ?? 0) - (b.display_order ?? 0)
    )[0];
});

// Confetti rains only on a Fast Money win (Feud's "pass the final" moment). Built
// once so the pieces don't reshuffle on every poll; festive gold/red/blue.
const CONFETTI_COLORS = ['#ffd23f', '#ef2b1d', '#2a6df4', '#eaf1ff', '#59d0ff'];
const confettiPieces = Array.from({ length: 60 }, (_, i) => ({
    left: (i * 37) % 100,
    delay: -((i * 0.37) % 6),
    duration: 4.5 + ((i * 13) % 40) / 10,
    color: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
    size: 8 + (i % 4) * 3,
    drift: (i % 2 === 0 ? 1 : -1) * (6 + (i % 5) * 3),
    round: i % 3 === 0,
}));
const fmWon = computed(() => phase.value === 'fast_money_result' && fmResult.value === 'win');
// The winner sting on a Fast Money win (its own clip, cut from the intro's tail).
// Latched so it plays ONCE — when the host pops back to reveal leftover answers and
// then returns to the result slide, we don't re-fire the music/celebration cue.
let fmWinPlayed = false;
watch(fmWon, (won) => {
    if (won && !fmWinPlayed) { fmWinPlayed = true; sounds.play('win'); }
});

// ---- Back wall of light bulbs ----------------------------------------------
// A real grid of addressable bulbs (not a tiled gradient) so the wall can do a
// choreographed center-out RIPPLE during the opening theme and on a Fast Money
// win, instead of every bulb flashing at once. Colour follows the phase: warm
// YELLOW for the face-off/main rounds, BLUE for Fast Money — like the real show.
const BULB_SPACING = 52;      // px per bulb tile (matches the old wall's density)
const RIPPLE_SECONDS = 1.6;   // one center-out sweep; also the CSS animation length
const wallCols = ref(0);
const wallRows = ref(0);
const bulbs = ref<Array<{ '--tw': string; '--d': string }>>([]);

const buildBulbs = () => {
    const cols = Math.max(8, Math.round((window.innerWidth * 1.04) / BULB_SPACING));
    const rows = Math.max(6, Math.round((window.innerHeight * 1.04) / BULB_SPACING));
    const cx = (cols - 1) / 2;
    const cy = (rows - 1) / 2;
    const maxD = Math.hypot(cx, cy) || 1;
    const next: Array<{ '--tw': string; '--d': string }> = [];
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            // phase 0 at the center → 1 at the far corner drives the center-out sweep
            const phaseFrac = Math.hypot(c - cx, r - cy) / maxD;
            next.push({
                '--tw': (Math.random() * 4).toFixed(2) + 's',
                '--d': (-(phaseFrac * RIPPLE_SECONDS)).toFixed(3) + 's',
            });
        }
    }
    wallCols.value = cols;
    wallRows.value = rows;
    bulbs.value = next;
};

let wallResizeTimer: number | undefined;
const onWallResize = () => {
    if (wallResizeTimer) clearTimeout(wallResizeTimer);
    wallResizeTimer = window.setTimeout(buildBulbs, 200);
};

// The bulbs RIPPLE while the opening theme plays and on a Fast Money win; otherwise
// they sit in a calm twinkle. Standby (pre-game lobby) dims the whole wall.
const wallMode = computed(() => {
    if (isStandby.value) return 'standby';
    if (themeFlashing.value || fmWon.value) return 'ripple';
    return 'idle';
});
// Colour by phase: BLUE for Fast Money, warm YELLOW everywhere else.
const wallColor = computed(() => (isFastMoney.value ? 'blue' : 'yellow'));

onMounted(() => {
    // No gesture needed on the board: the Entry page's "Open board" tap already gave
    // this document sticky user-activation (Inertia doesn't reload), so unlock the
    // effect clips and prime the theme now. The music then rolls on its own when the
    // host starts the game. (Direct-to-board without Entry simply stays silent.)
    sounds.unlock();
    blessAudio(getTheme(), () => themePrimed);
    // If the board opens after the game is already live, start the theme immediately.
    if (props.status === 'playing') maybeStartTheme();
    // Fallback for a direct load / refresh (no Entry tap): the first interaction with
    // the board unlocks audio and rolls any pending theme.
    window.addEventListener('pointerdown', onFirstInteraction);
    window.addEventListener('keydown', onFirstInteraction);
    buildBulbs();
    window.addEventListener('resize', onWallResize);
    computeFmRemaining();
    fmTimerInterval = window.setInterval(computeFmRemaining, 200);
});

onUnmounted(() => {
    if (strikeFlashTimer) clearTimeout(strikeFlashTimer);
    if (decisionTimer) clearTimeout(decisionTimer);
    if (winMusicTimer) clearTimeout(winMusicTimer);
    if (fmTimerInterval) clearInterval(fmTimerInterval);
    if (wallResizeTimer) clearTimeout(wallResizeTimer);
    window.removeEventListener('pointerdown', onFirstInteraction);
    window.removeEventListener('keydown', onFirstInteraction);
    window.removeEventListener('resize', onWallResize);
    if (theme) { theme.pause(); theme = null; }
});
</script>

<template>
    <!-- Family Feud projector board — a replica of the real show's gameboard.
         Colors are intentionally off the Keeler palette (see the scoped styles):
         TV-blue lit set, glossy blue answer slots, gold proscenium, red strikes. -->
    <div class="ff-board" :class="{ 'ff-standby': isStandby, 'ff-theme-live': themeFlashing }">
        <!-- Back wall of light bulbs: a real grid of addressable dots so the wall can
             do a choreographed center-out ripple on the opening theme + Fast Money win
             (otherwise a calm twinkle). Colour follows the phase (yellow / blue). -->
        <div
            class="ff-wall"
            :class="[`ff-wall--${wallMode}`, `ff-wall--${wallColor}`]"
            :style="{ '--cols': wallCols, '--rows': wallRows }"
            aria-hidden="true"
        >
            <i v-for="(b, i) in bulbs" :key="i" class="ff-dot" :style="b"></i>
        </div>
        <!-- drifting light wash over the bulb wall -->
        <div class="ff-wallglow"></div>

        <!-- gold proscenium: one continuous ring bulging into a pod on each side,
             the blue score screen nested inside each pod. Shown on board phases. -->
        <svg
            v-show="boardVisible"
            class="ff-svg"
            viewBox="0 0 1620 880"
            preserveAspectRatio="xMidYMid meet"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="ffgold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#f3c145" />
                    <stop offset=".5" stop-color="#eab234" />
                    <stop offset="1" stop-color="#dda328" />
                </linearGradient>
                <!-- clean glossy blue for the score boxes (no bulb dots) -->
                <linearGradient id="ffscoreblue" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#5fb8ff" />
                    <stop offset=".5" stop-color="#2b86f0" />
                    <stop offset="1" stop-color="#1560d6" />
                </linearGradient>
                <path
                    id="ffring"
                    fill="none"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    d="M1490,350 L1600,350 L1600,530 L1490,530 A740,485 0 0 1 130,530 L20,530 L20,350 L130,350 A740,485 0 0 1 1490,350 Z"
                />
            </defs>
            <use href="#ffring" stroke="#6f420f" stroke-width="40" />
            <use href="#ffring" stroke="url(#ffgold)" stroke-width="32" />
            <use href="#ffring" stroke="#ffe27a" stroke-width="5" opacity=".55" />

            <!-- left score pod -->
            <g class="ff-scorewrap" :class="{ ctrl: leftTeam && holderId === leftTeam.id }"
               :style="leftTeam ? { '--tc': leftTeam.color } : undefined">
                <rect class="ff-scorebox" x="42" y="376" width="92" height="128" rx="9" fill="url(#ffscoreblue)" stroke="#0f3f9e" stroke-width="3" />
                <text class="ff-score" x="88" y="440" text-anchor="middle" dominant-baseline="central">{{ leftTeam?.total_score ?? 0 }}</text>
                <text class="ff-name" x="6" y="668" text-anchor="start" :style="leftTeam ? { fill: leftTeam.color } : undefined">{{ leftTeam?.name ?? '' }}</text>
                <text v-if="sideStatusLabel(leftTeam)" class="ff-pp" x="6" y="708" text-anchor="start">{{ sideStatusLabel(leftTeam) }}</text>
            </g>
            <!-- right score pod -->
            <g class="ff-scorewrap" :class="{ ctrl: rightTeam && holderId === rightTeam.id }"
               :style="rightTeam ? { '--tc': rightTeam.color } : undefined">
                <rect class="ff-scorebox" x="1486" y="376" width="92" height="128" rx="9" fill="url(#ffscoreblue)" stroke="#0f3f9e" stroke-width="3" />
                <text class="ff-score" x="1532" y="440" text-anchor="middle" dominant-baseline="central">{{ rightTeam?.total_score ?? 0 }}</text>
                <text class="ff-name" x="1614" y="668" text-anchor="end" :style="rightTeam ? { fill: rightTeam.color } : undefined">{{ rightTeam?.name ?? '' }}</text>
                <text v-if="sideStatusLabel(rightTeam)" class="ff-pp" x="1614" y="708" text-anchor="end">{{ sideStatusLabel(rightTeam) }}</text>
            </g>
        </svg>

        <!-- ===================== BOARD (faceoff / play / steal / reveal) ===================== -->
        <template v-if="status === 'playing' && !themeSlideOpen">
            <template v-if="boardVisible">
                <!-- survey question + round / multiplier -->
                <div class="ff-topbar">
                    <div class="ff-roundtag">
                        <span>Round {{ roundNumber }}</span>
                        <span v-if="multLabel" class="ff-mult">{{ multLabel }}</span>
                        <span v-if="inFaceoff" class="ff-facetag">Face-Off</span>
                    </div>
                    <div v-if="questionShown" class="ff-survey"><span>{{ currentQuestion?.question_text }}</span></div>
                </div>

                <!-- the answer board -->
                <div class="ff-boardwrap">
                    <div class="ff-pot"><span class="ff-potnum">{{ pot }}</span></div>
                    <div class="ff-frame">
                        <div class="ff-panel">
                            <div class="ff-slots">
                                <div
                                    v-for="slot in slots"
                                    :key="slot.key"
                                    class="ff-slot"
                                    :class="{ open: slot.open, empty: !slot.exists }"
                                >
                                    <div class="ff-inner">
                                        <div class="ff-face ff-blank">
                                            <span v-if="slot.exists" class="ff-num">{{ slot.number }}</span>
                                        </div>
                                        <div class="ff-face ff-reveal">
                                            <span class="ff-atext">{{ slot.text }}</span>
                                            <span class="ff-apts">{{ slot.points }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- strikes flash overlay -->
                    <div v-if="strikeFlash > 0" class="ff-strikes">
                        <div v-for="n in strikeFlash" :key="n" class="ff-xmark">&#10005;</div>
                    </div>
                </div>
                <!-- "Chance to Steal" shows under the stealing team's name (see the
                     score pods), consolidated with the other status labels. -->
            </template>

            <!-- ===================== FAST MONEY ===================== -->
            <!-- Intro: which team is up, before Player 1 starts. -->
            <div v-else-if="phase === 'fast_money_intro'" class="ff-center show">
                <div class="ff-eyebrow">Fast Money</div>
                <div v-if="winningTeam" class="ff-headline" :style="{ color: winningTeam.color }">{{ winningTeam.name }}</div>
                <div class="ff-subhead">{{ fmTarget }} to Win</div>
            </div>

            <!-- Result: the celebratory winner slide (confetti on a win) once Player 2's
                 last answer has been revealed. -->
            <div v-else-if="phase === 'fast_money_result'" class="ff-center show">
                <div v-if="fmWon" class="ff-confetti" aria-hidden="true">
                    <span
                        v-for="(p, pi) in confettiPieces"
                        :key="pi"
                        class="ff-confetti-piece"
                        :class="{ 'ff-confetti-round': p.round }"
                        :style="{
                            left: p.left + '%',
                            backgroundColor: p.color,
                            width: p.size + 'px',
                            height: p.size + 'px',
                            animationDelay: p.delay + 's',
                            animationDuration: p.duration + 's',
                            '--ff-drift': p.drift + 'vw',
                        }"
                    ></span>
                </div>
                <div class="ff-eyebrow">Fast Money</div>
                <template v-if="fmResult === 'win'">
                    <div v-if="winningTeam" class="ff-headline" :style="{ color: winningTeam.color }">{{ winningTeam.name }}</div>
                    <div class="ff-subhead ff-fmwin">Wins Fast Money!</div>
                </template>
                <div v-else class="ff-headline ff-fmlose">So Close!</div>
                <div class="ff-fmresult-score">{{ fmCombined }}</div>
            </div>

            <!-- Board (capture + reveal): two columns of answers — Player 1 down the
                 left, Player 2 down the right — each with a points box, and a TOTAL box.
                 Nothing shows during capture; the reveal types each answer in, then
                 flips its points up. -->
            <div v-else-if="isFastMoney" class="ff-center show">
                <div class="ff-eyebrow">
                    Fast Money
                    <template v-if="fmActivePlayer === 1"> · Player 1</template>
                    <template v-else> · Player 2</template>
                </div>
                <div v-if="fmTimerRunning" class="ff-fmtimer" :class="{ 'ff-fmtimer-warn': fmRemaining <= 5 }">{{ fmTimerDisplay }}</div>

                <div class="ff-fm2">
                    <div class="ff-fm2grid">
                        <!-- Player 1 column (left) -->
                        <div class="ff-fm2col">
                            <div
                                v-for="row in fmRows"
                                :key="'p1-' + row.id"
                                class="ff-fm2cell"
                                :class="{ 'ff-fm2cell-on': fmShowP1 && row.p1.shown }"
                            >
                                <span class="ff-fm2text">{{ fmShowP1 ? fmText(row.id, 1, row.p1) : '' }}</span>
                                <span class="ff-fm2pts">{{ fmShowP1 && row.p1.scored ? row.p1.points : '' }}</span>
                            </div>
                        </div>
                        <!-- Player 2 column (right) -->
                        <div class="ff-fm2col">
                            <div
                                v-for="row in fmRows"
                                :key="'p2-' + row.id"
                                class="ff-fm2cell"
                                :class="{ 'ff-fm2cell-on': fmShowP2 && row.p2.shown }"
                            >
                                <span class="ff-fm2text">{{ fmShowP2 ? fmText(row.id, 2, row.p2) : '' }}</span>
                                <span class="ff-fm2pts">{{ fmShowP2 && row.p2.scored ? row.p2.points : '' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="ff-fm2total"><span class="ff-fm2totall">TOTAL</span> <span class="ff-fm2totaln">{{ fmCombined }}</span></div>
                </div>
            </div>

            <!-- ===================== GAME WINNER (target reached at recap) ===================== -->
            <div v-else-if="gameWon" class="ff-center show">
                <div class="ff-eyebrow">Game Winner</div>
                <div v-if="winningTeam" class="ff-headline" :style="{ color: winningTeam.color }">{{ winningTeam.name }}</div>
                <div class="ff-subhead">Wins the Game!</div>
                <div class="ff-chips">
                    <div v-for="team in orderedTeams" :key="team.id" class="ff-chip" :style="{ '--tc': team.color }">
                        <span class="ff-cn" :style="{ color: team.color }">{{ team.name }}</span>
                        <span class="ff-cp">{{ team.total_score }}</span>
                    </div>
                </div>
            </div>

            <!-- ===================== FACE-OFF SLIDE (round intro) =========================
                 "Round N · Face-Off", the matchup, and "One Player From Each Team" — with
                 the live scores UNDER each team until the host arms the face-off (Start
                 Face-Off), when the scores FADE (their space is kept, so the matchup
                 doesn't shift) and the sting plays, then the board comes up. -->
            <div v-else-if="phase === 'intro'" class="ff-center show">
                <div class="ff-eyebrow">Round {{ roundNumber }} &middot; Face-Off</div>
                <div v-if="orderedTeams.length" class="ff-matchup">
                    <template v-for="(team, ti) in orderedTeams" :key="team.id">
                        <span v-if="ti > 0" class="ff-vs">vs</span>
                        <span class="ff-mwrap">
                            <span class="ff-m" :style="{ color: team.color }">{{ team.name }}</span>
                            <!-- keep the score's slot when armed so nothing jumps -->
                            <span class="ff-mscore" :style="{ visibility: faceoffSlideScores ? 'visible' : 'hidden' }">{{ team.total_score }}</span>
                        </span>
                    </template>
                </div>
                <div class="ff-subhead">One Player From Each Team</div>
            </div>

            <!-- ===================== BETWEEN-ROUNDS RECAP (Step 5, before Next Round) ======
                 The end-of-round hold: the Family Feud logo over each team's name + its
                 running score. Only once the host clicks Next Round (→ 'intro') does the
                 face-off matchup slide above come up. -->
            <div v-else-if="phase === 'recap'" class="ff-center show">
                <div class="ff-logo"><span class="ff-logotext"><span class="ff-l1">Family</span><span class="ff-l2">Feud</span></span></div>
                <div v-if="orderedTeams.length" class="ff-chips">
                    <div v-for="team in orderedTeams" :key="team.id" class="ff-chip" :style="{ '--tc': team.color }">
                        <span class="ff-cn" :style="{ color: team.color }">{{ team.name }}</span>
                        <span class="ff-cp">{{ team.total_score }}</span>
                    </div>
                </div>
            </div>

            <!-- Fallback: a phase with no question yet -->
            <div v-else class="ff-center show">
                <div class="ff-logo"><span class="ff-logotext"><span class="ff-l1">Family</span><span class="ff-l2">Feud</span></span></div>
            </div>
        </template>

        <!-- ===================== LOBBY ===================== -->
        <div v-else-if="status === 'lobby'" class="ff-center show">
            <div class="ff-logo"><span class="ff-logotext"><span class="ff-l1">Family</span><span class="ff-l2">Feud</span></span></div>
            <div v-if="orderedTeams.length" class="ff-matchup">
                <template v-for="(team, ti) in orderedTeams" :key="team.id">
                    <span v-if="ti > 0" class="ff-vs">vs</span>
                    <span class="ff-m" :style="{ color: team.color }">{{ team.name }}</span>
                </template>
            </div>
            <div class="ff-subhead">Waiting to Start</div>
        </div>

        <!-- ===================== PAUSED ===================== -->
        <div v-else-if="status === 'paused'" class="ff-center show">
            <div class="ff-headline">Paused</div>
        </div>

        <!-- ===================== GAME OVER (winner) ===================== -->
        <div v-else-if="status === 'completed'" class="ff-center show">
            <div class="ff-eyebrow">Game Over</div>
            <div v-if="winningTeam" class="ff-headline" :style="{ color: winningTeam.color }">{{ winningTeam.name }}</div>
            <div class="ff-subhead">Wins!</div>
            <div class="ff-chips">
                <div
                    v-for="team in orderedTeams"
                    :key="team.id"
                    class="ff-chip"
                    :style="{ '--tc': team.color }"
                >
                    <span class="ff-cn" :style="{ color: team.color }">{{ team.name }}</span>
                    <span class="ff-cp">{{ team.total_score }}</span>
                </div>
            </div>
        </div>

        <!-- Theme slide: FF logo + matchup while the theme sting plays, then it
             dismisses itself (on the sting's 'ended') to reveal gameplay. -->
        <div v-if="themeSlideOpen" class="ff-center show ff-theme">
            <div class="ff-logo"><span class="ff-logotext"><span class="ff-l1">Family</span><span class="ff-l2">Feud</span></span></div>
            <div v-if="orderedTeams.length" class="ff-matchup">
                <template v-for="(team, ti) in orderedTeams" :key="team.id">
                    <span v-if="ti > 0" class="ff-vs">vs</span>
                    <span class="ff-m" :style="{ color: team.color }">{{ team.name }}</span>
                </template>
            </div>
        </div>
        <!-- Skip Intro only while the theme is actually playing; once the music ends
             the curtain holds silently for the face-off, so there's nothing to skip. -->
        <button v-if="themeSlideOpen && !themeMusicDone" type="button" class="ff-skip" @click="skipTheme">Skip Intro &rarr;</button>

        <!-- Only when the browser actually blocked autoplay (a direct load / refresh
             with no gesture): a subtle prompt. Any tap on the board also unlocks. -->
        <button v-if="audioBlocked && status === 'playing'" type="button" class="ff-soundhint" @click="tryUnlockAndPlay">
            &#128266;&nbsp; Tap for sound
        </button>
    </div>
</template>

<style scoped>
/* =====================================================================
   Family Feud projector board. Colors here are intentionally OFF the
   Keeler palette — this board is a replica of the real show's gameboard
   (a sanctioned exception, projector-only, nothing sold). Ported from
   docs/specs/family-feud-authentic.html.
   ===================================================================== */
.ff-board {
    --rim-soft: #8fe6ff;
    --led: #ffd23f;
    --ink: #f2f7ff;
    --strike: #ef2b1d;
    position: relative;
    width: 100%;
    height: 100vh;
    overflow: hidden;
    font-family: "Arial Narrow", "Helvetica Neue", Arial, sans-serif;
    background:
        repeating-linear-gradient(90deg, rgba(160, 120, 45, .10) 0 2px, rgba(0, 0, 0, 0) 2px 96px),
        linear-gradient(90deg, rgba(180, 130, 50, .55) 0%, rgba(34, 25, 14, 0) 18%, rgba(34, 25, 14, 0) 82%, rgba(180, 130, 50, .55) 100%),
        radial-gradient(120% 100% at 50% 40%, #362a18 0%, #201810 56%, #120c07 100%);
}
/* Full back WALL of light bulbs — a real grid of addressable dots (so it can chase),
   faded toward the edges. Colour is set per phase via the .ff-wall--yellow/blue
   classes; the twinkle/ripple motion via .ff-wall--idle/ripple/standby. */
.ff-wall {
    position: absolute;
    inset: -2%;
    z-index: 0;
    pointer-events: none;
    display: grid;
    grid-template-columns: repeat(var(--cols, 30), 1fr);
    grid-template-rows: repeat(var(--rows, 18), 1fr);
    place-items: center;
    -webkit-mask-image: radial-gradient(135% 122% at 50% 42%, #000 60%, rgba(0, 0, 0, .5) 84%, transparent 100%);
    mask-image: radial-gradient(135% 122% at 50% 42%, #000 60%, rgba(0, 0, 0, .5) 84%, transparent 100%);
}
.ff-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: radial-gradient(circle at 45% 40%, var(--c-core), var(--c-mid) 60%, var(--c-rim) 88%);
    will-change: opacity, transform, background, box-shadow;
}
/* phase colours: warm YELLOW for the rounds, BLUE for Fast Money */
.ff-wall--yellow { --c-core: #fff3cf; --c-mid: #ffbf45; --c-rim: rgba(150, 90, 20, .42); --c-on: #fff0c8; --c-glow1: #ffcf4a; --c-glow2: #ff9d1c; }
.ff-wall--blue   { --c-core: #dff0ff; --c-mid: #46a0ff; --c-rim: rgba(20, 72, 178, .40); --c-on: #cfe4ff; --c-glow1: #79bbff; --c-glow2: #1f6adb; }
/* calm resting twinkle */
.ff-wall--idle .ff-dot { animation: ff-twinkle 4s ease-in-out infinite; animation-delay: var(--tw); }
/* center-out ripple: each dot fires on a delay set from its distance to center */
.ff-wall--ripple .ff-dot { animation: ff-ripple 1.6s linear infinite; animation-delay: var(--d); }
/* standby: whole wall dimmed until the host starts the game */
.ff-wall--standby .ff-dot { opacity: .12; filter: brightness(.6) saturate(.6); }

@keyframes ff-twinkle { 0%, 100% { opacity: .7; } 50% { opacity: .95; } }
@keyframes ff-ripple {
    0%   { background: radial-gradient(circle at 45% 40%, var(--c-core), var(--c-mid) 60%, var(--c-rim) 88%); box-shadow: none; transform: scale(1); }
    9%   { background: radial-gradient(circle at 44% 38%, #fff, var(--c-on) 50%, var(--c-glow1) 86%);
           box-shadow: 0 0 9px var(--c-glow1), 0 0 17px var(--c-glow2); transform: scale(1.6); }
    34%, 100% { background: radial-gradient(circle at 45% 40%, var(--c-core), var(--c-mid) 60%, var(--c-rim) 88%); box-shadow: none; transform: scale(1); }
}
/* blue stage-floor glow */
.ff-board::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 30%;
    background: linear-gradient(0deg, rgba(60, 110, 255, .40), rgba(60, 110, 255, 0) 100%);
    filter: blur(10px);
    pointer-events: none;
}
.ff-wallglow {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    mix-blend-mode: screen;
    background: radial-gradient(62% 52% at 50% 44%, rgba(40, 95, 210, .3), transparent 76%);
    animation: ff-drift 14s ease-in-out infinite alternate;
}
@keyframes ff-drift { 0% { transform: translate3d(-1%, 0, 0); } 100% { transform: translate3d(1%, 1%, 0); } }

/* Standby: before the host starts the game the set sits dark — floor wash and wall
   glow off (the bulbs themselves are dimmed via .ff-wall--standby) until Start Game. */
.ff-standby::after { opacity: .12; }
.ff-standby .ff-wallglow { opacity: 0; }

/* Start Game → opening theme: the drifting wall wash warms to gold while the theme
   plays (the bulbs do the center-out ripple; see .ff-wall--ripple), then settles back
   the instant the music stops (themeFlashing → false). */
.ff-theme-live .ff-wallglow {
    background: radial-gradient(62% 52% at 50% 44%, rgba(255, 215, 60, .55), transparent 74%);
    animation: ff-introglow .26s steps(2, jump-none) infinite;
}
@keyframes ff-introglow {
    0%, 100% { opacity: .35; }
    50%      { opacity: 1; }
}

/* gold proscenium */
.ff-svg {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: min(94vw, 1620px);
    aspect-ratio: 1620 / 880;
    z-index: 1;
    pointer-events: none;
    filter: drop-shadow(0 0 30px rgba(240, 180, 60, .4)) drop-shadow(0 16px 40px rgba(0, 0, 0, .5));
    overflow: visible;
}
.ff-score {
    fill: #fff;
    font-weight: 900;
    font-size: 56px;
    paint-order: stroke;
    stroke: #081026;
    stroke-width: 4px;
    stroke-linejoin: round;
}
.ff-name {
    fill: #fff;
    font-weight: 900;
    font-size: 30px;
    text-transform: uppercase;
    letter-spacing: .5px;
    paint-order: stroke;
    stroke: #0a0a12;
    stroke-width: 5px;
    stroke-linejoin: round;
}
/* Play/Pass label, in white just under the deciding team's name. */
.ff-pp {
    fill: #fff;
    font-weight: 800;
    font-size: 24px;
    text-transform: uppercase;
    letter-spacing: .06em;
    paint-order: stroke;
    stroke: #0a0a12;
    stroke-width: 4px;
    stroke-linejoin: round;
}
/* Active/controlling team — highlighted from the moment the host picks who buzzed
   in. Its score pod gets a bright, gently pulsing halo and its name lights up so
   it's unmistakable which family is guessing. */
.ff-scorewrap.ctrl .ff-scorebox {
    stroke: var(--tc, #4bd6ff);
    stroke-width: 8px;
    animation: ff-ctrl-pulse 1.2s ease-in-out infinite;
}
.ff-scorewrap.ctrl .ff-name {
    filter: drop-shadow(0 0 10px var(--tc, #4bd6ff));
}
@keyframes ff-ctrl-pulse {
    0%, 100% { filter: drop-shadow(0 0 8px var(--tc, #4bd6ff)) drop-shadow(0 0 16px var(--tc, #4bd6ff)); }
    50%      { filter: drop-shadow(0 0 15px var(--tc, #4bd6ff)) drop-shadow(0 0 32px var(--tc, #4bd6ff)); }
}

/* ---- Top bar: survey banner + round / multiplier ---------------------- */
.ff-topbar {
    position: absolute;
    top: 3.2%;
    left: 50%;
    transform: translateX(-50%);
    width: 78%;
    max-width: 1180px;
    z-index: 12;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.1vh;
}
.ff-roundtag {
    display: flex;
    align-items: center;
    gap: .6em;
    font-weight: 800;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--rim-soft);
    font-size: clamp(12px, 1.4vw, 22px);
    text-shadow: 0 2px 8px rgba(0, 0, 0, .6);
}
.ff-mult {
    color: #04102e;
    background: linear-gradient(180deg, #ffe27a, var(--led));
    padding: .12em .7em;
    border-radius: 999px;
    font-weight: 900;
    letter-spacing: .04em;
    box-shadow: 0 0 16px rgba(255, 210, 63, .6);
}
/* Face-off marker on the round tag while both teams square off. */
.ff-facetag {
    color: #fff;
    background: linear-gradient(180deg, #ff6a3d, #ef2b1d);
    padding: .12em .7em;
    border-radius: 999px;
    font-weight: 900;
    letter-spacing: .06em;
    box-shadow: 0 0 16px rgba(239, 43, 29, .55);
    animation: ff-facetag-pulse 1.1s ease-in-out infinite;
}
@keyframes ff-facetag-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.06); } }
.ff-survey {
    width: 100%;
    text-align: center;
    color: #fff;
    font-weight: 800;
    line-height: 1.12;
    text-transform: uppercase;
    letter-spacing: .01em;
    font-size: clamp(18px, 2.5vw, 42px);
    text-shadow: 0 2px 4px rgba(0, 0, 0, .6);
    padding: 1.1vh 2vw;
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(10, 26, 94, .72), rgba(6, 16, 64, .72));
    border: 2px solid rgba(120, 180, 255, .35);
    box-shadow: inset 0 0 30px rgba(60, 120, 255, .25), 0 10px 30px rgba(0, 0, 0, .45);
}

/* ---- The answer board ------------------------------------------------- */
.ff-boardwrap {
    position: absolute;
    left: 50%;
    top: 47%;
    transform: translate(-50%, -50%);
    width: min(58vw, 1000px);
    z-index: 10;
}
.ff-frame {
    position: relative;
    padding: clamp(7px, 1vw, 15px);
    border-radius: 16px;
    background: linear-gradient(180deg, #f3c145, #e2a62c);
    border: 2px solid #6f440f;
    box-shadow: 0 0 24px rgba(255, 200, 70, .4), 0 14px 36px rgba(0, 0, 0, .6),
        inset 0 0 0 2px rgba(255, 244, 180, .85), inset 0 -2px 5px rgba(0, 0, 0, .35);
}
.ff-panel {
    position: relative;
    border-radius: 10px;
    padding: clamp(8px, 1.05vw, 16px);
    background: linear-gradient(180deg, #0c0f1a, #05070f);
    border: 2px solid #000;
    box-shadow: inset 0 0 34px rgba(0, 0, 0, .75);
}
.ff-pot {
    width: max-content;
    margin: 0 auto clamp(8px, 1.5vh, 20px);
    padding: clamp(4px, .5vw, 8px);
    border-radius: 9px;
    background: linear-gradient(180deg, #9096a1, #565c66);
    border: 1px solid #33383f;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .55), inset 0 1px 2px rgba(255, 255, 255, .55);
}
.ff-potnum {
    display: block;
    min-width: 1.8em;
    text-align: center;
    padding: .04em .42em;
    border-radius: 5px;
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    letter-spacing: .02em;
    font-size: clamp(30px, 3.7vw, 66px);
    background: linear-gradient(180deg, #2f8bf2, #1560d6);
    border: 1px solid #0f3f9e;
    text-shadow: 0 2px 6px rgba(0, 0, 0, .6);
}
.ff-slots {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: clamp(6px, .85vw, 13px);
}
.ff-slot {
    position: relative;
    height: clamp(40px, 6.6vh, 80px);
    border-radius: 7px;
    perspective: 900px;
}
.ff-inner {
    position: absolute;
    inset: 0;
    transition: transform .6s cubic-bezier(.34, 1.2, .5, 1);
    transform-style: preserve-3d;
}
.ff-slot.open .ff-inner { transform: rotateX(180deg); }
.ff-face {
    position: absolute;
    inset: 0;
    border-radius: 6px;
    backface-visibility: hidden;
    display: flex;
    align-items: center;
    overflow: hidden;
    border: clamp(2px, .32vw, 5px) solid #e3ecf6;
    box-shadow: 0 0 0 1.5px #04060d,
        inset 0 0 0 2px rgba(24, 52, 110, .75),
        inset 0 5px 9px rgba(255, 255, 255, .5), inset 0 -9px 13px rgba(0, 10, 40, .42);
}
/* glossy blue slot fill (shared by blank + revealed faces) */
.ff-blank, .ff-reveal {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, .5) 0%, rgba(255, 255, 255, 0) 16%),
        radial-gradient(120% 85% at 42% 40%, #5fb8ff 0%, #2b86f0 44%, #1560d6 100%);
}
.ff-blank { justify-content: center; }
/* empty (numberless) panels have no badge */
.ff-slot.empty .ff-num { display: none; }
.ff-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: clamp(46px, 6.2vw, 88px);
    height: clamp(28px, 3.9vh, 52px);
    border-radius: 50%;
    font-weight: 900;
    color: #fff;
    font-size: clamp(18px, 2.5vw, 40px);
    background: radial-gradient(120% 120% at 44% 32%, #3a72dc 0%, #0f327e 78%);
    border: 2px solid #cfe6ff;
    box-shadow: 0 6px 7px rgba(0, 0, 0, .5), inset 0 2px 3px rgba(255, 255, 255, .5),
        inset 0 -3px 6px rgba(0, 0, 0, .5);
    text-shadow: 0 1px 2px rgba(0, 0, 0, .6);
}
.ff-reveal { transform: rotateX(180deg); }
.ff-atext {
    flex: 1 1 auto;
    padding: 0 .35em 0 .7em;
    color: #fff;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: clamp(15px, 2vw, 34px);
    text-shadow: 0 2px 3px rgba(0, 0, 0, .6), 0 0 2px rgba(0, 0, 0, .5);
}
.ff-apts {
    flex: 0 0 auto;
    align-self: stretch;
    min-width: 1.7em;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(17px, 2.2vw, 38px);
    background: radial-gradient(120% 90% at 40% 38%, #86ccff, #3a93f4);
    border-left: 3px solid rgba(4, 6, 13, .7);
    box-shadow: inset 2px 0 0 rgba(160, 215, 255, .7);
    text-shadow: 0 2px 3px rgba(0, 0, 0, .5);
}

/* ---- Strikes overlay -------------------------------------------------- */
.ff-strikes {
    position: absolute;
    inset: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(14px, 3vw, 60px);
    animation: ff-pop .25s ease-out;
}
.ff-xmark {
    font-weight: 900;
    color: var(--strike);
    font-size: clamp(90px, 18vw, 300px);
    line-height: .8;
    width: 1.05em;
    height: 1.05em;
    display: flex;
    align-items: center;
    justify-content: center;
    border: .11em solid var(--strike);
    border-radius: .14em;
    background: transparent;
    box-shadow:
        inset 0 0 0 .02em rgba(255, 205, 205, .95),
        inset 0 0 .12em .01em rgba(255, 150, 150, .8),
        0 0 .18em rgba(255, 120, 120, .7),
        0 0 50px rgba(239, 43, 29, .75);
    text-shadow: 0 0 .06em rgba(255, 190, 190, .9), 0 0 22px rgba(239, 43, 29, .9);
}
@keyframes ff-pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* Steal, round-win, and Play/Pass status all render as a single white line under
   the deciding/active team's name on the board (see .ff-pp / sideStatusLabel). */

/* ---- Centered overlays (lobby / recap / winner / fast money) ---------- */
.ff-center {
    position: absolute;
    inset: 0;
    z-index: 15;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3.5vh;
    text-align: center;
    padding: 4vw;
}
.ff-center.show { display: flex; }

/* Fast Money win celebration: confetti raining continuously behind the board. */
.ff-confetti {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: -1;
}
.ff-confetti-piece {
    position: absolute;
    top: 0;
    border-radius: 2px;
    opacity: 0.92;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.35);
    animation-name: ff-confetti-fall;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    will-change: transform;
}
.ff-confetti-round { border-radius: 50%; }
@keyframes ff-confetti-fall {
    0% { transform: translate3d(0, -12vh, 0) rotate(0deg); }
    100% { transform: translate3d(var(--ff-drift, 0), 112vh, 0) rotate(720deg); }
}

.ff-eyebrow {
    color: var(--led);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .16em;
    font-size: clamp(16px, 2vw, 36px);
    text-shadow: 0 2px 8px rgba(0, 0, 0, .6);
}
.ff-headline {
    color: #fff;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-size: clamp(40px, 7vw, 120px);
    text-shadow: 0 0 30px rgba(120, 190, 255, .7), 0 4px 10px rgba(0, 0, 0, .6);
}
.ff-subhead {
    color: var(--ink);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .1em;
    font-size: clamp(20px, 2.6vw, 46px);
    text-shadow: 0 0 16px rgba(120, 190, 255, .5);
}
.ff-matchup {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: clamp(14px, 2.4vw, 40px);
}
.ff-m {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-size: clamp(26px, 3.8vw, 66px);
    text-shadow: 0 0 16px rgba(120, 190, 255, .4), 0 2px 6px rgba(0, 0, 0, .6);
}
/* A team column in the face-off matchup: name over its live score (the score drops
   when the host arms the face-off). */
.ff-mwrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .1em;
}
.ff-mscore {
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    font-size: clamp(24px, 3.4vw, 58px);
    text-shadow: 0 0 16px rgba(150, 200, 255, .5), 0 2px 6px rgba(0, 0, 0, .6);
}
.ff-vs {
    color: var(--ink);
    opacity: .7;
    font-weight: 700;
    font-size: clamp(16px, 2vw, 34px);
}
.ff-chips {
    display: flex;
    flex-wrap: wrap;
    gap: clamp(16px, 3vw, 48px);
    justify-content: center;
}
.ff-chip {
    display: flex;
    flex-direction: column;
    gap: .4vh;
    align-items: center;
    padding: 1.6vh 2.6vw;
    border-radius: 18px;
    background: rgba(6, 16, 64, .62);
    border: 3px solid var(--tc, #4bd6ff);
    box-shadow: 0 0 24px rgba(75, 214, 255, .35);
}
.ff-cn {
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: clamp(18px, 2vw, 34px);
}
.ff-cp {
    font-weight: 900;
    color: #fff;
    font-variant-numeric: tabular-nums;
    font-size: clamp(34px, 4.4vw, 74px);
    text-shadow: 0 0 16px rgba(150, 200, 255, .5);
}

/* Family Feud show lockup — orange/yellow serif wordmark on a dotted navy oval
   badge ringed by gold + chrome (the "badge behind it"). */
.ff-logo {
    position: relative;
    display: inline-grid;
    place-items: center;
    padding: clamp(30px, 6vw, 84px) clamp(64px, 13vw, 210px);
}
.ff-logo::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background:
        radial-gradient(circle, rgba(150, 185, 245, .55) 0 1.3px, transparent 2.1px) 0 0/17px 17px,
        radial-gradient(125% 125% at 50% 28%, #5474c6 0%, #33529f 55%, #274088 100%);
    box-shadow:
        inset 0 0 0 clamp(2px, .28vw, 5px) rgba(190, 215, 255, .85),
        0 0 0 clamp(4px, .55vw, 9px) #d5852a,
        0 0 0 clamp(9px, 1.15vw, 19px) #b3c4e0,
        0 0 0 clamp(10px, 1.3vw, 21px) #5f6f8e,
        0 0 34px rgba(80, 120, 220, .4),
        inset 0 8px 20px rgba(255, 255, 255, .28), inset 0 -14px 32px rgba(0, 0, 0, .42);
}
.ff-logotext {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: .84;
    filter: drop-shadow(2px 3px 1px rgba(0, 0, 0, .5)) drop-shadow(1px 2px 0 #9c4418);
}
.ff-l1, .ff-l2 {
    font-family: Georgia, "Times New Roman", serif;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1;
    letter-spacing: .01em;
    background-image: linear-gradient(180deg, #ef7a0e 0%, #ff9c1a 30%, #ffc21e 62%, #ffe83e 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.ff-l1 { font-size: clamp(30px, 5vw, 86px); }
.ff-l2 { font-size: clamp(34px, 5.8vw, 100px); margin-top: -.02em; }

/* ---- Fast Money board ------------------------------------------------- */
/* Fast Money board — a replica of the real show's Fast Money screen: the two
   columns of answers sit inside a big glossy BLUE panel, each answer/points cell
   a BLACK box with white text, and a black TOTAL box bottom-right. Cells sit
   empty (black) until the host reveals them. */
.ff-fm2 {
    width: min(1180px, 94vw);
    display: flex;
    flex-direction: column;
    gap: clamp(10px, 1.6vh, 22px);
    padding: clamp(16px, 2.4vw, 44px);
    border-radius: clamp(16px, 1.8vw, 30px);
    /* glossy blue set panel: bright sheen up top over a deep TV-blue body */
    background:
        linear-gradient(180deg, rgba(255, 255, 255, .30) 0%, rgba(255, 255, 255, 0) 24%),
        radial-gradient(130% 100% at 50% 26%, #46a2ff 0%, #1f79ea 46%, #0f55c6 100%);
    border: clamp(5px, .6vw, 11px) solid #0a2a63;
    box-shadow:
        0 0 0 2px rgba(150, 200, 255, .55),
        0 22px 54px rgba(0, 0, 0, .58),
        inset 0 2px 7px rgba(255, 255, 255, .55),
        inset 0 -12px 34px rgba(3, 18, 58, .6);
}
.ff-fm2grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: clamp(10px, 1.4vw, 26px);
}
.ff-fm2col {
    display: flex;
    flex-direction: column;
    gap: clamp(6px, 1vh, 13px);
}
.ff-fm2cell {
    display: grid;
    grid-template-columns: 1fr clamp(52px, 4.2vw, 92px);
    /* A DEFINITE row track — every cell is exactly this tall in BOTH columns. */
    grid-auto-rows: clamp(44px, 5.4vh, 64px);
    gap: clamp(5px, .5vw, 10px);
    align-items: stretch;
    /* Don't let the flex column resize the cell, and (crucially) kill the flex-item
       min-content floor: without this the points number (a larger font) grows the
       FILLED left cells past the row height while the empty right cells stay put —
       that was the left/right misalignment. */
    flex: none;
    min-height: 0;
}
.ff-fm2text {
    display: flex;
    align-items: center;
    min-width: 0;
    /* content must not push the row taller than its track */
    min-height: 0;
    padding: 0 .7em;
    border-radius: 6px;
    background: #000;
    border: 2px solid #b8c6d8;
    color: #fff;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: clamp(14px, 1.7vw, 32px);
    text-shadow: 0 1px 2px rgba(0, 0, 0, .8);
}
.ff-fm2pts {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    /* same: the number must not expand the row past its track */
    min-height: 0;
    overflow: hidden;
    border-radius: 6px;
    background: #000;
    border: 2px solid #b8c6d8;
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(18px, 2.2vw, 38px);
    text-shadow: 0 1px 2px rgba(0, 0, 0, .8);
}
/* A revealed cell brightens its hairline rim. */
.ff-fm2cell-on .ff-fm2text,
.ff-fm2cell-on .ff-fm2pts {
    border-color: #fff;
}
.ff-fm2total {
    align-self: flex-end;
    display: inline-flex;
    align-items: center;
    gap: .6em;
    padding: .22em .8em;
    border-radius: 6px;
    background: #000;
    border: 2px solid #b8c6d8;
}
.ff-fm2totall {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #fff;
    font-size: clamp(18px, 2.2vw, 40px);
}
.ff-fm2totaln {
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(28px, 3.6vw, 64px);
    text-shadow: 0 1px 2px rgba(0, 0, 0, .8);
}
/* Result slide score. */
.ff-fmresult-score {
    color: var(--led);
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(40px, 6vw, 110px);
    text-shadow: 0 0 26px rgba(255, 210, 63, .7);
}
/* Fast Money pass clock. */
.ff-fmtimer {
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    letter-spacing: .04em;
    color: var(--led);
    font-size: clamp(34px, 5vw, 84px);
    text-shadow: 0 0 20px rgba(255, 210, 63, .6);
}
.ff-fmtimer-warn { color: var(--strike); text-shadow: 0 0 22px rgba(239, 43, 29, .8); animation: ff-fmpulse 1s ease-in-out infinite; }
@keyframes ff-fmpulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
.ff-fmwin { color: var(--led); text-shadow: 0 0 30px rgba(255, 210, 63, .8); }
.ff-fmlose { color: var(--rim-soft); }

/* Theme slide: FF logo + matchup over a soft scrim while the sting plays. */
.ff-theme {
    z-index: 35;
    background: radial-gradient(120% 80% at 50% 45%, rgba(6, 12, 40, .55), rgba(2, 3, 12, .85));
    animation: ff-fade .4s ease-out;
}
@keyframes ff-fade { from { opacity: 0; } to { opacity: 1; } }

/* "Skip Intro" pill — bottom-right over the theme curtain. */
.ff-skip {
    position: absolute;
    right: 2.8%;
    bottom: 5%;
    z-index: 40;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-size: clamp(14px, 1.4vw, 22px);
    color: #eaf1ff;
    padding: .5em 1.3em;
    border-radius: 999px;
    background: rgba(6, 12, 40, .72);
    border: 2px solid var(--led);
    box-shadow: 0 0 22px rgba(80, 130, 255, .45), inset 0 0 12px rgba(0, 0, 0, .4);
    cursor: pointer;
    transition: transform .15s ease, background .15s ease;
}
.ff-skip:hover { background: rgba(20, 34, 94, .9); transform: scale(1.04); }

/* "Tap for sound" — subtle bottom-center prompt shown only when autoplay was
   blocked (no gesture on this page). Any tap on the board unlocks anyway. */
.ff-soundhint {
    position: absolute;
    left: 50%;
    bottom: 5%;
    transform: translateX(-50%);
    z-index: 40;
    font-weight: 800;
    letter-spacing: .04em;
    font-size: clamp(13px, 1.3vw, 20px);
    color: #eaf1ff;
    padding: .5em 1.2em;
    border-radius: 999px;
    background: rgba(6, 12, 40, .72);
    border: 2px solid var(--led);
    box-shadow: 0 0 22px rgba(80, 130, 255, .45), inset 0 0 12px rgba(0, 0, 0, .4);
    cursor: pointer;
    animation: ff-facetag-pulse 1.4s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .ff-inner { transition: none; }
    .ff-scorewrap.ctrl .ff-scorebox, .ff-facetag { animation: none; }
    /* Keep a steady halo on the active team when the pulse is disabled. */
    .ff-scorewrap.ctrl .ff-scorebox { filter: drop-shadow(0 0 14px var(--tc, #4bd6ff)); }
    .ff-confetti-piece { animation: none; display: none; }
    /* No motion: hold the bulbs steady — lit for the ripple moments, calm otherwise. */
    .ff-wall--idle .ff-dot { animation: none; }
    .ff-wall--ripple .ff-dot {
        animation: none;
        background: radial-gradient(circle at 44% 38%, #fff, var(--c-on) 50%, var(--c-glow1) 86%);
        box-shadow: 0 0 9px var(--c-glow1);
    }
    .ff-theme-live .ff-wallglow { animation: none; opacity: .8; }
}
</style>
