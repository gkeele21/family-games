<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';

// Accent = the chosen theme's primary color. Board runs in dark mode.
const { theme } = useTheme();

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
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
}

const props = defineProps<Props>();

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
const showAnswers = computed(() => props.gameState?.timer_started_at !== null);

// Answers show in orange (revealed and unrevealed) — unless the accent itself
// is orange, in which case they use green so they stay distinct from the question.
const answerColorClass = computed(() =>
    theme.value === 'orange' ? 'text-success' : 'text-warning'
);

// Sort teams by score
// Fixed team order (Team 1 first, Team 2 second) — never reorder by score.
const sortedTeams = computed(() => {
    return [...props.teams].sort((a, b) => a.display_order - b.display_order);
});

// Sort answers by display_order for proper grid layout
const sortedAnswers = computed(() => {
    if (!props.currentQuestion?.answers) return [];
    return [...props.currentQuestion.answers].sort((a, b) => a.display_order - b.display_order);
});

// Font size based on display_order (rank) — biggest for the most popular answer.
const getAnswerFontSize = (displayOrder: number): string => {
    const sizes: Record<number, string> = {
        1: '5rem',   // Most popular — biggest
        2: '3.75rem',
        3: '3.25rem',
        4: '2.9rem',
        5: '2.6rem',
        6: '2.4rem',
        7: '2.2rem', // Least popular — smallest
    };
    return sizes[displayOrder] || '2.9rem';
};

// Position class based on display_order — rank 1 centered, others around it.
const getAnswerPositionClass = (displayOrder: number): string => {
    const positions: Record<number, string> = {
        1: 'col-span-2 row-start-2',
        2: 'col-start-1 row-start-1',
        3: 'col-start-2 row-start-1',
        4: 'col-start-1 row-start-3',
        5: 'col-start-2 row-start-3',
        6: 'col-start-1 row-start-4',
        7: 'col-start-2 row-start-4',
    };
    return positions[displayOrder] || '';
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
    <div class="keeler-app" :class="`theme-${theme}`">
        <div class="relative h-screen flex flex-col overflow-hidden bg-surface-inset text-body">
            <!-- Aurora glow (matches landing page) -->
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <span class="absolute -left-16 -top-24 h-[32rem] w-[32rem] rounded-full bg-primary/25 blur-[130px]"></span>
                <span class="absolute -right-20 top-1/3 h-[32rem] w-[32rem] rounded-full bg-info/20 blur-[140px]"></span>
                <span class="absolute -bottom-24 left-1/3 h-[28rem] w-[28rem] rounded-full bg-warning/15 blur-[120px]"></span>
            </div>

            <!-- Scoreboard strip with glowing orange divider -->
            <div
                class="relative z-10 flex-shrink-0 bg-surface-header/60 backdrop-blur-sm border-b border-warning/30 shadow-[0_4px_16px_-2px_rgb(var(--color-warning)/0.35)]"
            >
                <div class="flex items-center justify-between gap-4 px-6 py-3">
                    <!-- Team scores -->
                    <div class="flex items-center gap-3">
                        <div
                            v-for="team in sortedTeams"
                            :key="team.id"
                            class="flex items-center gap-3 px-5 py-2 rounded-xl bg-surface-elevated/50 transition-all duration-300"
                            :class="{
                                'ring-2 ring-white shadow-[0_0_22px_2px_rgba(255,255,255,0.6)] scale-105': currentQuestion?.controlling_team_id === team.id,
                            }"
                        >
                            <span class="font-bold text-xl drop-shadow" :style="{ color: team.color }">{{ team.name }}</span>
                            <span class="text-3xl font-black tabular-nums text-white drop-shadow">{{ team.total_score }}</span>
                        </div>
                    </div>

                    <!-- Timer (red) — always visible, even paused / not yet started -->
                    <div
                        class="text-4xl font-black tabular-nums px-6 py-2 rounded-xl shadow-2xl transition-all duration-300"
                        :class="{
                            'bg-surface-overlay/70 border border-border text-danger': !timerWarning && !timerExpired,
                            'bg-danger text-white animate-pulse scale-110': timerWarning,
                            'bg-danger text-white': timerExpired,
                        }"
                    >
                        {{ timerDisplay }}
                    </div>
                </div>
            </div>

            <!-- Question (accent color) -->
            <div class="relative z-10 flex-shrink-0 py-6 px-6">
                <div class="text-center">
                    <h1
                        v-if="currentQuestion"
                        class="font-logo text-5xl font-bold text-primary drop-shadow-[0_2px_12px_rgb(var(--color-primary)/0.35)]"
                    >
                        {{ currentQuestion.question_text }}
                    </h1>
                    <h1 v-else class="font-logo text-4xl font-bold text-muted">
                        Waiting for question…
                    </h1>
                </div>
            </div>

            <!-- Answers — full-page words, no boxed tiles -->
            <div
                v-if="sortedAnswers.length > 0 && showAnswers"
                class="relative z-10 flex-1 grid grid-cols-2 gap-x-10 gap-y-4 px-10 pb-8 max-w-6xl mx-auto w-full"
            >
                <div
                    v-for="answer in sortedAnswers"
                    :key="answer.id"
                    :class="getAnswerPositionClass(answer.display_order)"
                    class="flex items-center justify-center text-center overflow-hidden"
                    :style="{ fontSize: getAnswerFontSize(answer.display_order) }"
                >
                    <!-- Unrevealed: continuous solid line (word count hidden) -->
                    <span
                        v-if="!answer.revealed"
                        class="font-logo font-bold"
                        :class="answerColorClass"
                        style="letter-spacing: -0.15em;"
                    >
                        {{ answer.answer_text }}
                    </span>
                    <!-- Revealed: typewriter reveal of the real answer -->
                    <span
                        v-else
                        class="font-logo font-black uppercase typing-reveal"
                        :class="answerColorClass"
                    >
                        {{ answer.answer_text }}
                    </span>

                </div>
            </div>

            <!-- Waiting state (question set, timer not started) -->
            <div
                v-else-if="currentQuestion && !showAnswers"
                class="relative z-10 flex-1 flex items-center justify-center"
            >
                <div class="text-center">
                    <p class="font-logo text-6xl font-black text-body mb-4">Get Ready!</p>
                    <p class="text-2xl text-muted">{{ sortedAnswers.length }} answers to find</p>
                </div>
            </div>

            <!-- No question state -->
            <div v-else class="relative z-10 flex-1 flex items-center justify-center">
                <p class="text-3xl text-muted">Waiting for next question…</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Typewriter reveal on correct answers */
.typing-reveal {
    display: inline-block;
    animation: typing 0.4s steps(20) forwards;
    overflow: hidden;
    white-space: nowrap;
    max-width: 0;
}

@keyframes typing {
    from { max-width: 0; }
    to { max-width: 100%; }
}
</style>
