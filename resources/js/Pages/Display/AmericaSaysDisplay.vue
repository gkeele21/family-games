<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { US_MAP_PATH } from './usMapPath';
import { useSoundEffects } from '@/composables/useSoundEffects';
import Button from '@/Components/Base/Button.vue';

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
}

interface GameState {
    timer_started_at: string | null;
    timer_duration: number;
    round_number?: number;
    // The team currently holding the turn — named on the round intro.
    active_team_id?: number | null;
    // Guided flow phase. Regular: 'intro' | 'question' | 'recap'. Final round:
    // 'final_intro' | 'final_question' | 'final_play' | 'final_cleared' |
    // 'final_review' | 'final_result'. Absent on older sessions (treated as
    // 'question' so the board still renders).
    phase?: string;
    // Final round: the lone team playing, and the pass/fail outcome.
    final_team_id?: number | null;
    final_result?: string | null;
    // True on the last regular round's recap, before the final begins.
    final_queued?: boolean;
    // True when the recap follows the last question of the round (every team has
    // had their turn) — the scores slide then reads "End of Round N".
    end_of_round?: boolean;
    // Monotonic counter bumped by the host's "Wrong" buzzer; each advance plays
    // the incorrect sound on the display.
    wrong_buzz?: number;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    controlling_team_id: number | null;
    segment?: string | null;
    answers_needed?: number | null;
    answers: Answer[];
}

interface Props {
    teams: Team[];
    gameState: GameState | null;
    currentQuestion: CurrentQuestion | null;
    inviteCode: string;
    // Session status: 'lobby' | 'playing' | 'paused' | 'completed'. The map is a
    // constant backdrop; the overlay changes per status.
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    status: 'playing',
});

// Teams in a fixed order (Team 1 first) for the final scoreboard.
const orderedTeams = computed(() =>
    [...props.teams].sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0))
);

const usMapPath = US_MAP_PATH;

// Sound effects: uploaded clips in public/sounds/ (correct / incorrect / timeout).
const sounds = useSoundEffects();

// Background round music — its own channel at a lower volume so the one-shot
// effects cut through it. Plays while the timer runs and pauses with it (see the
// watchers below).
let bgm: HTMLAudioElement | null = null;
const getBgm = (): HTMLAudioElement => {
    if (!bgm) {
        bgm = new Audio('/sounds/GameplayMusic.m4a');
        bgm.preload = 'auto';
        bgm.volume = 0.4;
    }
    return bgm;
};

// Theme music — the intro sting played over the map + team names once the game
// has started AND the display's audio has been unlocked (see the flow below).
let theme: HTMLAudioElement | null = null;
const getTheme = (): HTMLAudioElement => {
    if (!theme) {
        theme = new Audio('/sounds/ThemeMusic.m4a');
        theme.preload = 'auto';
        theme.volume = 0.6;
    }
    return theme;
};

// "Bless" an audio element inside a user gesture so it can be played
// programmatically later, even though that later play isn't a gesture itself.
const blessAudio = (m: HTMLAudioElement) => {
    m.muted = true;
    const p = m.play();
    if (p) {
        p.then(() => { m.pause(); m.currentTime = 0; m.muted = false; })
         .catch(() => { m.muted = false; });
    }
};

// Theme slide: map + team names shown while the theme music plays, then it
// dismisses itself (revealing gameplay) when the music ends. It fires ONCE, the
// moment BOTH the sound check is done (audio unlocked) AND the admin has started
// the game — whichever happens second triggers it.
const soundCheckDone = ref(false);
const themeSlideOpen = ref(false);
let themeStarted = false;

const startTheme = () => {
    if (themeStarted) return;
    themeStarted = true;
    const t = getTheme();
    themeSlideOpen.value = true;
    let done = false;
    const finish = () => {
        if (done) return;
        done = true;
        if (safety) clearTimeout(safety);
        themeSlideOpen.value = false;
    };
    // Close on natural end, or immediately if the file is missing/blocked so the
    // board never gets stuck behind the slide.
    t.addEventListener('ended', finish, { once: true });
    t.addEventListener('error', finish, { once: true });
    t.currentTime = 0;
    const p = t.play();
    if (p) p.catch(() => finish());
    // Safety net: if the audio stalls without firing 'ended'/'error', drop the
    // curtain anyway once it's outlasted any reasonable theme length.
    const safety = window.setTimeout(finish, 15000);
};

const maybeStartTheme = () => {
    if (soundCheckDone.value && props.status === 'playing') startTheme();
};

// The sound-check panel shows on load. It doubles as the browser autoplay unlock
// (TVs block sound until a user gesture) and lets whoever's running the display
// preview the correct/incorrect cues so the room knows them. "Done" unlocks all
// audio, then rolls straight into the theme if the admin has already started.
const soundPanelOpen = ref(true);
const startShow = () => {
    sounds.unlock();
    soundCheckDone.value = true;
    blessAudio(getBgm());
    soundPanelOpen.value = false;
    if (props.status === 'playing') {
        // Game already started — play the theme now, inside this Done gesture.
        // Do NOT bless the theme first: blessAudio() plays it muted then pauses
        // and resets on a deferred promise, which would stop the theme we just
        // started — leaving the curtain stuck with no music. Playing it here is
        // itself a gesture, so it's unlocked without blessing.
        maybeStartTheme();
    } else {
        // Game not started yet — bless the theme so the status watcher can start
        // it later (that fires outside a user gesture), and resume round music if
        // we opened mid-round.
        blessAudio(getTheme());
        if (props.gameState?.timer_started_at) {
            getBgm().play().catch(() => {});
        }
    }
};

// Timer state
const remainingTime = ref(props.gameState?.timer_duration || 30);
const buzzerPlayed = ref(false);
let timerInterval: number | null = null;
let audioContext: AudioContext | null = null;

// Timer expiry: play the uploaded EndTimer.m4a if present; otherwise fall back to
// the built-in synth buzzer so there's always *some* time's-up cue. Guarded so
// it fires once per run (reset when the timer restarts).
const playTimeout = () => {
    if (buzzerPlayed.value) return;
    buzzerPlayed.value = true;

    if (sounds.isAvailable('timeout') === false) {
        playBuzzerTone();
    } else {
        sounds.play('timeout');
    }
};

// Fallback time's-up tone via the Web Audio API (used when no EndTimer.m4a exists).
const playBuzzerTone = () => {
    try {
        audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();

        // Resume context if suspended (browser autoplay policy)
        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }

        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        // Buzzer sound: low frequency square wave
        oscillator.type = 'square';
        oscillator.frequency.setValueAtTime(220, audioContext.currentTime);

        // Volume envelope
        gainNode.gain.setValueAtTime(0.5, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 1);
    } catch (e) {
        console.error('Could not play buzzer sound:', e);
    }
};

// Calculate remaining time based on timer_started_at
const calculateRemainingTime = () => {
    if (!props.gameState?.timer_started_at) {
        remainingTime.value = props.gameState?.timer_duration || 30;
        return;
    }

    const startTime = new Date(props.gameState.timer_started_at).getTime();
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const prevTime = remainingTime.value;
    remainingTime.value = Math.max(0, (props.gameState?.timer_duration || 30) - elapsed);

    // Play timeout sound when timer crosses from >0 to 0
    if (prevTime > 0 && remainingTime.value === 0) {
        getBgm().pause();
        playTimeout();
    }
};

// Reset buzzer when timer restarts
watch(() => props.gameState?.timer_started_at, (newVal, oldVal) => {
    if (newVal && !oldVal) {
        // Timer just started, reset buzzer flag
        buzzerPlayed.value = false;
    }
});

// --- Background music --------------------------------------------------------
// Play round music while the timer runs; pause it when the timer pauses; rewind
// on a new question or when play stops. Music and timer both advance in real time
// and pause together, so the climax lands near the timer's end with no manual
// position syncing. (On resume we DON'T rewind, so it continues where it paused.)
watch(() => props.gameState?.timer_started_at != null, (running, wasRunning) => {
    const m = getBgm();
    if (running) m.play().catch(() => {});
    else if (wasRunning) m.pause();
});

// New question → music back to the top (the timer isn't running yet here).
watch(() => props.currentQuestion?.id, () => {
    const m = getBgm();
    m.pause();
    m.currentTime = 0;
});

// Status changes: leaving active play stops the round music; entering it (admin
// pressed Start Game) rolls the theme if the sound check was already done.
watch(() => props.status, (s) => {
    if (s !== 'playing') {
        const m = getBgm();
        m.pause();
        m.currentTime = 0;
    } else {
        maybeStartTheme();
    }
});

// Timer display
const timerDisplay = computed(() => {
    const mins = Math.floor(remainingTime.value / 60);
    const secs = remainingTime.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

const timerWarning = computed(() => remainingTime.value <= 10 && remainingTime.value > 0);
const timerExpired = computed(() => remainingTime.value <= 0);

// Guided-flow phase (defaults to 'question' when absent so old sessions still render).
const phase = computed(() => props.gameState?.phase ?? 'question');
const roundNumber = computed(() => props.gameState?.round_number ?? 1);

// The team up next on the round intro. The controlling team is the effective
// holder of the turn; fall back to the active team from state.
const activeTeam = computed<Team | null>(() => {
    const id = props.currentQuestion?.controlling_team_id ?? props.gameState?.active_team_id;
    return id ? props.teams.find((t) => t.id === id) ?? null : null;
});

// Final round: the single team playing for a pass/fail win.
const finalTeam = computed<Team | null>(() => {
    const id = props.gameState?.final_team_id;
    return id ? props.teams.find((t) => t.id === id) ?? null : null;
});
const finalResult = computed(() => props.gameState?.final_result ?? null);

// --- Correct-answer sound -------------------------------------------------
// Play CorrectAnswer.m4a whenever a new answer flips to revealed on the SAME question.
// A question switch (or the board first loading a question that already has
// reveals) syncs silently so we don't blast the sound on load.
const revealedIds = computed(() =>
    (props.currentQuestion?.answers ?? []).filter((a) => a.revealed).map((a) => a.id)
);
const revealedKey = computed(() => [...revealedIds.value].sort((a, b) => a - b).join(','));
let prevRevealed = new Set<number>(revealedIds.value);
let prevQuestionId: number | null = props.currentQuestion?.id ?? null;

watch(revealedKey, () => {
    const qid = props.currentQuestion?.id ?? null;
    const now = new Set(revealedIds.value);
    // Question changed → adopt its revealed set without a sound.
    if (qid !== prevQuestionId) {
        prevQuestionId = qid;
        prevRevealed = now;
        return;
    }
    let hasNewReveal = false;
    now.forEach((id) => { if (!prevRevealed.has(id)) hasNewReveal = true; });
    prevRevealed = now;
    if (hasNewReveal) sounds.play('correct');
});

// --- Incorrect sound ------------------------------------------------------
// IncorrectAnswer.m4a is host-driven only — the "Wrong Answer" buzzer. It is NOT
// played automatically on a final-round loss (only TimerEnd plays when the
// clock runs out). The host bumps a monotonic counter; play the sound each time
// it advances (undefined prev on first load means no sound until pressed).
watch(() => props.gameState?.wrong_buzz, (now, prev) => {
    if (typeof now === 'number' && typeof prev === 'number' && now > prev) {
        sounds.play('incorrect');
    }
});

// --- Question-shown & board-shown sounds ----------------------------------
// ShowQuestion.m4a: the plaque appears when the host presses "Show Question"
// (phase → 'question' regular / 'final_question' final).
// AnswerBoard.m4a: the answer board + clock appear — in the final that's "Reveal
// Answers & Start" (phase → 'final_play'); in a regular round it's "Start Timer",
// which sets timer_started_at while phase stays 'question' (handled below).
watch(phase, (now, prev) => {
    if (now === prev) return;
    if (now === 'question' || now === 'final_question') sounds.play('question');
    else if (now === 'final_play') sounds.play('board');
});

// Regular round: board/clock appear on the timer's false→true start. Gated to the
// 'question' phase so it doesn't double up with the final_play case above (the
// final sets phase and timer together).
watch(() => props.gameState?.timer_started_at != null, (started, wasStarted) => {
    if (started && !wasStarted && phase.value === 'question') sounds.play('board');
});

// A final question's answer board is shown once the host reveals it (phase
// final_play), while reviewing misses after time is up (final_review), or any
// time the current question already has revealed answers — so jumping back to a
// cleared/skipped question shows its answers, while a freshly-shown question
// (final_question with nothing revealed yet) stays plaque-only for the read.
const finalHasReveals = computed(() =>
    !!props.currentQuestion?.answers?.some((a) => a.revealed)
);
const finalBoardVisible = computed(
    () => phase.value === 'final_play' || phase.value === 'final_review' || finalHasReveals.value
);

// Confetti for the winner slide: a fixed set of pieces (position / delay /
// duration / color / size) that fall continuously. Built once so the pieces
// don't reshuffle on every poll.
const CONFETTI_COLORS = ['#f4b433', '#ef4636', '#2438e6', '#eaf1ff', '#5f7bff'];
const confettiPieces = Array.from({ length: 60 }, (_, i) => ({
    left: (i * 37) % 100,
    delay: -((i * 0.37) % 6),
    duration: 4.5 + ((i * 13) % 40) / 10,
    color: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
    size: 8 + (i % 4) * 3,
    drift: (i % 2 === 0 ? 1 : -1) * (6 + (i % 5) * 3),
    round: i % 3 === 0,
}));

// When the final clock runs out, flash a full-screen "Out of Time" over the map
// for ~3s, then let the board fall through to the reveal (review) beneath it.
const timeUpSplash = ref(false);
watch(phase, (newPhase, oldPhase) => {
    if (newPhase === 'final_review' && oldPhase && oldPhase !== 'final_review') {
        timeUpSplash.value = true;
        window.setTimeout(() => { timeUpSplash.value = false; }, 3000);
    }
});

// The last regular round just ended and the final is next — the recap board
// crowns the team leading on cumulative score (they earn the final round).
const finalQueued = computed(() => !!props.gameState?.final_queued);
const leadingTeam = computed<Team | null>(() => {
    if (!props.teams.length) return null;
    return [...props.teams].sort(
        (a, b) => b.total_score - a.total_score || (a.display_order ?? 0) - (b.display_order ?? 0)
    )[0];
});
// The answer board + clock only appear once the host starts the timer. Before
// that (just after "Show Question") only the question plaque is shown.
const timerStarted = computed(() => props.gameState?.timer_started_at != null);

// Sort answers by display_order for proper grid layout
const sortedAnswers = computed(() => {
    if (!props.currentQuestion?.answers) return [];
    return [...props.currentQuestion.answers].sort((a, b) => a.display_order - b.display_order);
});

// Authentic board: group answers into the show's center → pair → center → pair
// arrangement (single, pair, single, pair, …), generalized to any answer count.
const answerRows = computed<Answer[][]>(() => {
    const list = sortedAnswers.value;
    const rows: Answer[][] = [];
    let i = 0;
    let single = true;
    while (i < list.length) {
        const remaining = list.length - i;
        const take = single || remaining === 1 ? 1 : 2;
        rows.push(list.slice(i, i + take));
        i += take;
        single = !single;
    }
    return rows;
});

// Split an obfuscated blank on hyphens so each hyphen can be rendered in its own
// span with breathing room (the underscore runs stay squished into a line).
const blankSegments = (text: string): { text?: string; hyphen?: boolean }[] => {
    const segs: { text?: string; hyphen?: boolean }[] = [];
    text.split('-').forEach((part, i) => {
        if (i > 0) segs.push({ hyphen: true });
        segs.push({ text: part });
    });
    return segs;
};

// Split the question text so any run of 2+ underscores (the fill-in blank the host
// types) renders as one merged continuous line instead of spaced-out dashes.
const questionSegments = (text: string): { text: string; blank?: boolean }[] => {
    const segs: { text: string; blank?: boolean }[] = [];
    const re = /_{2,}/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) segs.push({ text: text.slice(last, m.index) });
        segs.push({ text: m[0], blank: true });
        last = m.index + m[0].length;
    }
    if (last < text.length) segs.push({ text: text.slice(last) });
    return segs;
};

onMounted(() => {
    calculateRemainingTime();
    timerInterval = window.setInterval(calculateRemainingTime, 100);
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    if (bgm) {
        bgm.pause();
        bgm = null;
    }
    if (theme) {
        theme.pause();
        theme = null;
    }
});
</script>

<template>
    <!-- Projector board — a replica of the real America Says gameboard.  -->
    <!-- Colors are intentionally off the Keeler palette (see notes in the -->
    <!-- scoped styles below): neon-outlined US map, star field, red plaque. -->
    <div class="as-board">
        <!-- US map: neon-outlined blue silhouette with a faint star field -->
        <div class="as-mapwrap">
            <svg class="as-map" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <radialGradient id="asMapFill" cx="50%" cy="42%" r="75%">
                        <stop offset="0%" stop-color="#2438e6" />
                        <stop offset="100%" stop-color="#0f1f9c" />
                    </radialGradient>
                    <pattern id="asStars" width="90" height="90" patternUnits="userSpaceOnUse">
                        <path d="M12 2 L14 9 L21 9 L15 13 L17 20 L12 16 L7 20 L9 13 L3 9 L10 9 Z" fill="#5f7bff" opacity="0.16" />
                        <path d="M60 45 L61.4 50 L66 50 L62 53 L63.4 58 L60 55 L56.6 58 L58 53 L54 50 L58.6 50 Z" fill="#5f7bff" opacity="0.13" />
                        <circle cx="40" cy="72" r="1.6" fill="#5f7bff" opacity="0.18" />
                        <circle cx="80" cy="20" r="1.3" fill="#5f7bff" opacity="0.15" />
                    </pattern>
                    <clipPath id="asClip"><path :d="usMapPath" /></clipPath>
                </defs>
                <path :d="usMapPath" fill="url(#asMapFill)" />
                <rect x="0" y="0" width="1600" height="900" fill="url(#asStars)" clip-path="url(#asClip)" />
                <path :d="usMapPath" fill="none" stroke="#eaf1ff" stroke-width="5" stroke-linejoin="round" stroke-linecap="round" />
                <path :d="usMapPath" fill="none" stroke="#ffffff" stroke-width="1.6" stroke-linejoin="round" opacity="0.9" />
            </svg>
        </div>

        <!-- ===================== PLAYING ===================== -->
        <!-- Held back while the theme slide (game-start curtain) plays, so no
             phase content — Round intro, question plaque, scores — ghosts through
             the theme scrim. Whatever phase is current appears once it dismisses. -->
        <template v-if="status === 'playing' && !themeSlideOpen">
            <!-- Final round: the single time budget stays on the board through every
                 beat (intro / question / play / cleared) — like the regular round
                 clock. It shows the banked time while idle and counts down during
                 play. Once time is up (final_review) the clock is gone; the host is
                 just revealing the answers they missed. -->
            <div
                v-if="['final_intro', 'final_question', 'final_play', 'final_cleared'].includes(phase)"
                class="as-timer"
                :class="{ 'as-timer-warn': timerWarning, 'as-timer-expired': timerExpired }"
            >{{ timerDisplay }}</div>

            <!-- INTRO: a round is about to start; the question stays hidden. Names
                 the team whose turn is up so they know to get ready. -->
            <div v-if="phase === 'intro'" class="as-center">
                <p class="as-eyebrow font-logo">Round {{ roundNumber }}</p>
                <template v-if="activeTeam">
                    <p class="as-headline as-headline-sm font-logo" :style="{ color: activeTeam.color }">{{ activeTeam.name }}</p>
                    <p class="as-subhead font-logo">Get Ready</p>
                </template>
                <p v-else class="as-headline font-logo">Get Ready</p>
            </div>

            <!-- RECAP: round scoreboard. After the last regular round, crown the
                 leading team before the final round begins. -->
            <div v-else-if="phase === 'recap'" class="as-center">
                <template v-if="finalQueued && leadingTeam">
                    <p class="as-headline font-logo" :style="{ color: leadingTeam.color }">{{ leadingTeam.name }} Wins!</p>
                    <p class="as-subhead font-logo">On to the Final Round</p>
                </template>
                <p v-else class="as-eyebrow font-logo">{{ gameState?.end_of_round ? 'End of Round' : 'Round' }} {{ roundNumber }} · Scores</p>
                <div class="as-finalscores">
                    <div v-for="team in orderedTeams" :key="team.id" class="as-scorechip">
                        <span class="as-scorechip-name" :style="{ color: team.color }">{{ team.name }}</span>
                        <span class="as-scorechip-pts font-logo">{{ team.total_score.toLocaleString() }}</span>
                    </div>
                </div>
            </div>

            <!-- FINAL — intro: which team is playing, get ready -->
            <div v-else-if="phase === 'final_intro'" class="as-center">
                <p class="as-eyebrow font-logo">Final Round</p>
                <p class="as-headline as-headline-sm font-logo" :style="finalTeam ? { color: finalTeam.color } : undefined">{{ finalTeam?.name ?? '' }}</p>
                <p class="as-subhead font-logo">Get Ready</p>
            </div>

            <!-- FINAL — pass/fail result. A win gets a celebratory "You Won!!!!"
                 with confetti raining continuously; a loss keeps "Out of Time". -->
            <div v-else-if="phase === 'final_result'" class="as-center">
                <div v-if="finalResult === 'win'" class="as-confetti" aria-hidden="true">
                    <span
                        v-for="(p, pi) in confettiPieces"
                        :key="pi"
                        class="as-confetti-piece"
                        :class="{ 'as-confetti-round': p.round }"
                        :style="{
                            left: p.left + '%',
                            backgroundColor: p.color,
                            width: p.size + 'px',
                            height: p.size + 'px',
                            animationDelay: p.delay + 's',
                            animationDuration: p.duration + 's',
                            '--as-drift': p.drift + 'vw',
                        }"
                    ></span>
                </div>
                <p class="as-eyebrow font-logo">Final Round</p>
                <p class="as-headline font-logo" :class="{ 'as-win-headline': finalResult === 'win' }">{{ finalResult === 'win' ? 'You Won!!!!' : 'Out of Time' }}</p>
                <p v-if="finalTeam" class="as-subhead font-logo" :style="{ color: finalTeam.color }">{{ finalTeam.name }}</p>
            </div>

            <!-- FINAL — a question board: plaque + a top-anchored, left-aligned answer
                 list (the block is centered on the map; answer #1 holds its spot and
                 each new answer stacks beneath it, all sharing a left edge). The
                 plaque shows first on its own (final_question) so the host can read
                 the question before revealing; the answer board appears once the host
                 reveals (final_play), stays up after the question is cleared
                 (final_cleared), and is shown when reviewing misses (final_review) or
                 whenever the current question already has revealed answers. -->
            <template v-else-if="['final_question', 'final_play', 'final_cleared', 'final_review'].includes(phase)">
                <div class="as-overlay">
                    <div class="as-plaque">
                        <div class="as-face">
                            <p class="font-logo"><template v-if="currentQuestion"><template
                                v-for="(seg, si) in questionSegments(currentQuestion.question_text)" :key="si"
                            ><span v-if="seg.blank" class="as-qblank">{{ seg.text }}</span><template v-else>{{ seg.text }}</template></template></template></p>
                        </div>
                    </div>

                    <div v-if="currentQuestion && finalBoardVisible" class="as-answers as-answers-final">
                        <div class="as-final-col">
                            <div v-for="ans in sortedAnswers" :key="ans.id" class="as-row-final">
                                <div class="as-ans as-ans-final font-logo">
                                    <span v-if="!ans.revealed" class="as-blank-wrap"><template
                                        v-for="(seg, si) in blankSegments(ans.answer_text)" :key="si"
                                    ><span v-if="seg.hyphen" class="as-hyphen">-</span><span v-else class="as-blank">{{ seg.text }}</span></template></span>
                                    <span v-else class="as-typing">{{ ans.answer_text }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- QUESTION: plaque always; answer board + timer only once started -->
            <template v-else>
                <div
                    v-if="currentQuestion && timerStarted"
                    class="as-timer"
                    :class="{ 'as-timer-warn': timerWarning, 'as-timer-expired': timerExpired }"
                >
                    {{ timerDisplay }}
                </div>

                <div class="as-overlay">
                    <div class="as-plaque">
                        <div class="as-face">
                            <p class="font-logo"><template v-if="currentQuestion"><template
                                v-for="(seg, si) in questionSegments(currentQuestion.question_text)" :key="si"
                            ><span v-if="seg.blank" class="as-qblank">{{ seg.text }}</span><template v-else>{{ seg.text }}</template></template></template><template v-else>Waiting for question…</template></p>
                        </div>
                    </div>

                    <div v-if="currentQuestion && timerStarted" class="as-answers">
                        <div
                            v-for="(row, ri) in answerRows"
                            :key="ri"
                            class="as-row"
                            :class="{ 'as-pair': row.length === 2 }"
                        >
                            <div v-for="ans in row" :key="ans.id" class="as-ans font-logo">
                                <!-- Unrevealed: first-letter + underscores squished into a
                                     continuous line (hides the character count). Hyphens are
                                     rendered separately so they get breathing room. -->
                                <span v-if="!ans.revealed" class="as-blank-wrap"><template
                                    v-for="(seg, si) in blankSegments(ans.answer_text)" :key="si"
                                ><span v-if="seg.hyphen" class="as-hyphen">-</span><span v-else class="as-blank">{{ seg.text }}</span></template></span>
                                <!-- Revealed: typewriter reveal of the real answer. -->
                                <span v-else class="as-typing">{{ ans.answer_text }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- ===================== WAITING (lobby) ===================== -->
        <div v-else-if="status === 'lobby'" class="as-center">
            <div class="as-logo" aria-label="America Says">
                <span class="as-logo-word as-logo-america">America</span>
                <span class="as-logo-word as-logo-says">Says</span>
            </div>
            <!-- The matchup, each team in its own color, so players see the lineup
                 before the game starts. -->
            <div v-if="orderedTeams.length" class="as-matchup">
                <template v-for="(team, ti) in orderedTeams" :key="team.id">
                    <span v-if="ti > 0" class="as-vs font-logo">vs</span>
                    <span class="as-team font-logo" :style="{ color: team.color }">{{ team.name }}</span>
                </template>
            </div>
            <p class="as-subhead font-logo">Waiting to Start</p>
        </div>

        <!-- ===================== PAUSED ===================== -->
        <div v-else-if="status === 'paused'" class="as-center">
            <p class="as-headline font-logo">Paused</p>
        </div>

        <!-- Sound check + one-time audio unlock. TVs/browsers block sound until a
             user gesture, so this panel shows on load: preview the cues, then
             "Start" enables audio and dismisses it. -->
        <!-- keeler-app brings the palette tokens into scope so the shared Button
             component's color variants (success / danger / primary) resolve — the
             rest of this board is intentionally off-palette hex. -->
        <div v-if="soundPanelOpen" class="as-soundcheck keeler-app">
            <div class="as-soundcheck-card">
                <p class="as-soundcheck-title font-logo">Sound Check</p>
                <p class="as-soundcheck-hint">Tap a sound to preview it, then press Done.</p>
                <div class="as-soundcheck-row">
                    <Button variant="success" size="md" @click="sounds.play('correct')">&#9654;&nbsp; Correct Answer</Button>
                    <Button variant="danger" size="md" @click="sounds.play('incorrect')">&#9654;&nbsp; Incorrect Answer</Button>
                </div>
                <Button variant="accent" size="md" @click="startShow">Done</Button>
            </div>
        </div>

        <!-- Theme slide: the map + team matchup while the theme music plays, then
             it dismisses itself (on the music's 'ended') to reveal gameplay. -->
        <div v-if="themeSlideOpen" class="as-center as-theme">
            <div class="as-logo" aria-label="America Says">
                <span class="as-logo-word as-logo-america">America</span>
                <span class="as-logo-word as-logo-says">Says</span>
            </div>
            <div v-if="orderedTeams.length" class="as-matchup">
                <template v-for="(team, ti) in orderedTeams" :key="team.id">
                    <span v-if="ti > 0" class="as-vs font-logo">vs</span>
                    <span class="as-team font-logo" :style="{ color: team.color }">{{ team.name }}</span>
                </template>
            </div>
        </div>

        <!-- Final round: a brief "Out of Time" flash over the map the moment the
             clock expires, before the reveal board shows beneath it. -->
        <div v-if="timeUpSplash" class="as-center as-timeup">
            <p class="as-headline font-logo">Out of Time</p>
            <p v-if="finalTeam" class="as-subhead font-logo" :style="{ color: finalTeam.color }">{{ finalTeam.name }}</p>
        </div>

        <!-- ===================== GAME OVER ===================== -->
        <div v-else-if="status === 'completed'" class="as-center">
            <p class="as-headline as-headline-sm font-logo">Game Over</p>
            <div class="as-finalscores">
                <div v-for="team in orderedTeams" :key="team.id" class="as-scorechip">
                    <span class="as-scorechip-name" :style="{ color: team.color }">{{ team.name }}</span>
                    <span class="as-scorechip-pts font-logo">{{ team.total_score.toLocaleString() }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* =====================================================================
   America Says projector board. Colors here are intentionally off the
   Keeler palette — this board is a replica of the real America Says
   gameboard (a sanctioned exception, projector-only, nothing sold).
   ===================================================================== */
.as-board {
    position: relative;
    width: 100%;
    height: 100vh;
    overflow: hidden;
    background:
        radial-gradient(120% 80% at 50% 8%, #16225e 0%, #0a1236 32%, #02030c 78%),
        #02030c;
}
/* faint blue stage-floor glow like the real set */
.as-board::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 26%;
    background: linear-gradient(0deg, rgba(46, 86, 255, 0.35), rgba(46, 86, 255, 0) 100%);
    filter: blur(8px);
    pointer-events: none;
}
.as-mapwrap { position: absolute; inset: 0; display: grid; place-items: center; }
.as-map {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 0 6px #6ea0ff) drop-shadow(0 0 22px rgba(80, 130, 255, 0.55));
}

/* Content is BOUNDED to the map's interior. The plaque pins to the top; the
   answers fill and vertically center in the space below — so no matter how tall
   the question or how many answers, nothing spills past the coastline. */
.as-overlay {
    position: absolute;
    left: 50%;
    top: 8%;
    transform: translateX(-50%);
    width: 62%;
    max-width: 1080px;
    height: 72%;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* red question plaque with gold rim and a downward point */
.as-plaque {
    /* Straight sides meeting at a near-point (only a hairline flat at center). */
    --as-notch: polygon(0 0, 100% 0, 100% 66%, 50.8% 100%, 49.2% 100%, 0 66%);
    position: relative;
    max-width: 640px;
    padding: 3px;
    background: #f4b433;
    clip-path: var(--as-notch);
    filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.55));
}
.as-face {
    padding: 1.4vh 2.2vw 3.2vh;
    text-align: center;
    clip-path: var(--as-notch);
    background: linear-gradient(180deg, #ef4636, #b81f16);
    box-shadow: inset 0 0 26px rgba(255, 150, 120, 0.35);
}
.as-plaque p {
    margin: 0;
    color: #fff;
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1.14;
    font-size: clamp(18px, 2vw, 34px);
    text-transform: uppercase;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.55);
}
/* The host's fill-in blank ("____"): squish the underscores into one line. */
.as-qblank { letter-spacing: -0.15em; }

.as-answers {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: clamp(1vh, 2.4vh, 3vh);
    width: 100%;
    padding-top: 2.2vh;
}
.as-row { display: flex; justify-content: center; align-items: center; width: 100%; }
/* Final round: a left-aligned list, vertically centered in the space below the
   plaque (rather than pinned to the top) so it sits in the middle of the map. The
   column is a single centered block and every answer shares its left edge; all
   rows are present from the start, so nothing shifts as answers are revealed. */
.as-answers-final { justify-content: center; padding-top: 0; }
.as-final-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: clamp(1.4vh, 3vh, 4vh);
    margin: 0 auto;
}
.as-row-final { flex: 0 0 auto; }
.as-ans-final { text-align: left; }
/* Theme slide: map + team matchup over a soft scrim while the theme plays. Sits
   above gameplay but below the sound-check panel (they never show together). */
.as-theme {
    z-index: 35;
    background: radial-gradient(120% 80% at 50% 45%, rgba(6, 12, 40, 0.55), rgba(2, 3, 12, 0.85));
    animation: as-timeup-in 0.4s ease-out;
}
/* Brief full-screen "Out of Time" flash when the final clock expires. */
.as-timeup {
    z-index: 30;
    background: radial-gradient(120% 80% at 50% 45%, rgba(120, 20, 20, 0.62), rgba(2, 3, 12, 0.82));
    animation: as-timeup-in 0.25s ease-out;
}
@keyframes as-timeup-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
/* Winner celebration: confetti raining continuously behind the "You Won!!!!"
   headline, and a pop-in on the headline itself. */
.as-confetti {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: -1;
}
.as-confetti-piece {
    position: absolute;
    top: 0;
    border-radius: 2px;
    opacity: 0.92;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.35);
    animation-name: as-confetti-fall;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    will-change: transform;
}
.as-confetti-round { border-radius: 50%; }
@keyframes as-confetti-fall {
    0% { transform: translate3d(0, -12vh, 0) rotate(0deg); }
    100% { transform: translate3d(var(--as-drift, 0), 112vh, 0) rotate(720deg); }
}
.as-win-headline {
    color: #f4b433;
    font-size: clamp(48px, 8.4vw, 150px);
    animation: as-win-pop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes as-win-pop {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
/* pair rows kept narrower and centered so edge words stay inside the coastline */
.as-pair { justify-content: space-between; width: 80%; margin: 0 auto; }
.as-ans {
    color: #f2f7ff;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: clamp(22px, 2.6vw, 46px);
    text-align: center;
    white-space: nowrap;
    text-shadow: 0 0 10px rgba(150, 190, 255, 0.55), 0 2px 6px rgba(0, 0, 0, 0.6);
}
/* Unrevealed blank: negative tracking squishes the underscores into one
   continuous line so the exact character count can't be read off. Hyphens sit
   in their own span with margin so they aren't scrunched against the line.
   vertical-align:middle keeps the row height identical whether the answer is a
   blank or a revealed (overflow:hidden) span — otherwise the baseline shifts as
   answers reveal and the whole board creeps down into the map border. */
.as-blank-wrap { display: inline-block; vertical-align: middle; }
.as-blank { letter-spacing: -0.15em; }
.as-hyphen { margin: 0 0.10em; letter-spacing: normal; }
/* Revealed: typewriter reveal of the real answer (ported from the old board). */
.as-typing {
    display: inline-block;
    vertical-align: middle;
    overflow: hidden;
    white-space: nowrap;
    max-width: 0;
    animation: as-typing 0.3s steps(15) forwards;
}
@keyframes as-typing {
    from { max-width: 0; }
    to { max-width: 100%; }
}

.as-getready {
    color: #f2f7ff;
    font-weight: 900;
    font-size: clamp(30px, 5vw, 84px);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    text-shadow: 0 0 18px rgba(150, 190, 255, 0.6);
}

/* Centered overlay for lobby / paused / game-over states, over the map. */
.as-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4vh;
    z-index: 15;
    text-align: center;
    padding: 4vw;
}
.as-headline {
    color: #f2f7ff;
    font-weight: 900;
    font-size: clamp(40px, 7vw, 120px);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    text-shadow: 0 0 26px rgba(150, 190, 255, 0.65), 0 4px 10px rgba(0, 0, 0, 0.6);
}
.as-headline-sm { font-size: clamp(32px, 5vw, 84px); }
.as-eyebrow {
    color: #f4b433;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: clamp(18px, 2.2vw, 40px);
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}
/* "America Says" show logo — an AMERICA blue banner over a bigger SAYS red
   banner (no shield behind). One font-size on .as-logo scales the whole mark. */
.as-logo {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 0.08em;
    line-height: 1;
    font-family: "Arial Narrow", "Helvetica Neue", Arial, sans-serif;
    /* Glow echoing the map (a bright near-white core over a blue halo), plus a
       soft drop shadow so the lockup reads against the blue map. */
    filter:
        drop-shadow(0 0 0.1em rgba(190, 215, 255, 0.8))
        drop-shadow(0 0 0.34em rgba(90, 140, 255, 0.6))
        drop-shadow(0 0.05em 0.09em rgba(0, 0, 0, 0.5));
    font-size: clamp(60px, 11vw, 180px);
}
.as-logo-word {
    position: relative;
    display: inline-block;
    color: #fff;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1;
    border-radius: 0.05em;
    text-shadow: 0 0.02em 0.03em rgba(0, 0, 0, 0.55);
}
.as-logo-america {
    font-size: 0.56em;
    letter-spacing: 0.01em;
    padding: 0.14em 0.5em;
    background: linear-gradient(180deg, #e0463a, #c0251c);
    transform: rotate(-1deg);
    z-index: 2;
}
.as-logo-says {
    font-size: 1em;
    letter-spacing: 0.02em;
    padding: 0.08em 0.34em 0.12em;
    background: linear-gradient(180deg, #e0463a, #c0251c);
    transform: rotate(-1deg);
    z-index: 3;
}

/* Smaller line beneath the logo on the waiting screen. */
.as-subhead {
    color: #f2f7ff;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: clamp(20px, 2.6vw, 46px);
    text-shadow: 0 0 16px rgba(150, 190, 255, 0.5), 0 2px 6px rgba(0, 0, 0, 0.55);
}
/* Lobby matchup: "Team A vs Team B" in each team's color. */
.as-matchup {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: clamp(12px, 2vw, 32px);
}
.as-team {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-size: clamp(24px, 3.4vw, 60px);
    text-shadow: 0 0 16px rgba(150, 190, 255, 0.4), 0 2px 6px rgba(0, 0, 0, 0.6);
}
.as-vs {
    color: #eaf1ff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: clamp(16px, 2vw, 32px);
    opacity: 0.75;
}

.as-finalscores { display: flex; flex-wrap: wrap; gap: clamp(16px, 3vw, 48px); justify-content: center; }
.as-scorechip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4vh;
    padding: 1.4vh 2.4vw;
    border-radius: 18px;
    background: rgba(6, 12, 40, 0.6);
    border: 2px solid #f4b433;
    box-shadow: 0 0 22px rgba(80, 130, 255, 0.35);
}
.as-scorechip-name {
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: clamp(18px, 2vw, 34px);
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}
.as-scorechip-pts {
    color: #fff;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(32px, 4vw, 68px);
    text-shadow: 0 0 16px rgba(150, 190, 255, 0.5);
}

/* Timer sits in the bottom-right corner, out of the way of the board. */
.as-timer {
    position: absolute;
    right: 2.8%;
    bottom: 5%;
    z-index: 20;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    font-size: clamp(20px, 2.2vw, 38px);
    letter-spacing: 0.05em;
    color: #eaf1ff;
    padding: 0.28em 1em;
    border-radius: 999px;
    background: rgba(6, 12, 40, 0.72);
    border: 2px solid #f4b433;
    box-shadow: 0 0 22px rgba(80, 130, 255, 0.45), inset 0 0 12px rgba(0, 0, 0, 0.4);
}
.as-timer-warn {
    border-color: #ff3b3b;
    color: #fff;
    background: rgba(120, 20, 20, 0.8);
    animation: as-pulse 1s infinite;
}
.as-timer-expired { border-color: #ff3b3b; color: #fff; background: #b81f16; }
@keyframes as-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

/* Sound-check panel: a full-screen scrim over the board with a gold-rimmed card,
   matching the off-palette board look. Shows on load until "Start". */
/* Compact card tucked into the top-left corner (no full-screen scrim) so the
   America Says logo + map stay visible behind it. The card itself uses Keeler
   palette tokens — keeler-app scope is set on the wrapper for exactly this. */
.as-soundcheck {
    position: absolute;
    top: 3%;
    left: 2.4%;
    z-index: 40;
    /* keeler-app is a page wrapper by default (fills the viewport, gray ground) —
       we only want its palette variables, so cancel those two effects. */
    min-height: 0;
    background: transparent;
}
.as-soundcheck-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    width: min(320px, 36vw);
    padding: 16px 18px;
    border-radius: 16px;
    background: rgb(var(--color-surface-elevated));
    border: 1px solid rgb(var(--color-border-strong));
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
}
.as-soundcheck-title {
    color: rgb(var(--color-text));
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: clamp(16px, 1.6vw, 22px);
}
.as-soundcheck-hint {
    margin: 0 0 2px;
    color: rgb(var(--color-text-muted));
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: clamp(11px, 1vw, 14px);
}
.as-soundcheck-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
/* Preview + Done controls (shared Button component) fill the compact card width
   so they read clearly as buttons. */
.as-soundcheck-card :deep(button) { width: 100%; justify-content: center; }

@media (prefers-reduced-motion: reduce) {
    .as-typing { animation: none; max-width: 100%; }
    .as-timer-warn { animation: none; }
    .as-confetti-piece { animation: none; display: none; }
    .as-win-headline { animation: none; }
}
</style>
