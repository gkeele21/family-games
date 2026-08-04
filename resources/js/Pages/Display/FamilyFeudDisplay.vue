<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

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
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    control_status: string;
    controlling_team_id: number | null;
    controlling_team_ids: number[];
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

// Two team pods flank the board (Team 1 on the left, Team 2 on the right).
const orderedTeams = computed(() =>
    [...props.teams].sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0))
);
const leftTeam = computed(() => orderedTeams.value[0] ?? null);
const rightTeam = computed(() => orderedTeams.value[1] ?? null);

// Answers in survey-rank order. Slot number == rank (top answer is #1).
const rankedAnswers = computed(() => {
    if (!props.currentQuestion?.answers) return [];
    return [...props.currentQuestion.answers]
        .sort((a, b) => a.display_order - b.display_order)
        .map((a, i) => ({ ...a, slot: i + 1 }));
});

// Board total: sum of every revealed answer's points (the round's running score,
// shown in the display at the top of the board like the show).
const boardTotal = computed(() =>
    rankedAnswers.value.reduce((sum, a) => sum + (a.revealed ? (a.points ?? 0) : 0), 0)
);

// Control banner
const isAllPlay = computed(() => props.currentQuestion?.control_status === 'all_play');
const controllingTeam = computed(() => {
    const id = props.currentQuestion?.controlling_team_id;
    if (!id) return null;
    return props.teams.find(t => t.id === id) ?? null;
});
const controlledTeamIds = computed<number[]>(() => {
    const q = props.currentQuestion;
    if (!q) return [];
    if (q.controlling_team_ids?.length) return q.controlling_team_ids;
    return q.controlling_team_id ? [q.controlling_team_id] : [];
});

// ----- Reveal "ding" (Web Audio) -------------------------------------------
// Play the bright board-reveal chime whenever the revealed-answer count rises.
let audioContext: AudioContext | null = null;
const playDing = () => {
    try {
        if (!audioContext) {
            audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
        }
        if (audioContext.state === 'suspended') audioContext.resume();
        const now = audioContext.currentTime;
        // Two stacked bell partials for a bright "ding!".
        [1046.5, 1567.98].forEach((freq, i) => {
            const osc = audioContext!.createOscillator();
            const gain = audioContext!.createGain();
            osc.connect(gain);
            gain.connect(audioContext!.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now);
            const peak = i === 0 ? 0.32 : 0.14;
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(peak, now + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.9);
            osc.start(now);
            osc.stop(now + 0.95);
        });
    } catch (e) {
        // Autoplay may be blocked until first interaction; ignore.
    }
};

const revealedCount = computed(() => rankedAnswers.value.filter(a => a.revealed).length);
watch(revealedCount, (next, prev) => {
    if (next > prev) playDing();
});

// Board total gets a quick "bump" whenever it grows.
const totalBump = ref(false);
watch(boardTotal, (next, prev) => {
    if (next > prev) {
        totalBump.value = true;
        window.setTimeout(() => (totalBump.value = false), 450);
    }
});

// ----- Timer (mirrors the America Says board) ------------------------------
const remainingTime = ref(props.gameState?.timer_duration || 30);
let timerInterval: number | null = null;

const calculateRemainingTime = () => {
    if (!props.gameState?.timer_started_at) {
        remainingTime.value = props.gameState?.timer_duration || 30;
        return;
    }
    const startTime = new Date(props.gameState.timer_started_at).getTime();
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    remainingTime.value = Math.max(0, (props.gameState?.timer_duration || 30) - elapsed);
};

const timerDisplay = computed(() => {
    const mins = Math.floor(remainingTime.value / 60);
    const secs = remainingTime.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});
const timerWarning = computed(() => remainingTime.value <= 10 && remainingTime.value > 0);
const timerExpired = computed(() => remainingTime.value <= 0);
const hasTimer = computed(() => !!props.gameState?.timer_started_at);

onMounted(() => {
    calculateRemainingTime();
    timerInterval = window.setInterval(calculateRemainingTime, 100);
});
onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
    <!-- Projector board — a replica of the modern (Steve Harvey era) Family Feud
         answer board: one column of full-width blue strips, each showing a
         centered rank number that flips on its horizontal axis to reveal the
         answer (left) + a white points card (right). Colors are intentionally
         OFF the Keeler palette (sanctioned projector-only replica exception,
         same as the America Says board). -->
    <div class="ff-board">
        <!-- ===================== PLAYING ===================== -->
        <template v-if="status === 'playing'">
            <!-- Team pods + round total header -->
            <div class="ff-topbar">
                <div
                    v-if="leftTeam"
                    class="ff-pod ff-pod-left"
                    :class="{ 'ff-pod-active': controlledTeamIds.includes(leftTeam.id) }"
                    :style="{ '--pod-color': leftTeam.color }"
                >
                    <span class="ff-pod-name">{{ leftTeam.name }}</span>
                    <span class="ff-pod-score font-logo">{{ leftTeam.total_score }}</span>
                </div>

                <div class="ff-total-wrap">
                    <div class="ff-logo font-logo" aria-label="Family Feud">
                        <span class="ff-logo-line">FAMILY</span>
                        <span class="ff-logo-line ff-logo-feud">FEUD</span>
                    </div>
                    <div class="ff-total" :class="{ 'ff-total-bump': totalBump }">
                        <span class="ff-total-num font-logo">{{ boardTotal }}</span>
                    </div>
                </div>

                <div
                    v-if="rightTeam"
                    class="ff-pod ff-pod-right"
                    :class="{ 'ff-pod-active': controlledTeamIds.includes(rightTeam.id) }"
                    :style="{ '--pod-color': rightTeam.color }"
                >
                    <span class="ff-pod-name">{{ rightTeam.name }}</span>
                    <span class="ff-pod-score font-logo">{{ rightTeam.total_score }}</span>
                </div>
            </div>

            <!-- Control banner -->
            <div v-if="currentQuestion" class="ff-control">
                <span v-if="isAllPlay" class="ff-control-pill ff-control-allplay font-logo">ALL PLAY</span>
                <span
                    v-else-if="controllingTeam"
                    class="ff-control-pill font-logo"
                    :style="{ '--pod-color': controllingTeam.color }"
                >{{ controllingTeam.name }} IN CONTROL</span>
            </div>

            <!-- Question strip (host reads it; we surface it for remote players) -->
            <div v-if="currentQuestion" class="ff-question font-logo">
                {{ currentQuestion.question_text }}
            </div>

            <!-- The answer board: one column of full-width strips (modern set) -->
            <div v-if="currentQuestion" class="ff-panel">
                <div class="ff-panel-inner">
                    <div
                        v-for="ans in rankedAnswers"
                        :key="ans.id"
                        class="ff-slot"
                        :class="{ revealed: ans.revealed }"
                    >
                        <div class="ff-slot-inner">
                            <div class="ff-face ff-front">
                                <span class="ff-num font-logo">{{ ans.slot }}</span>
                            </div>
                            <div class="ff-face ff-back">
                                <span class="ff-atext font-logo">{{ ans.answer_text }}</span>
                                <span class="ff-pts font-logo">{{ ans.points }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="ff-waiting font-logo">Waiting for next question…</div>

            <!-- Timer -->
            <div
                v-if="currentQuestion && hasTimer"
                class="ff-timer font-logo"
                :class="{ 'ff-timer-warn': timerWarning, 'ff-timer-expired': timerExpired }"
            >
                {{ timerDisplay }}
            </div>
        </template>

        <!-- ===================== LOBBY ===================== -->
        <div v-else-if="status === 'lobby'" class="ff-center">
            <div class="ff-logo ff-logo-xl font-logo" aria-label="Family Feud">
                <span class="ff-logo-line">FAMILY</span>
                <span class="ff-logo-line ff-logo-feud">FEUD</span>
            </div>
            <p class="ff-subhead font-logo">Waiting to Start</p>
        </div>

        <!-- ===================== PAUSED ===================== -->
        <div v-else-if="status === 'paused'" class="ff-center">
            <p class="ff-headline font-logo">Paused</p>
        </div>

        <!-- ===================== GAME OVER ===================== -->
        <div v-else-if="status === 'completed'" class="ff-center">
            <p class="ff-headline ff-headline-sm font-logo">Game Over</p>
            <div class="ff-finalscores">
                <div v-for="team in orderedTeams" :key="team.id" class="ff-scorechip" :style="{ '--pod-color': team.color }">
                    <span class="ff-scorechip-name">{{ team.name }}</span>
                    <span class="ff-scorechip-pts font-logo">{{ team.total_score.toLocaleString() }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* =====================================================================
   Family Feud projector board. Colors here are intentionally OFF the
   Keeler palette — this is a replica of the real (modern, Steve Harvey
   era) Family Feud answer board (a sanctioned exception, projector-only,
   nothing sold): one column of full-width blue strips, white points cards.
   ===================================================================== */
.ff-board {
    position: relative;
    width: 100%;
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2vh 2vw 2.4vh;
    gap: 1.2vh;
    background:
        radial-gradient(130% 100% at 50% -8%, #123a86 0%, #0a2258 34%, #061a42 66%, #020c22 100%),
        #020c22;
    color: #eaf3ff;
}

/* ---------- top bar: team pods + round total ---------- */
.ff-topbar {
    width: 100%;
    max-width: 1600px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 2vw;
}
.ff-pod {
    --pod-color: #4aa3ff;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2vh;
    padding: 0.9vh 1.4vw;
    border-radius: 14px;
    background: linear-gradient(180deg, #123f92, #0a2352);
    border: 2px solid color-mix(in srgb, var(--pod-color) 55%, #0a2258);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), inset 0 0 18px rgba(0, 0, 0, 0.45);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.ff-pod-left { justify-self: start; }
.ff-pod-right { justify-self: end; }
.ff-pod-active {
    border-color: var(--pod-color);
    box-shadow: 0 0 26px color-mix(in srgb, var(--pod-color) 60%, transparent), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}
.ff-pod-name {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: clamp(13px, 1.4vw, 24px);
    color: color-mix(in srgb, var(--pod-color) 78%, #fff);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
}
.ff-pod-score {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: clamp(26px, 3.2vw, 56px);
    color: #fff;
    text-shadow: 0 0 14px rgba(120, 180, 255, 0.5);
    line-height: 1;
}

.ff-total-wrap { display: flex; flex-direction: column; align-items: center; gap: 0.7vh; }
/* Round total — beveled dark display with a blue rim and white digits. */
.ff-total {
    min-width: clamp(110px, 11vw, 200px);
    padding: 0.3vh 1.4vw;
    border-radius: 10px;
    background: linear-gradient(180deg, #0a1c44, #04102c);
    border: 2px solid #4f93ee;
    box-shadow: 0 0 20px rgba(79, 147, 238, 0.45), inset 0 0 20px rgba(0, 0, 0, 0.75);
    text-align: center;
    transition: transform 0.2s ease;
}
.ff-total-bump { animation: ff-bump 0.45s ease; }
@keyframes ff-bump {
    0% { transform: scale(1); }
    40% { transform: scale(1.16); }
    100% { transform: scale(1); }
}
.ff-total-num {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: clamp(28px, 3.6vw, 62px);
    color: #fff;
    text-shadow: 0 0 16px rgba(150, 200, 255, 0.8);
    line-height: 1;
}

/* ---------- Family Feud logo ---------- */
.ff-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 0.82;
    letter-spacing: 0.01em;
    transform: skewX(-6deg);
}
.ff-logo-line {
    font-weight: 700;
    text-transform: uppercase;
    font-size: clamp(18px, 2vw, 34px);
    background: linear-gradient(180deg, #eaf3ff 0%, #8fc0ff 48%, #2f7fe6 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    -webkit-text-stroke: 1.5px #0a2352;
    paint-order: stroke fill;
    filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.5));
}
.ff-logo-feud { font-size: clamp(22px, 2.5vw, 44px); }
.ff-logo-xl .ff-logo-line { font-size: clamp(54px, 10vw, 148px); }
.ff-logo-xl .ff-logo-feud { font-size: clamp(70px, 13vw, 196px); }

/* ---------- control banner ---------- */
.ff-control { min-height: 1.1em; display: flex; justify-content: center; }
.ff-control-pill {
    --pod-color: #4aa3ff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: clamp(13px, 1.5vw, 24px);
    padding: 0.3em 1.3em;
    border-radius: 999px;
    color: #fff;
    background: linear-gradient(180deg, color-mix(in srgb, var(--pod-color) 82%, #000), color-mix(in srgb, var(--pod-color) 52%, #000));
    border: 1px solid color-mix(in srgb, var(--pod-color) 60%, #fff);
    box-shadow: 0 0 18px color-mix(in srgb, var(--pod-color) 55%, transparent);
}
.ff-control-allplay {
    --pod-color: #ffb020;
    background: linear-gradient(90deg, #ff5a3c, #ffb020, #ff5a3c);
    border-color: #ffe08a;
    animation: ff-pulse 1.1s infinite;
}
@keyframes ff-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.06); }
}

/* ---------- question strip ---------- */
.ff-question {
    max-width: 1400px;
    text-align: center;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    line-height: 1.1;
    font-size: clamp(16px, 1.9vw, 34px);
    color: #cfe2ff;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
    padding: 0 2vw;
}

/* ---------- answer board ---------- */
.ff-panel {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    max-width: 1180px;
    display: flex;
    align-items: stretch;
    justify-content: center;
    padding: clamp(12px, 1.8vh, 26px) clamp(14px, 1.8vw, 30px);
    border-radius: 34px 34px 26px 26px;
    background: linear-gradient(180deg, #1e5abd 0%, #123f92 48%, #0a2a66 100%);
    border: 5px solid #5b9bff;
    box-shadow:
        0 0 60px rgba(70, 140, 240, 0.5),
        0 10px 40px rgba(0, 0, 0, 0.6),
        inset 0 0 0 2px rgba(255, 255, 255, 0.12),
        inset 0 0 70px rgba(0, 8, 32, 0.6);
}
.ff-panel-inner {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: clamp(6px, 1vh, 14px);
    justify-content: center;
}

/* Each strip flips on its horizontal axis (rotateX) to reveal the answer. */
.ff-slot {
    position: relative;
    flex: 1 1 0;
    min-height: 42px;
    max-height: 104px;
    perspective: 1600px;
}
.ff-slot-inner {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    transition: transform 0.7s cubic-bezier(0.45, 0.05, 0.2, 1);
}
.ff-slot.revealed .ff-slot-inner { transform: rotateX(180deg); }
.ff-face {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    border-radius: 12px;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    overflow: hidden;
    background: linear-gradient(180deg, #3f7ad8 0%, #245bb0 46%, #163f8c 100%);
    border: 1px solid rgba(150, 190, 255, 0.55);
    box-shadow: inset 0 2px 3px rgba(255, 255, 255, 0.35), inset 0 -12px 26px rgba(0, 10, 40, 0.55);
}
/* Hidden face: big centered rank number seated in a soft recessed oval. */
.ff-front { justify-content: center; }
.ff-front::before {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    width: clamp(52px, 8vh, 96px);
    height: clamp(34px, 5.2vh, 62px);
    transform: translate(-50%, -50%);
    border-radius: 50%;
    background: radial-gradient(ellipse at 50% 35%, rgba(10, 26, 66, 0.55), rgba(10, 26, 66, 0) 70%);
}
.ff-num {
    position: relative;
    font-weight: 700;
    font-size: clamp(24px, 4.6vh, 52px);
    color: #fff;
    text-shadow: 0 2px 3px rgba(0, 0, 0, 0.6), 0 0 18px rgba(150, 200, 255, 0.7);
}
/* Revealed face: answer text on the left, white points card on the right. */
.ff-back {
    transform: rotateX(180deg);
    justify-content: space-between;
    gap: 0.6vw;
    padding: 0 clamp(6px, 0.7vw, 12px) 0 clamp(16px, 2vw, 34px);
}
.ff-atext {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.01em;
    font-size: clamp(15px, 3vh, 34px);
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: left;
}
.ff-pts {
    flex: 0 0 auto;
    align-self: stretch;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: clamp(52px, 5vw, 92px);
    margin: clamp(5px, 0.9vh, 12px) 0;
    border-radius: 8px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: clamp(20px, 3.6vh, 44px);
    color: #103a86;
    background: linear-gradient(180deg, #ffffff, #d6e4fb);
    box-shadow: inset 0 2px 2px rgba(255, 255, 255, 0.9), inset 0 -3px 6px rgba(80, 120, 190, 0.45), 0 2px 4px rgba(0, 0, 0, 0.4);
}
.ff-slot.revealed .ff-back { animation: ff-reveal-glow 0.8s ease; }
@keyframes ff-reveal-glow {
    0% { box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.9), 0 0 34px rgba(140, 190, 255, 0.9); }
    100% { box-shadow: inset 0 2px 3px rgba(255, 255, 255, 0.35), inset 0 -12px 26px rgba(0, 10, 40, 0.55); }
}

.ff-waiting {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: clamp(22px, 3vw, 48px);
    color: #7fa8dd;
}

/* ---------- timer ---------- */
.ff-timer {
    position: absolute;
    right: 2.6%;
    bottom: 3.4%;
    z-index: 20;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: clamp(20px, 2.2vw, 38px);
    letter-spacing: 0.05em;
    color: #eaf3ff;
    padding: 0.28em 1em;
    border-radius: 999px;
    background: rgba(4, 18, 51, 0.78);
    border: 2px solid #5b9bff;
    box-shadow: 0 0 22px rgba(79, 147, 238, 0.45), inset 0 0 12px rgba(0, 0, 0, 0.4);
}
.ff-timer-warn {
    border-color: #ff5a3c;
    color: #fff;
    background: rgba(120, 24, 16, 0.82);
    animation: ff-pulse 1s infinite;
}
.ff-timer-expired { border-color: #ff5a3c; color: #fff; background: #b81f16; }

/* ---------- centered overlays (lobby / paused / game over) ---------- */
.ff-center {
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
.ff-headline {
    color: #eaf3ff;
    font-weight: 700;
    font-size: clamp(40px, 7vw, 120px);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    text-shadow: 0 0 26px rgba(120, 180, 255, 0.6), 0 4px 10px rgba(0, 0, 0, 0.6);
}
.ff-headline-sm { font-size: clamp(32px, 5vw, 84px); }
.ff-subhead {
    color: #eaf3ff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: clamp(20px, 2.6vw, 46px);
    text-shadow: 0 0 16px rgba(120, 180, 255, 0.5), 0 2px 6px rgba(0, 0, 0, 0.55);
}
.ff-finalscores { display: flex; flex-wrap: wrap; gap: clamp(16px, 3vw, 48px); justify-content: center; }
.ff-scorechip {
    --pod-color: #4aa3ff;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4vh;
    padding: 1.4vh 2.4vw;
    border-radius: 18px;
    background: rgba(6, 20, 51, 0.7);
    border: 3px solid var(--pod-color);
    box-shadow: 0 0 22px color-mix(in srgb, var(--pod-color) 45%, transparent);
}
.ff-scorechip-name {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: clamp(18px, 2vw, 34px);
    color: var(--pod-color);
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}
.ff-scorechip-pts {
    color: #fff;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    font-size: clamp(32px, 4vw, 68px);
    text-shadow: 0 0 16px rgba(120, 180, 255, 0.5);
}

@media (prefers-reduced-motion: reduce) {
    .ff-slot-inner { transition: none; }
    .ff-slot.revealed .ff-back,
    .ff-total-bump,
    .ff-control-allplay,
    .ff-timer-warn { animation: none; }
}
</style>
