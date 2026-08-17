<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useSoundEffects } from '@/composables/useSoundEffects';
import Button from '@/Components/Base/Button.vue';

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
    // Fast Money board data (optional; see FastMoney above).
    fast_money?: FastMoney | null;
    // Family Feud: a team reached the target (300) — the recap becomes the
    // "Team X Wins the game" celebration before Fast Money.
    feud_target_reached?: boolean;
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

// Theme slide: FF logo + team matchup shown while the theme plays, then it
// dismisses itself when the sting ends. Fires ONCE, the moment BOTH the sound
// check is done (audio unlocked) AND the game has started.
const soundCheckDone = ref(false);
const themeSlideOpen = ref(false);
let themeStarted = false;

const startTheme = () => {
    if (themeStarted) return;
    themeStarted = true;
    const t = getTheme();
    themeSlideOpen.value = true;
    let done = false;
    let safety = 0;
    const finish = () => {
        if (done) return;
        done = true;
        if (safety) clearTimeout(safety);
        t.pause();
        themeSlideOpen.value = false;
    };
    // The theme plays to its natural end, which dismisses the curtain.
    t.addEventListener('ended', finish, { once: true });
    t.addEventListener('error', finish, { once: true });
    t.currentTime = 0;
    themePrimed = true;
    const p = t.play();
    if (p) p.catch(() => finish());
    // Safety net if the audio stalls without firing 'ended'/'error' (Intro.m4a is
    // ~15.4s; keep this comfortably past that so it never clips a healthy play).
    safety = window.setTimeout(finish, 20000);
};

const maybeStartTheme = () => {
    if (soundCheckDone.value && props.status === 'playing') startTheme();
};

// Skip Intro: cut the theme and drop the curtain immediately.
const skipTheme = () => {
    if (theme) {
        theme.pause();
        theme.currentTime = 0;
    }
    themeSlideOpen.value = false;
};

// The sound-check panel shows on load. It doubles as the browser autoplay unlock
// (TVs block sound until a user gesture) and previews the key cues. "Done"
// unlocks all audio, then rolls into the theme if the game has already started.
const soundPanelOpen = ref(true);
const startShow = () => {
    sounds.unlock();
    soundCheckDone.value = true;
    soundPanelOpen.value = false;
    if (props.status === 'playing') {
        // Game already started — play the theme now, inside this Done gesture.
        maybeStartTheme();
    } else {
        // Not started — bless the theme so the status watcher can start it later.
        blessAudio(getTheme(), () => themePrimed);
    }
};

// Start the theme when the admin starts the game (fires outside a gesture, hence
// the bless above), and stop it when leaving active play.
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

// Fast Money spans a few phases (intro → p1 → p2 → result); the segment is the
// reliable signal since the current question stays a fast_money one throughout.
const isFastMoney = computed(() =>
    phase.value.startsWith('fast_money') || props.currentQuestion?.segment === 'fast_money'
);
const fmActivePlayer = computed(() => props.gameState?.fast_money?.active_player ?? 1);
const isSteal = computed(() => phase.value === 'steal');

// The board (proscenium + slots + survey) is shown during the board phases, once
// there's a question to show and we're not in Fast Money.
const boardVisible = computed(() =>
    props.status === 'playing'
    && !!props.currentQuestion
    && !isFastMoney.value
    && ['faceoff', 'question', 'steal', 'reveal'].includes(phase.value)
);

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

// Face-off lead-in: advancing lands on the round intro ("Get Ready", before the
// host shows the question) — sound the face-off cue there. Only on a real
// transition into the intro, and not while the opening theme is up.
watch(phase, (now, prev) => {
    if (now === 'intro' && !!prev && prev !== 'intro' && props.status === 'playing' && !themeSlideOpen.value) {
        sounds.play('faceOff');
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

// A team reached the target (300): the round recap turns into the "Team X Wins
// the game" celebration (score chips, no confetti — that's saved for a Fast
// Money win), mirroring the America Says crown-the-leader beat before its final.
const gameWon = computed(() => phase.value === 'recap' && !!props.gameState?.feud_target_reached);

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
watch(fmWon, (won, prev) => {
    if (won && !prev) sounds.play('win');
});

onMounted(() => {
    computeFmRemaining();
    fmTimerInterval = window.setInterval(computeFmRemaining, 200);
});

onUnmounted(() => {
    if (strikeFlashTimer) clearTimeout(strikeFlashTimer);
    if (fmTimerInterval) clearInterval(fmTimerInterval);
    if (theme) { theme.pause(); theme = null; }
});
</script>

<template>
    <!-- Family Feud projector board — a replica of the real show's gameboard.
         Colors are intentionally off the Keeler palette (see the scoped styles):
         TV-blue lit set, glossy blue answer slots, gold proscenium, red strikes. -->
    <div class="ff-board">
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
                <pattern id="ffdots" width="12" height="12" patternUnits="userSpaceOnUse">
                    <rect width="12" height="12" fill="#1a5fd0" />
                    <circle cx="3" cy="3" r="1.3" fill="#a8d6ff" opacity=".85" />
                </pattern>
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
                <rect class="ff-scorebox" x="42" y="376" width="92" height="128" rx="9" fill="url(#ffdots)" stroke="#0f3f9e" stroke-width="3" />
                <text class="ff-score" x="88" y="440" text-anchor="middle" dominant-baseline="central">{{ leftTeam?.total_score ?? 0 }}</text>
                <text class="ff-name" x="6" y="668" text-anchor="start" :style="leftTeam ? { fill: leftTeam.color } : undefined">{{ leftTeam?.name ?? '' }}</text>
            </g>
            <!-- right score pod -->
            <g class="ff-scorewrap" :class="{ ctrl: rightTeam && holderId === rightTeam.id }"
               :style="rightTeam ? { '--tc': rightTeam.color } : undefined">
                <rect class="ff-scorebox" x="1486" y="376" width="92" height="128" rx="9" fill="url(#ffdots)" stroke="#0f3f9e" stroke-width="3" />
                <text class="ff-score" x="1532" y="440" text-anchor="middle" dominant-baseline="central">{{ rightTeam?.total_score ?? 0 }}</text>
                <text class="ff-name" x="1614" y="668" text-anchor="end" :style="rightTeam ? { fill: rightTeam.color } : undefined">{{ rightTeam?.name ?? '' }}</text>
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
                    </div>
                    <div class="ff-survey"><span>{{ currentQuestion?.question_text }}</span></div>
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

                <!-- Steal banner — only during the actual one-guess steal. Once it
                     resolves we drop to the 'reveal' beat (host puts up the leftovers),
                     so the banner clears rather than implying a steal is still live. -->
                <div
                    v-if="isSteal && stealTeam"
                    class="ff-stealbar"
                    :style="{ '--tc': stealTeam.color, color: stealTeam.color }"
                >
                    {{ stealTeam.name }} — Steal!
                </div>
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

            <!-- ===================== INTRO (round about to start) ===================== -->
            <div v-else-if="phase === 'intro'" class="ff-center show">
                <div class="ff-eyebrow">Round {{ roundNumber }}</div>
                <template v-if="stealTeam">
                    <div class="ff-headline" :style="{ color: stealTeam.color }">{{ stealTeam.name }}</div>
                    <div class="ff-subhead">Get Ready</div>
                </template>
                <div v-else class="ff-subhead">Get Ready</div>
            </div>

            <!-- ===================== RECAP (end-of-round scores) ===================== -->
            <div v-else-if="phase === 'recap'" class="ff-center show">
                <!-- A team hit the target → crown the game winner (Fast Money is next,
                     as a bonus). Otherwise just the end-of-round scores. No confetti
                     here — that's reserved for a Fast Money win. -->
                <template v-if="gameWon">
                    <div class="ff-eyebrow">Game Winner</div>
                    <div v-if="winningTeam" class="ff-headline" :style="{ color: winningTeam.color }">{{ winningTeam.name }}</div>
                    <div class="ff-subhead">Wins the Game!</div>
                </template>
                <div v-else class="ff-eyebrow">End of Round {{ roundNumber }} &middot; Scores</div>
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
        <button v-if="themeSlideOpen" type="button" class="ff-skip" @click="skipTheme">Skip Intro &rarr;</button>

        <!-- Sound check + one-time audio unlock. TVs/browsers block sound until a
             user gesture, so this shows on load: preview the cues, then Done
             enables audio and dismisses it. The card uses the Keeler palette
             (keeler-app scope); the rest of the board is intentionally off-palette. -->
        <div v-if="soundPanelOpen" class="ff-soundcheck keeler-app">
            <div class="ff-soundcheck-card">
                <p class="ff-soundcheck-title">Sound Check</p>
                <p class="ff-soundcheck-hint">Tap a sound to preview it, then press Done.</p>
                <div class="ff-soundcheck-row">
                    <Button variant="success" size="md" @click="sounds.play('answerReveal')">&#9654;&nbsp; Answer Reveal</Button>
                    <Button variant="danger" size="md" @click="sounds.play('strike')">&#9654;&nbsp; Strike</Button>
                </div>
                <Button variant="accent" size="md" @click="startShow">Done</Button>
            </div>
        </div>
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
/* Full back WALL of blue light bulbs, faded toward the edges, gently twinkling. */
.ff-board::before {
    content: "";
    position: absolute;
    inset: -2%;
    pointer-events: none;
    z-index: 0;
    background-image: radial-gradient(circle,
        rgba(175, 222, 255, 1) 0 2.6px,
        rgba(70, 160, 255, .82) 3.2px 4.7px,
        rgba(20, 72, 178, .28) 5.4px 7px,
        transparent 7.6px);
    background-size: 52px 52px;
    -webkit-mask-image: radial-gradient(135% 122% at 50% 42%, #000 60%, rgba(0, 0, 0, .5) 84%, transparent 100%);
    mask-image: radial-gradient(135% 122% at 50% 42%, #000 60%, rgba(0, 0, 0, .5) 84%, transparent 100%);
    animation: ff-twinkle 4s ease-in-out infinite;
}
@keyframes ff-twinkle { 0%, 100% { opacity: .7; } 50% { opacity: .95; } }
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
.ff-scorewrap.ctrl .ff-scorebox {
    stroke: var(--tc, #4bd6ff);
    stroke-width: 6px;
    filter: drop-shadow(0 0 12px var(--tc, #4bd6ff));
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
    background:
        radial-gradient(circle, rgba(150, 205, 255, .85) 0 1.4px, transparent 2.4px) 0 0/13px 13px,
        linear-gradient(180deg, #2f8bf2, #1560d6);
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

/* ---- Steal banner ----------------------------------------------------- */
.ff-stealbar {
    position: absolute;
    top: 16%;
    left: 50%;
    transform: translateX(-50%);
    z-index: 22;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-size: clamp(20px, 2.6vw, 46px);
    padding: .24em 1.1em;
    border-radius: 999px;
    color: var(--tc, #fff);
    background: rgba(6, 16, 64, .82);
    border: 3px solid currentColor;
    box-shadow: 0 0 30px rgba(75, 214, 255, .5), inset 0 0 14px rgba(0, 0, 0, .4);
    animation: ff-steal 1.1s ease-in-out infinite;
}
@keyframes ff-steal {
    0%, 100% { transform: translateX(-50%) scale(1); }
    50% { transform: translateX(-50%) scale(1.05); }
}

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
    gap: clamp(5px, .5vw, 10px);
    align-items: stretch;
}
.ff-fm2text {
    display: flex;
    align-items: center;
    min-width: 0;
    min-height: clamp(38px, 5.4vh, 64px);
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
    border-radius: 6px;
    background: #000;
    border: 2px solid #b8c6d8;
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(18px, 2.2vw, 40px);
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

/* Sound-check card, tucked top-left (no full-screen scrim) so the board stays
   visible. The card uses Keeler palette tokens (keeler-app scope on the wrapper). */
.ff-soundcheck {
    position: absolute;
    top: 3%;
    left: 2.4%;
    z-index: 40;
    min-height: 0;
    background: transparent;
}
.ff-soundcheck-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    width: min(320px, 36vw);
    padding: 16px 18px;
    border-radius: 16px;
    background: rgb(var(--color-surface-elevated));
    border: 1px solid rgb(var(--color-border-strong));
    box-shadow: 0 16px 40px rgba(0, 0, 0, .55);
}
.ff-soundcheck-title {
    color: rgb(var(--color-text));
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-size: clamp(16px, 1.6vw, 22px);
}
.ff-soundcheck-hint {
    margin: 0 0 2px;
    color: rgb(var(--color-text-muted));
    font-weight: 600;
    letter-spacing: .01em;
    font-size: clamp(11px, 1vw, 14px);
}
.ff-soundcheck-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
.ff-soundcheck-card :deep(button) { width: 100%; justify-content: center; }

@media (prefers-reduced-motion: reduce) {
    .ff-inner { transition: none; }
    .ff-stealbar, .ff-scorewrap.ctrl .ff-scorebox { animation: none; }
    .ff-confetti-piece { animation: none; display: none; }
}
</style>
