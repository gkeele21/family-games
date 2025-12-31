<script setup lang="ts">
import GameTimer from '@/Components/GameTimer.vue';
import { computed } from 'vue';

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
}

interface GameState {
    timer_started_at: string | null;
    timer_duration: number;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    control_status: string;
    controlling_team_id: number | null;
    controlling_team_ids: number[];
}

interface CurrentCard {
    id: number;
    card_number: number;
    letter: string;
}

interface Props {
    teams: Team[];
    gameState: GameState | null;
    currentQuestion: CurrentQuestion | null;
    currentCard: CurrentCard | null;
    inviteCode: string;
}

const props = defineProps<Props>();

const sortedTeams = computed(() => {
    return [...props.teams].sort((a, b) => b.total_score - a.total_score);
});

const getControllingTeam = () => {
    if (!props.currentQuestion?.controlling_team_id) return null;
    return props.teams.find(t => t.id === props.currentQuestion?.controlling_team_id);
};

const getControllingTeamName = () => {
    return getControllingTeam()?.name ?? null;
};

const getControllingTeams = () => {
    const teamIds = props.currentQuestion?.controlling_team_ids || [];
    return props.teams.filter(t => teamIds.includes(t.id));
};

const isAllPlay = computed(() => props.currentQuestion?.control_status === 'all_play');
const hasMultipleTeamControl = computed(() => {
    const teamIds = props.currentQuestion?.controlling_team_ids || [];
    return props.currentQuestion?.control_status === 'team_control' && teamIds.length > 1;
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gradient-to-br from-green-900 via-teal-900 to-cyan-900 text-white">
        <!-- Header with Scores -->
        <div class="bg-black/40 p-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Oodles</h1>

                <!-- Scoreboard -->
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
            <!-- Card Info -->
            <div v-if="currentCard" class="text-center mb-6">
                <div class="inline-block bg-white/10 backdrop-blur rounded-2xl px-8 py-4">
                    <span class="text-xl text-gray-300">Card {{ currentCard.card_number }}</span>
                    <span class="text-8xl font-bold ml-6 text-yellow-400">{{ currentCard.letter }}</span>
                </div>
            </div>

            <!-- Control Status -->
            <div v-if="currentQuestion" class="mb-6">
                <!-- All Play -->
                <div v-if="isAllPlay" class="bg-gradient-to-r from-red-500 via-yellow-500 to-green-500 text-white rounded-xl p-4 text-center animate-pulse">
                    <span class="text-4xl font-bold">ALL PLAY!</span>
                </div>

                <!-- Multiple Teams in Control -->
                <div v-else-if="hasMultipleTeamControl" class="bg-orange-500/80 rounded-xl p-4 text-center">
                    <span class="text-2xl font-bold">Multiple Teams Have Control:</span>
                    <div class="flex items-center justify-center gap-3 mt-2">
                        <template v-for="(team, index) in getControllingTeams()" :key="team.id">
                            <span v-if="index > 0" class="text-xl">&</span>
                            <span
                                class="px-4 py-2 rounded-full text-white font-bold text-xl"
                                :style="{ backgroundColor: team.color }"
                            >
                                {{ team.name }}
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Single Team Control -->
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

            <!-- Question -->
            <div v-if="currentQuestion" class="flex-1 flex items-center justify-center">
                <div class="text-center max-w-4xl">
                    <h2 class="text-6xl font-bold leading-tight">
                        {{ currentQuestion.question_text }}
                    </h2>
                    <p class="text-3xl text-yellow-400 mt-6 font-semibold">
                        Answer starts with "{{ currentCard?.letter }}"
                    </p>
                </div>
            </div>

            <!-- No Active Question -->
            <div v-else class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <p class="text-3xl text-gray-400">Waiting for next question...</p>
                </div>
            </div>
        </div>
    </div>
</template>
