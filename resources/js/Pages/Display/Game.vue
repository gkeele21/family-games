<script setup lang="ts">
import Scoreboard from '@/Components/Scoreboard.vue';
import GameTimer from '@/Components/GameTimer.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

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
    round_number: number;
    active_team_id: number | null;
    active_team_name: string | null;
    timer_started_at: string | null;
    timer_duration: number;
    remaining_seconds: number | null;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    status: string;
    control_status: string;
    controlling_team_id: number | null;
    controlling_team_ids: number[];
    answers: Answer[];
}

interface CurrentCard {
    id: number;
    card_number: number;
    letter: string;
}

interface Props {
    gameSession: {
        id: number;
        name: string | null;
        status: string;
        invite_code: string;
        game_type: {
            name: string;
            slug: string;
        };
    };
    teams: Team[];
}

const props = defineProps<Props>();

const teams = ref<Team[]>(props.teams);
const gameState = ref<GameState | null>(null);
const currentQuestion = ref<CurrentQuestion | null>(null);
const currentCard = ref<CurrentCard | null>(null);
const status = ref(props.gameSession.status);
let pollInterval: number | null = null;

const fetchState = async () => {
    try {
        const response = await axios.get(`/display/${props.gameSession.invite_code}/state`);
        teams.value = response.data.teams;
        gameState.value = response.data.gameState;
        currentQuestion.value = response.data.currentQuestion;
        currentCard.value = response.data.currentCard;
        status.value = response.data.status;
    } catch (error) {
        console.error('Failed to fetch state:', error);
    }
};

onMounted(() => {
    fetchState();
    pollInterval = window.setInterval(fetchState, 500);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

const getControllingTeamName = () => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    const team = teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
    return team?.name ?? null;
};

const getControllingTeam = () => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    return teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
};

const isAllPlay = computed(() => currentQuestion.value?.control_status === 'all_play');

const sortedTeams = computed(() => {
    return [...teams.value].sort((a, b) => b.total_score - a.total_score);
});

const winningTeam = computed(() => {
    if (teams.value.length === 0) return null;
    return teams.value.reduce((a, b) => a.total_score > b.total_score ? a : b);
});
</script>

<template>
    <Head :title="`${gameSession.game_type.name} - Display`" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 text-white overflow-hidden">
        <!-- Waiting for Game to Start -->
        <div v-if="status === 'lobby'" class="min-h-screen flex flex-col">
            <!-- Header -->
            <div class="bg-black/40 p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-4xl font-bold">{{ gameSession.game_type.name }}</h1>
                    <div class="text-right">
                        <div class="text-gray-400 text-lg">Join with code</div>
                        <div class="text-5xl font-mono font-bold tracking-widest">{{ gameSession.invite_code }}</div>
                    </div>
                </div>
            </div>

            <!-- Waiting Content -->
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="text-8xl mb-6 animate-pulse">&#9203;</div>
                    <h2 class="text-5xl font-bold mb-4">Waiting for players...</h2>
                    <p class="text-2xl text-gray-300">Game will start soon</p>

                    <!-- Teams Preview -->
                    <div v-if="teams.length > 0" class="mt-12">
                        <h3 class="text-2xl text-gray-400 mb-6">Teams</h3>
                        <div class="flex flex-wrap justify-center gap-6">
                            <div
                                v-for="team in teams"
                                :key="team.id"
                                class="px-8 py-4 rounded-xl text-2xl font-bold"
                                :style="{ backgroundColor: team.color }"
                            >
                                {{ team.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game In Progress -->
        <div v-else-if="status === 'playing'" class="min-h-screen flex flex-col">
            <!-- Compact Header -->
            <div class="bg-black/40 p-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold">{{ gameSession.game_type.name }}</h1>

                    <!-- Scoreboard in header -->
                    <div class="flex items-center gap-6">
                        <div
                            v-for="team in sortedTeams"
                            :key="team.id"
                            class="flex items-center gap-3 px-4 py-2 rounded-lg transition-all"
                            :class="{
                                'ring-4 ring-yellow-400': currentQuestion?.controlling_team_id === team.id,
                                'bg-white/10': currentQuestion?.controlling_team_id !== team.id,
                            }"
                            :style="{
                                backgroundColor: currentQuestion?.controlling_team_id === team.id ? team.color : undefined,
                            }"
                        >
                            <div
                                class="w-4 h-4 rounded-full"
                                :style="{ backgroundColor: team.color }"
                            ></div>
                            <span class="font-bold text-xl">{{ team.name }}</span>
                            <span class="text-2xl font-mono font-bold">{{ team.total_score }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Game Area -->
            <div class="flex-1 flex flex-col p-6">
                <!-- Card Info (for Oodles) -->
                <div v-if="currentCard" class="text-center mb-4">
                    <span class="text-xl text-gray-400">Card {{ currentCard.card_number }}</span>
                    <span class="text-6xl font-bold ml-4 text-yellow-400">{{ currentCard.letter }}</span>
                </div>

                <!-- Control Status Banner -->
                <div v-if="currentQuestion" class="mb-4">
                    <!-- All Play -->
                    <div v-if="isAllPlay" class="bg-gradient-to-r from-red-500 via-yellow-500 to-green-500 text-white rounded-xl p-4 text-center animate-pulse">
                        <span class="text-3xl font-bold">ALL PLAY!</span>
                    </div>
                    <!-- Team Control -->
                    <div
                        v-else-if="getControllingTeam()"
                        class="rounded-xl p-4 text-center text-white"
                        :style="{ backgroundColor: getControllingTeam()?.color }"
                    >
                        <span class="text-3xl font-bold">{{ getControllingTeamName() }}</span>
                        <span class="text-2xl ml-3">has control</span>
                    </div>
                </div>

                <!-- Timer -->
                <div v-if="gameState?.timer_started_at" class="flex justify-center mb-6">
                    <div class="scale-150">
                        <GameTimer
                            :timer-started-at="gameState.timer_started_at"
                            :timer-duration="gameState.timer_duration"
                            :is-host="false"
                        />
                    </div>
                </div>

                <!-- Question & Answers -->
                <div class="flex-1 flex flex-col">
                    <div v-if="currentQuestion" class="flex-1 flex flex-col">
                        <!-- Question -->
                        <h2 class="text-5xl font-bold text-center mb-8 px-8">
                            {{ currentQuestion.question_text }}
                        </h2>

                        <!-- Answers Grid -->
                        <div class="flex-1 grid grid-cols-2 gap-4 px-8 max-w-6xl mx-auto w-full">
                            <div
                                v-for="answer in currentQuestion.answers"
                                :key="answer.id"
                                class="flex items-center justify-between p-6 rounded-xl text-center transition-all duration-500"
                                :class="{
                                    'bg-green-500 text-white scale-105 shadow-2xl shadow-green-500/50': answer.revealed,
                                    'bg-white/10 backdrop-blur': !answer.revealed,
                                }"
                            >
                                <div class="text-2xl font-semibold flex-1">
                                    {{ answer.answer_text }}
                                </div>
                                <div
                                    v-if="answer.revealed && answer.points"
                                    class="text-xl font-bold bg-black/30 px-4 py-2 rounded-lg ml-4"
                                >
                                    {{ answer.points }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Active Question -->
                    <div v-else class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-6xl mb-4 opacity-50">&#128173;</div>
                            <p class="text-3xl text-gray-400">Waiting for next question...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Paused -->
        <div v-else-if="status === 'paused'" class="min-h-screen flex items-center justify-center">
            <div class="text-center">
                <div class="text-8xl mb-6">&#9208;</div>
                <h2 class="text-5xl font-bold mb-4">Game Paused</h2>
                <p class="text-2xl text-gray-300">Waiting for host to resume...</p>
            </div>
        </div>

        <!-- Game Completed -->
        <div v-else-if="status === 'completed'" class="min-h-screen flex items-center justify-center">
            <div class="text-center">
                <div class="text-8xl mb-6">&#127942;</div>
                <h2 class="text-6xl font-bold mb-8">Game Over!</h2>

                <!-- Final Scores -->
                <div class="flex flex-wrap justify-center gap-8 mb-12">
                    <div
                        v-for="(team, index) in sortedTeams"
                        :key="team.id"
                        class="px-8 py-6 rounded-2xl text-center transition-all"
                        :class="{
                            'scale-125 ring-4 ring-yellow-400': index === 0,
                        }"
                        :style="{ backgroundColor: team.color }"
                    >
                        <div v-if="index === 0" class="text-4xl mb-2">&#128081;</div>
                        <div class="text-3xl font-bold mb-2">{{ team.name }}</div>
                        <div class="text-4xl font-mono font-bold">{{ team.total_score }}</div>
                    </div>
                </div>

                <div v-if="winningTeam" class="text-4xl">
                    <span class="text-yellow-400 font-bold">{{ winningTeam.name }}</span>
                    <span class="text-gray-300 ml-2">wins!</span>
                </div>
            </div>
        </div>
    </div>
</template>
