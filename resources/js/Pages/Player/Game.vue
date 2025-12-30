<script setup lang="ts">
import Scoreboard from '@/Components/Scoreboard.vue';
import GameTimer from '@/Components/GameTimer.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface TeamMember {
    id: number;
    display_name: string;
}

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
    members: TeamMember[];
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
    playerTeamId: number | null;
}

const props = defineProps<Props>();

const teams = ref<Team[]>(props.teams);
const playerTeamId = ref<number | null>(props.playerTeamId);
const gameState = ref<GameState | null>(null);
const currentQuestion = ref<CurrentQuestion | null>(null);
const currentCard = ref<CurrentCard | null>(null);
const status = ref(props.gameSession.status);
let pollInterval: number | null = null;

const fetchState = async () => {
    try {
        const response = await axios.get(route('player.state', props.gameSession.id));
        teams.value = response.data.teams;
        playerTeamId.value = response.data.playerTeamId;
        gameState.value = response.data.gameState;
        currentQuestion.value = response.data.currentQuestion;
        currentCard.value = response.data.currentCard;
        status.value = response.data.status;
    } catch (error) {
        console.error('Failed to fetch state:', error);
    }
};

const playerTeam = () => {
    if (!playerTeamId.value) return null;
    return teams.value.find(t => t.id === playerTeamId.value);
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
</script>

<template>
    <Head :title="gameSession.game_type.name" />

    <div class="min-h-screen bg-gradient-to-br from-blue-900 to-purple-900 text-white">
        <!-- Header -->
        <div class="bg-black/30 p-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">{{ gameSession.game_type.name }}</h1>
                <div class="text-lg">
                    Code: <span class="font-mono font-bold">{{ gameSession.invite_code }}</span>
                </div>
            </div>
        </div>

        <!-- Waiting for Game to Start -->
        <div v-if="status === 'lobby'" class="flex items-center justify-center min-h-[80vh]">
            <div class="text-center">
                <div class="text-6xl mb-4">&#9203;</div>
                <h2 class="text-3xl font-bold mb-4">Waiting for game to start...</h2>
                <p class="text-xl text-gray-300">The host will start the game soon</p>

                <!-- Player's Team Info -->
                <div v-if="playerTeam()" class="mt-6 mb-4">
                    <p class="text-lg text-gray-300">
                        You're on team
                        <span
                            class="font-bold px-3 py-1 rounded-full ml-1"
                            :style="{ backgroundColor: playerTeam()?.color, color: 'white' }"
                        >
                            {{ playerTeam()?.name }}
                        </span>
                    </p>
                </div>
                <div v-else class="mt-6 mb-4">
                    <p class="text-lg text-yellow-300">
                        Waiting for the host to assign you to a team...
                    </p>
                </div>

                <div class="mt-4">
                    <Scoreboard
                        :teams="teams"
                        :active-team-id="null"
                        :controlling-team-id="null"
                        :player-team-id="playerTeamId"
                        :show-members="true"
                    />
                </div>
            </div>
        </div>

        <!-- Game In Progress -->
        <div v-else-if="status === 'playing'" class="p-6">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Scoreboard (Left Sidebar) -->
                    <div class="lg:col-span-1">
                        <Scoreboard
                            :teams="teams"
                            :active-team-id="gameState?.active_team_id"
                            :controlling-team-id="currentQuestion?.controlling_team_id"
                            :player-team-id="playerTeamId"
                        />
                    </div>

                    <!-- Main Game Area -->
                    <div class="lg:col-span-3 space-y-6">
                        <!-- Current Card (for Oodles) -->
                        <div v-if="currentCard" class="bg-white/10 backdrop-blur rounded-lg p-4 text-center">
                            <span class="text-lg">Card {{ currentCard.card_number }}</span>
                            <span class="text-4xl font-bold ml-4">{{ currentCard.letter }}</span>
                        </div>

                        <!-- Controlling Team -->
                        <div v-if="getControllingTeamName()" class="bg-yellow-500 text-black rounded-lg p-4 text-center">
                            <span class="text-xl font-bold">{{ getControllingTeamName() }}</span>
                            <span class="ml-2">has control</span>
                        </div>

                        <!-- Timer -->
                        <div v-if="gameState" class="flex justify-center">
                            <GameTimer
                                :timer-started-at="gameState.timer_started_at"
                                :timer-duration="gameState.timer_duration"
                                :is-host="false"
                            />
                        </div>

                        <!-- Question & Answers -->
                        <div class="bg-white/10 backdrop-blur rounded-lg p-6">
                            <div v-if="currentQuestion">
                                <h3 class="text-3xl font-bold text-center mb-8">
                                    {{ currentQuestion.question_text }}
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div
                                        v-for="answer in currentQuestion.answers"
                                        :key="answer.id"
                                        class="p-4 rounded-lg text-center transition-all duration-500"
                                        :class="{
                                            'bg-green-500 text-white scale-105': answer.revealed,
                                            'bg-gray-700/50': !answer.revealed,
                                        }"
                                    >
                                        <div class="text-xl font-semibold">
                                            {{ answer.answer_text }}
                                        </div>
                                        <div v-if="answer.revealed && answer.points" class="text-lg mt-1">
                                            {{ answer.points }} pts
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center text-gray-300 py-12">
                                <p class="text-2xl">Waiting for next question...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Completed -->
        <div v-else-if="status === 'completed'" class="flex items-center justify-center min-h-[80vh]">
            <div class="text-center">
                <h2 class="text-4xl font-bold mb-8">Game Over!</h2>

                <div class="mb-8">
                    <Scoreboard
                        :teams="teams"
                        :active-team-id="null"
                        :controlling-team-id="null"
                        :player-team-id="playerTeamId"
                        :show-members="true"
                    />
                </div>

                <div v-if="teams.length > 0" class="text-2xl">
                    Winner:
                    <span class="font-bold text-yellow-400">
                        {{ teams.reduce((a, b) => a.total_score > b.total_score ? a : b).name }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
