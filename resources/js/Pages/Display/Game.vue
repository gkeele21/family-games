<script setup lang="ts">
import AmericaSaysDisplay from './AmericaSaysDisplay.vue';
import FamilyFeudDisplay from './FamilyFeudDisplay.vue';
import OodlesDisplay from './OodlesDisplay.vue';
import DisplayFrame from '@/Components/Display/DisplayFrame.vue';
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
    bonus_question: {
        question_text: string;
        answer_text: string | null;
    } | null;
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

const gameSlug = props.gameSession.game_type.slug;
const isAmericaSays = gameSlug === 'america-says';
const isOodles = gameSlug === 'oodles';
const isFamilyFeud = gameSlug === 'family-feud';

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
    pollInterval = window.setInterval(fetchState, 300);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

const sortedTeams = computed(() => {
    return [...teams.value].sort((a, b) => b.total_score - a.total_score);
});

const winningTeam = computed(() => {
    if (teams.value.length === 0) return null;
    return teams.value.reduce((a, b) => a.total_score > b.total_score ? a : b);
});
</script>

<template>
    <Head :title="`${gameSession.game_type.name} - Display`">
        <!-- TV/projector presentation hints. On iOS, launching from an
             Add-to-Home-Screen icon uses these to open chromeless (no Safari
             bar) — the dependable "fullscreen" path on iPhone. Harmless on
             desktop browsers, which use the in-page fullscreen button instead. -->
        <meta head-key="amwac" name="apple-mobile-web-app-capable" content="yes" />
        <meta head-key="mwac" name="mobile-web-app-capable" content="yes" />
        <meta head-key="sbs" name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta head-key="awt" name="apple-mobile-web-app-title" content="Keeler Games" />
        <meta head-key="tc" name="theme-color" content="#404040" />
    </Head>

    <!-- Display boards live inside a full-viewport frame that owns the screen:
         fullscreen toggle, wake lock, cursor/controls auto-hide, and a live
         overscan-fit so the board fits whatever the TV renders. -->
    <DisplayFrame>
        <!-- America Says: one map-backed board handles every state (lobby /
             playing / paused / completed) so the neon map is a constant backdrop. -->
        <AmericaSaysDisplay
            v-if="isAmericaSays"
            :status="status"
            :teams="teams"
            :game-state="gameState"
            :current-question="currentQuestion"
            :invite-code="gameSession.invite_code"
        />

        <!-- Family Feud: one board handles every state (lobby / playing / paused /
             completed) so the lit set is a constant backdrop, like America Says. -->
        <FamilyFeudDisplay
            v-else-if="isFamilyFeud"
            :status="status"
            :teams="teams"
            :game-state="gameState"
            :current-question="currentQuestion"
            :invite-code="gameSession.invite_code"
        />

        <!-- Other games: original per-status screens -->
        <template v-else>
    <!-- Lobby State - Same for all games -->
    <div v-if="status === 'lobby'" class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 text-white">
        <div class="min-h-screen flex flex-col">
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
    </div>

    <!-- Playing State - Route to game-specific display -->
    <template v-else-if="status === 'playing'">
        <!-- America Says -->
        <AmericaSaysDisplay
            v-if="isAmericaSays"
            :teams="teams"
            :game-state="gameState"
            :current-question="currentQuestion"
            :invite-code="gameSession.invite_code"
        />

        <!-- Oodles -->
        <OodlesDisplay
            v-else-if="isOodles"
            :teams="teams"
            :game-state="gameState"
            :current-question="currentQuestion"
            :current-card="currentCard"
            :invite-code="gameSession.invite_code"
        />

        <!-- Family Feud is handled by <FamilyFeudDisplay> at the top level (it
             owns every status), so it never reaches this generic branch. -->
    </template>

    <!-- Paused State -->
    <div v-else-if="status === 'paused'" class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 text-white flex items-center justify-center">
        <div class="text-center">
            <div class="text-8xl mb-6">&#9208;</div>
            <h2 class="text-5xl font-bold mb-4">Game Paused</h2>
            <p class="text-2xl text-gray-300">Waiting for host to resume...</p>
        </div>
    </div>

    <!-- Completed State -->
    <div v-else-if="status === 'completed'" class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 text-white flex items-center justify-center">
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
        </template>
    </DisplayFrame>
</template>
