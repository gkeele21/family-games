<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { US_MAP_PATH } from './usMapPath';

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
    // Guided flow phase: 'intro' | 'question' | 'recap'. Absent on older sessions
    // (treated as 'question' so the board still renders).
    phase?: string;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    controlling_team_id: number | null;
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

// Timer state
const remainingTime = ref(props.gameState?.timer_duration || 30);
const buzzerPlayed = ref(false);
let timerInterval: number | null = null;
let audioContext: AudioContext | null = null;

// Play buzzer sound using Web Audio API
const playBuzzer = () => {
    if (buzzerPlayed.value) return;
    buzzerPlayed.value = true;

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

    // Play buzzer when timer crosses from >0 to 0
    if (prevTime > 0 && remainingTime.value === 0) {
        playBuzzer();
    }
};

// Reset buzzer when timer restarts
watch(() => props.gameState?.timer_started_at, (newVal, oldVal) => {
    if (newVal && !oldVal) {
        // Timer just started, reset buzzer flag
        buzzerPlayed.value = false;
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
        <template v-if="status === 'playing'">
            <!-- INTRO: a round is about to start; the question stays hidden -->
            <div v-if="phase === 'intro'" class="as-center">
                <p class="as-eyebrow font-logo">Round {{ roundNumber }}</p>
                <p class="as-headline font-logo">Get Ready</p>
            </div>

            <!-- RECAP: round scoreboard -->
            <div v-else-if="phase === 'recap'" class="as-center">
                <p class="as-eyebrow font-logo">Round {{ roundNumber }} · Scores</p>
                <div class="as-finalscores">
                    <div v-for="team in orderedTeams" :key="team.id" class="as-scorechip">
                        <span class="as-scorechip-name" :style="{ color: team.color }">{{ team.name }}</span>
                        <span class="as-scorechip-pts font-logo">{{ team.total_score.toLocaleString() }}</span>
                    </div>
                </div>
            </div>

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
            <p class="as-subhead font-logo">Waiting to Start</p>
        </div>

        <!-- ===================== PAUSED ===================== -->
        <div v-else-if="status === 'paused'" class="as-center">
            <p class="as-headline font-logo">Paused</p>
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
   in their own span with margin so they aren't scrunched against the line. */
.as-blank-wrap { display: inline-block; }
.as-blank { letter-spacing: -0.15em; }
.as-hyphen { margin: 0 0.10em; letter-spacing: normal; }
/* Revealed: typewriter reveal of the real answer (ported from the old board). */
.as-typing {
    display: inline-block;
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

@media (prefers-reduced-motion: reduce) {
    .as-typing { animation: none; max-width: 100%; }
    .as-timer-warn { animation: none; }
}
</style>
