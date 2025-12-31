<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

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
    is_steal_round?: boolean;
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
const showTimer = computed(() => props.gameState?.timer_started_at !== null);
const isStealRound = computed(() => props.gameState?.is_steal_round ?? false);

// Sort teams by score
const sortedTeams = computed(() => {
    return [...props.teams].sort((a, b) => b.total_score - a.total_score);
});

// Get controlling team
const getControllingTeam = computed(() => {
    if (!props.currentQuestion?.controlling_team_id) return null;
    return props.teams.find(t => t.id === props.currentQuestion?.controlling_team_id);
});

// Sort answers by display_order for proper grid layout
const sortedAnswers = computed(() => {
    if (!props.currentQuestion?.answers) return [];
    return [...props.currentQuestion.answers].sort((a, b) => a.display_order - b.display_order);
});

// Count revealed answers
const revealedCount = computed(() => {
    return sortedAnswers.value.filter(a => a.revealed).length;
});

// Get answer display (first letters + underscores) - from propoff
const getAnswerDisplay = (answer: Answer): string => {
    const words = answer.answer_text.split(' ');

    const displayWords = words.map(word => {
        const parts = word.split('-');
        const displayParts = parts.map(part => {
            const firstLetter = part.charAt(0).toUpperCase();
            const underscoreCount = Math.floor((part.length - 1) * 1.5);
            const underscores = '_'.repeat(underscoreCount);
            return firstLetter + underscores;
        });
        return displayParts.join('-');
    });

    return displayWords.join(' ');
};

// Get font size based on display_order (rank) - DOUBLED for TV visibility
const getAnswerFontSize = (displayOrder: number): string => {
    const sizes: Record<number, string> = {
        1: '4.5rem',   // Most popular - biggest (was 2.25rem)
        2: '3.5rem',   // was 1.75rem
        3: '3rem',     // was 1.5rem
        4: '2.7rem',   // was 1.35rem
        5: '2.4rem',   // was 1.2rem
        6: '2.2rem',   // was 1.1rem
        7: '2rem',     // Least popular - smallest (was 1rem)
    };
    return sizes[displayOrder] || '2.7rem';
};

// Get position class based on display_order - rank 1 in center row
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
    <div class="h-screen flex flex-col bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 text-white relative overflow-hidden">
        <!-- Animated background particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="particle particle-1"></div>
            <div class="particle particle-2"></div>
            <div class="particle particle-3"></div>
            <div class="particle particle-4"></div>
            <div class="particle particle-5"></div>
        </div>

        <!-- Header with Scoreboard -->
        <div class="flex-shrink-0 bg-black/30 backdrop-blur-sm border-b border-white/10" style="z-index: 20;">
            <div class="flex items-center justify-between px-6 py-3">
                <!-- Team Scores -->
                <div class="flex items-center gap-4">
                    <div
                        v-for="team in sortedTeams"
                        :key="team.id"
                        class="flex items-center gap-3 px-5 py-2 rounded-xl transition-all duration-300"
                        :class="{
                            'ring-2 ring-yellow-400 ring-offset-2 ring-offset-transparent scale-105': currentQuestion?.controlling_team_id === team.id,
                        }"
                        :style="{
                            backgroundColor: team.color,
                            boxShadow: currentQuestion?.controlling_team_id === team.id ? `0 0 20px ${team.color}` : 'none',
                        }"
                    >
                        <span class="font-bold text-xl text-white drop-shadow-lg">{{ team.name }}</span>
                        <span class="text-3xl font-mono font-black text-white drop-shadow-lg">{{ team.total_score }}</span>
                    </div>
                </div>

                <!-- Timer (in header) -->
                <div
                    v-if="showTimer"
                    class="text-4xl font-black font-mono tabular-nums px-6 py-2 rounded-xl shadow-2xl transition-all duration-300"
                    :class="{
                        'bg-green-500 text-white': !timerWarning && !timerExpired,
                        'bg-red-500 text-white animate-pulse scale-110': timerWarning,
                        'bg-red-900 text-white': timerExpired,
                    }"
                >
                    {{ timerDisplay }}
                </div>

                <!-- Progress indicator -->
                <div v-if="sortedAnswers.length > 0" class="text-right">
                    <div class="text-sm text-gray-400 uppercase tracking-wider">Found</div>
                    <div class="text-3xl font-black">
                        <span class="text-green-400">{{ revealedCount }}</span>
                        <span class="text-gray-500">/</span>
                        <span class="text-white">{{ sortedAnswers.length }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Steal Round Banner -->
        <div
            v-if="isStealRound && getControllingTeam"
            class="flex-shrink-0 bg-gradient-to-r from-orange-600 via-red-500 to-orange-600 py-2 text-center animate-pulse"
            style="z-index: 15;"
        >
            <span class="text-2xl font-black uppercase tracking-wider">
                Steal Round! {{ getControllingTeam.name }} can steal!
            </span>
        </div>

        <!-- Question Display -->
        <div class="flex-shrink-0 py-4 px-6" style="z-index: 10;">
            <div class="text-center">
                <h1
                    v-if="currentQuestion"
                    class="text-4xl font-bold px-8 py-4 rounded-2xl inline-block bg-white/10 backdrop-blur-sm border border-white/20 shadow-2xl"
                >
                    {{ currentQuestion.question_text }}
                </h1>
                <h1
                    v-else
                    class="text-3xl px-8 py-4 rounded-2xl inline-block bg-gray-800/50"
                >
                    Waiting for question...
                </h1>
            </div>
        </div>

        <!-- Answers Grid (only shown when timer is running) -->
        <div
            v-if="sortedAnswers.length > 0 && showAnswers"
            class="flex-1 grid grid-cols-2 gap-3 px-6 pb-4 max-w-7xl mx-auto w-full relative"
            style="z-index: 10;"
        >
            <div
                v-for="answer in sortedAnswers"
                :key="answer.id"
                :class="getAnswerPositionClass(answer.display_order)"
                class="flex items-center justify-center overflow-hidden"
            >
                <div
                    class="w-full h-full flex items-center justify-center p-3 rounded-2xl transition-all duration-500"
                    :class="{
                        'bg-gradient-to-br from-green-500 to-emerald-600 shadow-2xl shadow-green-500/30 scale-[1.02]': answer.revealed,
                        'bg-white/5 backdrop-blur-sm border border-white/10': !answer.revealed,
                    }"
                    :style="{
                        fontSize: getAnswerFontSize(answer.display_order),
                    }"
                >
                    <div class="text-center">
                        <!-- Unrevealed: Show first letter + underscores -->
                        <span
                            v-if="!answer.revealed"
                            class="text-blue-300/80 font-bold"
                            style="letter-spacing: -0.1em;"
                        >
                            {{ getAnswerDisplay(answer) }}
                        </span>
                        <!-- Revealed: Show full answer -->
                        <span
                            v-else
                            class="uppercase text-white font-black drop-shadow-lg typing-reveal"
                        >
                            {{ answer.answer_text }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Waiting state when no timer -->
        <div
            v-else-if="currentQuestion && !showAnswers"
            class="flex-1 flex items-center justify-center"
            style="z-index: 10;"
        >
            <div class="text-center">
                <div class="text-8xl mb-6 animate-bounce">🎯</div>
                <p class="text-5xl text-white font-black mb-4">Get Ready!</p>
                <p class="text-2xl text-blue-300">{{ sortedAnswers.length }} answers to find</p>
            </div>
        </div>

        <!-- No question state -->
        <div
            v-else
            class="flex-1 flex items-center justify-center"
            style="z-index: 10;"
        >
            <div class="text-center">
                <div class="text-6xl mb-4 opacity-50">💭</div>
                <p class="text-3xl text-gray-400">Waiting for next question...</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Typing reveal animation */
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

/* Floating particles background */
.particle {
    position: absolute;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3));
    animation: float 20s infinite ease-in-out;
}

.particle-1 {
    width: 300px;
    height: 300px;
    top: -100px;
    left: -100px;
    animation-delay: 0s;
}

.particle-2 {
    width: 200px;
    height: 200px;
    top: 50%;
    right: -50px;
    animation-delay: -5s;
}

.particle-3 {
    width: 150px;
    height: 150px;
    bottom: -50px;
    left: 30%;
    animation-delay: -10s;
}

.particle-4 {
    width: 250px;
    height: 250px;
    top: 30%;
    left: 60%;
    animation-delay: -15s;
}

.particle-5 {
    width: 180px;
    height: 180px;
    bottom: 20%;
    right: 20%;
    animation-delay: -7s;
}

@keyframes float {
    0%, 100% {
        transform: translate(0, 0) scale(1);
        opacity: 0.3;
    }
    25% {
        transform: translate(30px, -30px) scale(1.1);
        opacity: 0.5;
    }
    50% {
        transform: translate(-20px, 20px) scale(0.9);
        opacity: 0.4;
    }
    75% {
        transform: translate(20px, 10px) scale(1.05);
        opacity: 0.35;
    }
}
</style>
