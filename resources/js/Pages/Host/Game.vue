<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Scoreboard from '@/Components/Scoreboard.vue';
import GameTimer from '@/Components/GameTimer.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
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
    points: number;
    display_order: number;
    revealed: boolean;
}

interface GameState {
    round_number: number;
    active_team_id: number | null;
    timer_started_at: string | null;
    timer_duration: number;
    remaining_seconds: number | null;
    state_data: Record<string, any>;
}

interface CurrentQuestion {
    id: number;
    question_text: string;
    status: string;
    control_status: string;
    controlling_team_id: number | null;
    controlling_team_ids: number[];
    answers: Answer[];
    revealed_answer_ids: number[];
}

interface CardQuestion {
    id: number;
    question_text: string;
    display_order: number;
    status: string;
    is_current: boolean;
}

interface CurrentCard {
    id: number;
    card_number: number;
    letter: string;
    status: string;
    questions: CardQuestion[];
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
    config: Record<string, any>;
}

const props = defineProps<Props>();

const teams = ref<Team[]>([]);
const gameState = ref<GameState | null>(null);
const currentQuestion = ref<CurrentQuestion | null>(null);
const currentCard = ref<CurrentCard | null>(null);
const totalCards = ref(0);
const selectedControllingTeams = ref<number[]>([]);
const showControlModal = ref(false);
const selectedAllPlayTeams = ref<number[]>([]);
let pollInterval: number | null = null;

const isOodles = props.gameSession.game_type.slug === 'oodles';

const fetchState = async () => {
    try {
        const response = await axios.get(route('host.state', props.gameSession.id));
        teams.value = response.data.teams;
        gameState.value = response.data.gameState;
        currentQuestion.value = response.data.currentQuestion;
        currentCard.value = response.data.currentCard;
        totalCards.value = response.data.totalCards || 0;
    } catch (error) {
        console.error('Failed to fetch state:', error);
    }
};

const startTimer = async () => {
    await axios.post(route('host.timer.start', props.gameSession.id));
    fetchState();
};

const pauseTimer = async () => {
    await axios.post(route('host.timer.pause', props.gameSession.id));
    fetchState();
};

const resetTimer = async () => {
    await axios.post(route('host.timer.reset', props.gameSession.id));
    fetchState();
};

const revealAnswer = async (answerId: number) => {
    const activeTeamId = gameState.value?.active_team_id;
    await axios.post(route('host.reveal', props.gameSession.id), {
        answer_id: answerId,
        team_id: activeTeamId,
    });
    fetchState();
};

const endGame = async () => {
    if (confirm('Are you sure you want to end the game?')) {
        await axios.post(route('host.end', props.gameSession.id));
        window.location.href = route('games.index');
    }
};

const selectQuestion = async (sessionQuestionId: number) => {
    await axios.post(route('host.question.select', props.gameSession.id), {
        session_question_id: sessionQuestionId,
    });
    fetchState();
};

const nextQuestion = async () => {
    const response = await axios.post(route('host.question.next', props.gameSession.id));
    if (response.data.card_complete) {
        // Show message that card is complete
        alert('All questions on this card are complete! Click "Next Card" to continue.');
    }
    if (response.data.game_complete) {
        window.location.href = route('games.index');
    }
    fetchState();
};

const nextCard = async () => {
    const response = await axios.post(route('host.card.next', props.gameSession.id));
    if (response.data.game_complete) {
        window.location.href = route('games.index');
    }
    fetchState();
};

const markCorrect = async (teamId: number) => {
    try {
        const response = await axios.post(route('host.question.correct', props.gameSession.id), {
            team_id: teamId,
        });
        console.log('markCorrect response:', response.data);
    } catch (error: any) {
        console.error('markCorrect error:', error.response?.data || error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
    fetchState();
};

const markCorrectMultiple = async () => {
    if (selectedAllPlayTeams.value.length === 0) {
        alert('Please select at least one team');
        return;
    }
    try {
        const response = await axios.post(route('host.question.correct', props.gameSession.id), {
            team_ids: selectedAllPlayTeams.value,
        });
        console.log('markCorrectMultiple response:', response.data);
        selectedAllPlayTeams.value = [];
    } catch (error: any) {
        console.error('markCorrectMultiple error:', error.response?.data || error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
    fetchState();
};

const toggleAllPlayTeam = (teamId: number) => {
    const index = selectedAllPlayTeams.value.indexOf(teamId);
    if (index === -1) {
        selectedAllPlayTeams.value.push(teamId);
    } else {
        selectedAllPlayTeams.value.splice(index, 1);
    }
};

const isAllPlayTeamSelected = (teamId: number): boolean => {
    return selectedAllPlayTeams.value.includes(teamId);
};

const markWrong = async () => {
    await axios.post(route('host.question.wrong', props.gameSession.id));
    fetchState();
};

const getControllingTeam = () => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    return teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
};

const isAllPlay = () => {
    return currentQuestion.value?.control_status === 'all_play';
};

const hasTeamControl = () => {
    return currentQuestion.value?.control_status === 'team_control' && getControllingTeam();
};

const hasMultipleTeamControl = () => {
    const teamIds = currentQuestion.value?.controlling_team_ids || [];
    return currentQuestion.value?.control_status === 'team_control' && teamIds.length > 1;
};

const getControllingTeams = () => {
    const teamIds = currentQuestion.value?.controlling_team_ids || [];
    return teams.value.filter(t => teamIds.includes(t.id));
};

const openControlModal = () => {
    // Initialize with currently controlling teams
    selectedControllingTeams.value = currentQuestion.value?.controlling_team_ids || [];
    showControlModal.value = true;
};

const closeControlModal = () => {
    showControlModal.value = false;
};

const toggleTeamControl = (teamId: number) => {
    const index = selectedControllingTeams.value.indexOf(teamId);
    if (index === -1) {
        selectedControllingTeams.value.push(teamId);
    } else {
        selectedControllingTeams.value.splice(index, 1);
    }
};

const isTeamSelected = (teamId: number): boolean => {
    return selectedControllingTeams.value.includes(teamId);
};

const saveControllingTeams = async () => {
    if (selectedControllingTeams.value.length === 0) {
        alert('Please select at least one team');
        return;
    }

    await axios.post(route('host.control', props.gameSession.id), {
        team_ids: selectedControllingTeams.value,
    });
    closeControlModal();
    fetchState();
};

const updateTeamScore = async (teamId: number, newScore: number) => {
    try {
        await axios.patch(route('host.teams.score.update', {
            gameSession: props.gameSession.id,
            team: teamId,
        }), {
            score: newScore,
        });
        fetchState();
    } catch (error: any) {
        console.error('Failed to update score:', error);
        alert('Error updating score: ' + (error.response?.data?.error || error.message));
    }
};

onMounted(() => {
    fetchState();
    pollInterval = window.setInterval(fetchState, 1000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head :title="`Host - ${gameSession.game_type.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Hosting: {{ gameSession.game_type.name }}
                    <span class="text-gray-500 font-normal ml-2">Code: {{ gameSession.invite_code }}</span>
                </h2>
                <button
                    @click="endGame"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    End Game
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Scoreboard (Left Sidebar) -->
                    <div class="lg:col-span-1">
                        <Scoreboard
                            :teams="teams"
                            :active-team-id="gameState?.active_team_id"
                            :controlling-team-ids="currentQuestion?.controlling_team_ids || []"
                            :editable="true"
                            @update-score="updateTeamScore"
                        />

                        <!-- Set Control Button -->
                        <div v-if="currentQuestion" class="mt-4">
                            <button
                                @click="openControlModal"
                                class="w-full px-4 py-3 bg-yellow-500 text-black font-bold rounded-lg hover:bg-yellow-400 transition-colors"
                            >
                                Set Team Control
                            </button>
                            <p class="text-xs text-gray-500 mt-2 text-center">
                                Select teams that have control (for All Play ties)
                            </p>
                        </div>
                    </div>

                    <!-- Main Game Area -->
                    <div class="lg:col-span-3 space-y-6">
                        <!-- Current Card (Oodles) -->
                        <div v-if="isOodles && currentCard" class="bg-gradient-to-r from-purple-600 to-blue-600 shadow-sm rounded-lg p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="text-6xl font-bold bg-white/20 rounded-lg px-4 py-2">
                                        {{ currentCard.letter }}
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold">Card {{ currentCard.card_number }} of {{ totalCards }}</h3>
                                        <p class="text-white/80">
                                            {{ currentCard.questions.filter(q => q.status === 'completed').length }} of {{ currentCard.questions.length }} questions completed
                                        </p>
                                    </div>
                                </div>
                                <button
                                    @click="nextCard"
                                    class="px-6 py-3 bg-white text-purple-600 font-bold rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    Next Card &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- Timer -->
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <GameTimer
                                v-if="gameState"
                                :timer-started-at="gameState.timer_started_at"
                                :timer-duration="gameState.timer_duration"
                                :is-host="true"
                                @start="startTimer"
                                @pause="pauseTimer"
                                @reset="resetTimer"
                            />
                        </div>

                        <!-- Question List for Oodles (when no question selected) -->
                        <div v-if="isOodles && currentCard && !currentQuestion" class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="text-xl font-bold mb-4">Select a Question</h3>
                            <p class="text-gray-500 mb-4">Click on a question to display it to players.</p>

                            <div class="space-y-2">
                                <button
                                    v-for="question in currentCard.questions"
                                    :key="question.id"
                                    @click="selectQuestion(question.id)"
                                    :disabled="question.status === 'completed'"
                                    class="w-full p-4 rounded-lg text-left transition-all flex items-center justify-between"
                                    :class="{
                                        'bg-green-100 text-green-800 cursor-not-allowed': question.status === 'completed',
                                        'bg-gray-100 hover:bg-blue-100 cursor-pointer': question.status !== 'completed',
                                    }"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg font-mono bg-gray-200 px-2 py-1 rounded">
                                            {{ question.display_order }}
                                        </span>
                                        <span class="font-medium">{{ question.question_text }}</span>
                                    </div>
                                    <span
                                        v-if="question.status === 'completed'"
                                        class="text-green-600 font-semibold"
                                    >
                                        ✓ Done
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Question & Answers -->
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <div v-if="currentQuestion">
                                <!-- Question Header -->
                                <div class="mb-6">
                                    <h3 class="text-2xl font-bold text-center">
                                        {{ currentQuestion.question_text }}
                                    </h3>
                                </div>

                                <!-- Oodles: Controlling Team & Actions -->
                                <div v-if="isOodles" class="mb-6">
                                    <!-- Multiple Teams in Control (from All Play tie) -->
                                    <div
                                        v-if="hasMultipleTeamControl()"
                                        class="bg-orange-100 border-2 border-orange-400 rounded-lg p-4 mb-4"
                                    >
                                        <div class="text-center mb-3">
                                            <span class="text-xl font-bold text-orange-700">Multiple Teams Have Control!</span>
                                            <div class="flex items-center justify-center gap-2 mt-2">
                                                <template v-for="(team, index) in getControllingTeams()" :key="team.id">
                                                    <span v-if="index > 0" class="text-gray-500">&</span>
                                                    <span
                                                        class="px-3 py-1 rounded-full text-white font-bold"
                                                        :style="{ backgroundColor: team.color }"
                                                    >
                                                        {{ team.name }}
                                                    </span>
                                                </template>
                                            </div>
                                        </div>

                                        <p class="text-center text-orange-600 mb-3">Select the team(s) that answered correctly:</p>

                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <button
                                                v-for="team in getControllingTeams()"
                                                :key="team.id"
                                                @click="toggleAllPlayTeam(team.id)"
                                                class="p-4 rounded-lg font-bold text-lg transition-all flex items-center justify-center gap-2 border-4"
                                                :class="{
                                                    'text-white': isAllPlayTeamSelected(team.id),
                                                    'text-gray-800 bg-white': !isAllPlayTeamSelected(team.id),
                                                }"
                                                :style="{
                                                    backgroundColor: isAllPlayTeamSelected(team.id) ? team.color : undefined,
                                                    borderColor: team.color,
                                                }"
                                            >
                                                <span v-if="isAllPlayTeamSelected(team.id)" class="text-xl">✓</span>
                                                <span>{{ team.name }}</span>
                                            </button>
                                        </div>

                                        <div class="flex gap-3 justify-center">
                                            <button
                                                @click="markCorrectMultiple"
                                                :disabled="selectedAllPlayTeams.length === 0"
                                                class="px-6 py-3 bg-green-500 text-white font-bold rounded-lg hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            >
                                                Award Points
                                            </button>
                                            <button
                                                @click="markWrong"
                                                class="px-6 py-3 bg-red-500 text-white font-bold rounded-lg hover:bg-red-600 transition-colors"
                                            >
                                                All Wrong (All Play)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Single Team in Control -->
                                    <div
                                        v-else-if="hasTeamControl()"
                                        class="bg-yellow-100 border-2 border-yellow-400 rounded-lg p-4 mb-4"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-6 h-6 rounded-full"
                                                    :style="{ backgroundColor: getControllingTeam()?.color }"
                                                ></div>
                                                <span class="text-xl font-bold">{{ getControllingTeam()?.name }}</span>
                                                <span class="text-gray-600">has control</span>
                                            </div>
                                        </div>

                                        <!-- Correct / Wrong Buttons -->
                                        <div class="flex gap-4 mt-4">
                                            <button
                                                @click="markCorrect(getControllingTeam()!.id)"
                                                class="flex-1 py-4 bg-green-500 text-white text-xl font-bold rounded-lg hover:bg-green-600 transition-colors"
                                            >
                                                ✓ Correct
                                            </button>
                                            <button
                                                @click="markWrong"
                                                class="flex-1 py-4 bg-red-500 text-white text-xl font-bold rounded-lg hover:bg-red-600 transition-colors"
                                            >
                                                ✗ Wrong (All Play)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- All Play Mode -->
                                    <div
                                        v-else-if="isAllPlay()"
                                        class="bg-purple-100 border-2 border-purple-400 rounded-lg p-4 mb-4"
                                    >
                                        <div class="text-center mb-4">
                                            <span class="text-2xl font-bold text-purple-700">ALL PLAY!</span>
                                            <p class="text-purple-600">Select all teams that answered correctly (points will be split):</p>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <button
                                                v-for="team in teams"
                                                :key="team.id"
                                                @click="toggleAllPlayTeam(team.id)"
                                                class="p-4 rounded-lg font-bold text-lg transition-all flex items-center justify-center gap-2 border-4"
                                                :class="{
                                                    'text-white': isAllPlayTeamSelected(team.id),
                                                    'text-gray-800 bg-white': !isAllPlayTeamSelected(team.id),
                                                }"
                                                :style="{
                                                    backgroundColor: isAllPlayTeamSelected(team.id) ? team.color : undefined,
                                                    borderColor: team.color,
                                                }"
                                            >
                                                <span v-if="isAllPlayTeamSelected(team.id)" class="text-xl">✓</span>
                                                <span>{{ team.name }}</span>
                                            </button>
                                        </div>

                                        <div class="flex gap-3 justify-center">
                                            <button
                                                @click="markCorrectMultiple"
                                                :disabled="selectedAllPlayTeams.length === 0"
                                                class="px-6 py-3 bg-green-500 text-white font-bold rounded-lg hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            >
                                                Award Points ({{ selectedAllPlayTeams.length }} team{{ selectedAllPlayTeams.length !== 1 ? 's' : '' }})
                                            </button>
                                            <button
                                                @click="nextQuestion"
                                                class="px-6 py-3 bg-gray-400 text-white font-bold rounded-lg hover:bg-gray-500 transition-colors"
                                            >
                                                Skip (no one got it)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- No controlling team (shouldn't normally happen) -->
                                    <div
                                        v-else
                                        class="bg-gray-100 rounded-lg p-4 mb-4 text-center text-gray-500"
                                    >
                                        <p>No team in control. Select a team to award points:</p>
                                        <div class="flex gap-2 mt-3 justify-center">
                                            <button
                                                v-for="team in teams"
                                                :key="team.id"
                                                @click="markCorrect(team.id)"
                                                class="px-4 py-2 rounded-lg font-medium text-white"
                                                :style="{ backgroundColor: team.color }"
                                            >
                                                {{ team.name }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Answer display (for non-Oodles games or reference) -->
                                <div v-if="!isOodles" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <button
                                        v-for="answer in currentQuestion.answers"
                                        :key="answer.id"
                                        @click="revealAnswer(answer.id)"
                                        :disabled="answer.revealed"
                                        class="p-4 rounded-lg text-left transition-all"
                                        :class="{
                                            'bg-green-100 border-2 border-green-500': answer.revealed,
                                            'bg-gray-100 hover:bg-blue-100 cursor-pointer': !answer.revealed,
                                        }"
                                    >
                                        <div class="flex justify-between items-center">
                                            <span class="font-semibold">
                                                {{ answer.revealed ? answer.answer_text : '???' }}
                                            </span>
                                            <span class="text-lg font-bold">
                                                {{ answer.points }} pts
                                            </span>
                                        </div>
                                    </button>
                                </div>

                                <!-- Oodles: Show the answer (host reference) -->
                                <div v-if="isOodles && currentQuestion.answers?.length > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 mb-1">Answer:</p>
                                    <p class="text-xl font-bold text-gray-800">
                                        {{ currentQuestion.answers[0]?.answer_text }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="!isOodles" class="text-center text-gray-500 py-12">
                                <p class="text-xl">No active question</p>
                                <p class="mt-2">Select a question to display it to players</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Modal -->
        <div
            v-if="showControlModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeControlModal"
        >
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-xl font-bold mb-2">Set Controlling Teams</h3>
                <p class="text-gray-600 mb-4">
                    Select one or more teams that have control of the next question.
                    Use this for All Play ties.
                </p>

                <div class="space-y-2 mb-6">
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        @click="toggleTeamControl(team.id)"
                        class="flex items-center justify-between p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="{
                            'border-yellow-500 bg-yellow-50': isTeamSelected(team.id),
                            'border-gray-200 hover:border-gray-300': !isTeamSelected(team.id),
                        }"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="w-5 h-5 rounded-full"
                                :style="{ backgroundColor: team.color }"
                            ></div>
                            <span class="font-semibold">{{ team.name }}</span>
                        </div>
                        <div
                            v-if="isTeamSelected(team.id)"
                            class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center"
                        >
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div
                            v-else
                            class="w-6 h-6 border-2 border-gray-300 rounded-full"
                        ></div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        @click="closeControlModal"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveControllingTeams"
                        :disabled="selectedControllingTeams.length === 0"
                        class="px-4 py-2 bg-yellow-500 text-black font-bold rounded-lg hover:bg-yellow-400 disabled:opacity-50"
                    >
                        Save Control
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
