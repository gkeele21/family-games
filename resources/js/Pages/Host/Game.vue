<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import Scoreboard from '@/Components/Scoreboard.vue';
import GameTimer from '@/Components/GameTimer.vue';
import Card from '@/Components/Base/Card.vue';
import Button from '@/Components/Base/Button.vue';
import Modal from '@/Components/Base/Modal.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';
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
    is_steal_round: boolean;
    steal_points_percentage: number;
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
const currentQuestionNumber = ref<number | null>(null);
const totalQuestions = ref<number | null>(null);
const selectedControllingTeams = ref<number[]>([]);
const showControlModal = ref(false);
const selectedAllPlayTeams = ref<number[]>([]);
const showStealModal = ref(false);
const timerExpiredHandled = ref(false);
let pollInterval: number | null = null;

const isOodles = props.gameSession.game_type.slug === 'oodles';
const isAmericaSays = props.gameSession.game_type.slug === 'america-says';

const isStealRound = computed(() => gameState.value?.is_steal_round ?? false);
const stealPointsPercentage = computed(() => gameState.value?.steal_points_percentage ?? 50);

const allAnswersRevealed = computed(() => {
    if (!currentQuestion.value) return false;
    return currentQuestion.value.answers.every(a => a.revealed);
});

const getControllingTeamName = computed(() => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    const team = teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
    return team?.name ?? null;
});

const fetchState = async () => {
    try {
        const response = await axios.get(route('host.state', props.gameSession.id));
        teams.value = response.data.teams;
        gameState.value = response.data.gameState;
        currentQuestion.value = response.data.currentQuestion;
        currentCard.value = response.data.currentCard;
        totalCards.value = response.data.totalCards || 0;
        currentQuestionNumber.value = response.data.currentQuestionNumber;
        totalQuestions.value = response.data.totalQuestions;
    } catch (error) {
        console.error('Failed to fetch state:', error);
    }
};

const onTimerExpired = () => {
    if (timerExpiredHandled.value) return;
    if (isAmericaSays && currentQuestion.value && !allAnswersRevealed.value) {
        const isSteal = gameState.value?.is_steal_round;
        if (!isSteal) {
            timerExpiredHandled.value = true;
            showStealModal.value = true;
        }
    }
};

const startTimer = async () => {
    timerExpiredHandled.value = false;
    await axios.post(route('host.timer.start', props.gameSession.id));
    fetchState();
};

const pauseTimer = async () => {
    await axios.post(route('host.timer.pause', props.gameSession.id));
    fetchState();
};

const resetTimer = async () => {
    timerExpiredHandled.value = false;
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

const unrevealAnswer = async (answerId: number) => {
    await axios.post(route('host.unreveal', props.gameSession.id), { answer_id: answerId });
    fetchState();
};

// Clicking an answer reveals it; clicking one that's already revealed undoes it.
const toggleAnswer = (answer: Answer) => {
    if (answer.revealed) unrevealAnswer(answer.id);
    else revealAnswer(answer.id);
};

const showEndConfirm = ref(false);
const endGame = () => {
    showEndConfirm.value = true;
};
const confirmEndGame = async () => {
    showEndConfirm.value = false;
    await axios.post(route('host.end', props.gameSession.id));
    window.location.href = route('games.index');
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
        alert('All questions on this card are complete! Click "Next Card" to continue.');
    }
    if (response.data.game_complete) {
        window.location.href = route('games.index');
    }
    await axios.post(route('host.timer.reset', props.gameSession.id));
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
        await axios.post(route('host.question.correct', props.gameSession.id), { team_id: teamId });
        if (isOodles) {
            await axios.post(route('host.timer.reset', props.gameSession.id));
        }
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
        await axios.post(route('host.question.correct', props.gameSession.id), { team_ids: selectedAllPlayTeams.value });
        selectedAllPlayTeams.value = [];
        if (isOodles) {
            await axios.post(route('host.timer.reset', props.gameSession.id));
        }
    } catch (error: any) {
        console.error('markCorrectMultiple error:', error.response?.data || error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
    fetchState();
};

const toggleAllPlayTeam = (teamId: number) => {
    const index = selectedAllPlayTeams.value.indexOf(teamId);
    if (index === -1) selectedAllPlayTeams.value.push(teamId);
    else selectedAllPlayTeams.value.splice(index, 1);
};

const isAllPlayTeamSelected = (teamId: number): boolean => selectedAllPlayTeams.value.includes(teamId);

const markWrong = async () => {
    await axios.post(route('host.question.wrong', props.gameSession.id));
    fetchState();
};

const getControllingTeam = () => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    return teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
};

const isAllPlay = () => currentQuestion.value?.control_status === 'all_play';
const hasTeamControl = () => currentQuestion.value?.control_status === 'team_control' && getControllingTeam();
const hasMultipleTeamControl = () => {
    const teamIds = currentQuestion.value?.controlling_team_ids || [];
    return currentQuestion.value?.control_status === 'team_control' && teamIds.length > 1;
};
const getControllingTeams = () => {
    const teamIds = currentQuestion.value?.controlling_team_ids || [];
    return teams.value.filter(t => teamIds.includes(t.id));
};

const openControlModal = () => {
    selectedControllingTeams.value = currentQuestion.value?.controlling_team_ids || [];
    showControlModal.value = true;
};
const closeControlModal = () => {
    showControlModal.value = false;
};
const toggleTeamControl = (teamId: number) => {
    const index = selectedControllingTeams.value.indexOf(teamId);
    if (index === -1) selectedControllingTeams.value.push(teamId);
    else selectedControllingTeams.value.splice(index, 1);
};
const isTeamSelected = (teamId: number): boolean => selectedControllingTeams.value.includes(teamId);
const saveControllingTeams = async () => {
    if (selectedControllingTeams.value.length === 0) {
        alert('Please select at least one team');
        return;
    }
    await axios.post(route('host.control', props.gameSession.id), { team_ids: selectedControllingTeams.value });
    closeControlModal();
    fetchState();
};

const updateTeamScore = async (teamId: number, newScore: number) => {
    try {
        await axios.patch(route('host.teams.score.update', { gameSession: props.gameSession.id, team: teamId }), { score: newScore });
        fetchState();
    } catch (error: any) {
        console.error('Failed to update score:', error);
        alert('Error updating score: ' + (error.response?.data?.error || error.message));
    }
};

const startStealRound = async () => {
    try {
        showStealModal.value = false;
        await axios.post(route('host.steal.start', props.gameSession.id));
        fetchState();
    } catch (error: any) {
        console.error('Failed to start steal round:', error);
        alert('Error starting steal round: ' + (error.response?.data?.error || error.message));
    }
};
const skipStealRound = async () => {
    showStealModal.value = false;
    await nextQuestion();
};
const endStealAndNextQuestion = async () => {
    try {
        await axios.post(route('host.steal.end', props.gameSession.id));
        await nextQuestion();
    } catch (error: any) {
        console.error('Failed to end steal round:', error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
};

onMounted(() => {
    fetchState();
    pollInterval = window.setInterval(fetchState, 1000);
});
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head :title="`Host - ${gameSession.game_type.name}`" />

    <StandardLayout sticky-header>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-xl font-bold text-body">
                    Hosting: {{ gameSession.game_type.name }}
                    <span class="ml-2 font-normal text-muted">Code: {{ gameSession.invite_code }}</span>
                </h1>
                <Button variant="danger" size="md" @click="endGame">End Game</Button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <!-- Scoreboard (left) -->
                <div class="lg:col-span-1">
                    <Scoreboard
                        :teams="teams"
                        :active-team-id="gameState?.active_team_id"
                        :controlling-team-ids="currentQuestion?.controlling_team_ids || []"
                        :editable="true"
                        @update-score="updateTeamScore"
                    />

                    <div v-if="isOodles && currentQuestion" class="mt-4">
                        <Button variant="accent" size="md" class="w-full" @click="openControlModal">Set Team Control</Button>
                        <p class="mt-2 text-center text-xs text-muted">Select teams that have control (for All Play ties)</p>
                    </div>
                </div>

                <!-- Main game area -->
                <div class="space-y-6 lg:col-span-3">
                    <!-- Current Card (Oodles) -->
                    <Card v-if="isOodles && currentCard" bg-class="bg-info/15 border border-info/30">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="rounded-lg bg-surface-inset px-4 py-2 text-6xl font-bold text-body">{{ currentCard.letter }}</div>
                                <div>
                                    <h3 class="text-2xl font-bold text-body">Card {{ currentCard.card_number }} of {{ totalCards }}</h3>
                                    <p class="text-muted">{{ currentCard.questions.filter(q => q.status === 'completed').length }} of {{ currentCard.questions.length }} questions completed</p>
                                </div>
                            </div>
                            <Button variant="secondary" size="md" @click="nextCard">Next Card &rarr;</Button>
                        </div>
                    </Card>

                    <!-- Timer -->
                    <Card>
                        <GameTimer
                            v-if="gameState"
                            :timer-started-at="gameState.timer_started_at"
                            :timer-duration="gameState.timer_duration"
                            :is-host="true"
                            @start="startTimer"
                            @pause="pauseTimer"
                            @reset="resetTimer"
                            @expired="onTimerExpired"
                        />
                    </Card>

                    <!-- Oodles: Question list -->
                    <Card v-if="isOodles && currentCard && !currentQuestion" title="Select a Question">
                        <p class="mb-4 text-muted">Click a question to display it to players.</p>
                        <div class="space-y-2">
                            <button
                                v-for="question in currentCard.questions"
                                :key="question.id"
                                :disabled="question.status === 'completed'"
                                class="flex w-full items-center justify-between rounded-lg border p-4 text-left transition-all"
                                :class="question.status === 'completed'
                                    ? 'cursor-not-allowed border-success/40 bg-success/10 text-success'
                                    : 'cursor-pointer border-border bg-surface-inset text-body hover:border-primary hover:bg-surface-overlay'"
                                @click="selectQuestion(question.id)"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="rounded bg-surface-overlay px-2 py-1 font-mono text-lg text-body">{{ question.display_order }}</span>
                                    <span class="font-medium">{{ question.question_text }}</span>
                                </div>
                                <span v-if="question.status === 'completed'" class="font-semibold text-success">✓ Done</span>
                            </button>
                        </div>
                    </Card>

                    <!-- Question & Answers -->
                    <Card>
                        <div v-if="currentQuestion">
                            <!-- Question header -->
                            <div class="mb-6 text-center">
                                <div v-if="isAmericaSays && currentQuestionNumber && totalQuestions" class="mb-2">
                                    <span class="rounded-full bg-surface-inset px-3 py-1 text-sm font-medium text-muted">Question {{ currentQuestionNumber }} of {{ totalQuestions }}</span>
                                </div>
                                <h3 class="text-2xl font-bold text-body">{{ currentQuestion.question_text }}</h3>
                            </div>

                            <!-- Oodles: control & actions -->
                            <div v-if="isOodles" class="mb-6">
                                <!-- Multiple teams in control -->
                                <div v-if="hasMultipleTeamControl()" class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-4">
                                    <div class="mb-3 text-center">
                                        <span class="text-xl font-bold text-warning">Multiple Teams Have Control!</span>
                                        <div class="mt-2 flex items-center justify-center gap-2">
                                            <template v-for="(team, index) in getControllingTeams()" :key="team.id">
                                                <span v-if="index > 0" class="text-muted">&amp;</span>
                                                <span class="rounded-full px-3 py-1 font-bold text-white" :style="{ backgroundColor: team.color }">{{ team.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <p class="mb-3 text-center text-muted">Select the team(s) that answered correctly:</p>
                                    <div class="mb-4 grid grid-cols-2 gap-3">
                                        <button
                                            v-for="team in getControllingTeams()"
                                            :key="team.id"
                                            class="flex items-center justify-center gap-2 rounded-lg border-4 p-4 text-lg font-bold transition-all"
                                            :class="isAllPlayTeamSelected(team.id) ? 'text-white' : 'bg-surface-inset text-body'"
                                            :style="{ backgroundColor: isAllPlayTeamSelected(team.id) ? team.color : undefined, borderColor: team.color }"
                                            @click="toggleAllPlayTeam(team.id)"
                                        >
                                            <span v-if="isAllPlayTeamSelected(team.id)" class="text-xl">✓</span>
                                            <span>{{ team.name }}</span>
                                        </button>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <Button variant="success" size="md" :disabled="selectedAllPlayTeams.length === 0" @click="markCorrectMultiple">Award Points</Button>
                                        <Button variant="danger" size="md" @click="markWrong">All Wrong (All Play)</Button>
                                    </div>
                                </div>

                                <!-- Single team in control -->
                                <div v-else-if="hasTeamControl()" class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-6 w-6 rounded-full" :style="{ backgroundColor: getControllingTeam()?.color }"></div>
                                        <span class="text-xl font-bold text-body">{{ getControllingTeam()?.name }}</span>
                                        <span class="text-muted">has control</span>
                                    </div>
                                    <div class="mt-4 flex gap-4">
                                        <Button variant="success" size="lg" class="flex-1" @click="markCorrect(getControllingTeam()!.id)">✓ Correct</Button>
                                        <Button variant="danger" size="lg" class="flex-1" @click="markWrong">✗ Wrong (All Play)</Button>
                                    </div>
                                </div>

                                <!-- All Play -->
                                <div v-else-if="isAllPlay()" class="mb-4 rounded-lg border border-info/40 bg-info/10 p-4">
                                    <div class="mb-4 text-center">
                                        <span class="text-2xl font-bold text-info">ALL PLAY!</span>
                                        <p class="text-muted">Select all teams that answered correctly (points split):</p>
                                    </div>
                                    <div class="mb-4 grid grid-cols-2 gap-3">
                                        <button
                                            v-for="team in teams"
                                            :key="team.id"
                                            class="flex items-center justify-center gap-2 rounded-lg border-4 p-4 text-lg font-bold transition-all"
                                            :class="isAllPlayTeamSelected(team.id) ? 'text-white' : 'bg-surface-inset text-body'"
                                            :style="{ backgroundColor: isAllPlayTeamSelected(team.id) ? team.color : undefined, borderColor: team.color }"
                                            @click="toggleAllPlayTeam(team.id)"
                                        >
                                            <span v-if="isAllPlayTeamSelected(team.id)" class="text-xl">✓</span>
                                            <span>{{ team.name }}</span>
                                        </button>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <Button variant="success" size="md" :disabled="selectedAllPlayTeams.length === 0" @click="markCorrectMultiple">Award Points ({{ selectedAllPlayTeams.length }} team{{ selectedAllPlayTeams.length !== 1 ? 's' : '' }})</Button>
                                        <Button variant="muted" size="md" @click="nextQuestion">Skip (no one got it)</Button>
                                    </div>
                                </div>

                                <!-- No controlling team -->
                                <div v-else class="mb-4 rounded-lg bg-surface-inset p-4 text-center text-muted">
                                    <p>No team in control. Select a team to award points:</p>
                                    <div class="mt-3 flex justify-center gap-2">
                                        <button v-for="team in teams" :key="team.id" class="rounded-lg px-4 py-2 font-medium text-white" :style="{ backgroundColor: team.color }" @click="markCorrect(team.id)">{{ team.name }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- America Says: steal round -->
                            <div v-if="isAmericaSays && isStealRound" class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xl font-bold text-warning">STEAL ROUND!</span>
                                        <p class="text-muted">{{ getControllingTeamName }} can steal for {{ stealPointsPercentage }}% points</p>
                                    </div>
                                    <Button variant="muted" size="md" @click="endStealAndNextQuestion">Next Question</Button>
                                </div>
                            </div>

                            <!-- America Says: all revealed -->
                            <div v-else-if="isAmericaSays && allAnswersRevealed" class="mb-4 rounded-lg border border-success/40 bg-success/10 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xl font-bold text-success">All Answers Revealed!</span>
                                        <p class="text-muted">Great job! Ready for the next question?</p>
                                    </div>
                                    <Button variant="success" size="md" @click="nextQuestion">Next Question</Button>
                                </div>
                            </div>

                            <!-- America Says: control round -->
                            <div v-else-if="isAmericaSays && currentQuestion.controlling_team_id" class="mb-4 rounded-lg border border-info/40 bg-info/10 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xl font-bold text-info">{{ getControllingTeamName }}'s Turn</span>
                                        <p class="text-muted">Full points for correct answers</p>
                                    </div>
                                    <Button variant="accent" size="md" @click="showStealModal = true">Start Steal Round</Button>
                                </div>
                            </div>

                            <!-- Answers (America Says / Family Feud) -->
                            <div v-if="!isOodles" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <button
                                    v-for="answer in currentQuestion.answers"
                                    :key="answer.id"
                                    :title="answer.revealed ? 'Click to undo' : 'Click to reveal'"
                                    class="cursor-pointer rounded-lg border p-4 text-left transition-all"
                                    :class="answer.revealed
                                        ? 'border-success bg-success/10 text-body shadow-[0_0_18px_-2px_rgb(var(--color-success)_/_0.55)]'
                                        : (isStealRound
                                            ? 'border-warning/40 bg-warning/10 text-body'
                                            : 'border-border bg-surface-inset text-body')"
                                    @click="toggleAnswer(answer)"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold">{{ answer.answer_text }}</span>
                                        <span class="text-lg font-bold" :class="isStealRound && !answer.revealed ? 'text-warning' : 'text-muted'">
                                            <template v-if="isStealRound && !answer.revealed">
                                                {{ Math.floor(answer.points * stealPointsPercentage / 100) }} pts
                                                <span class="ml-1 text-sm text-subtle line-through">{{ answer.points }}</span>
                                            </template>
                                            <template v-else>{{ answer.points }} pts</template>
                                        </span>
                                    </div>
                                </button>
                            </div>

                            <!-- Oodles: answer reference -->
                            <div v-if="isOodles && currentQuestion.answers?.length > 0" class="mt-4 rounded-lg bg-surface-inset p-4">
                                <p class="mb-1 text-sm text-muted">Answer:</p>
                                <p class="text-xl font-bold text-body">{{ currentQuestion.answers[0]?.answer_text }}</p>
                            </div>
                        </div>

                        <div v-else-if="!isOodles" class="py-12 text-center text-muted">
                            <p class="text-xl">No active question</p>
                            <p class="mt-2">Select a question to display it to players</p>
                        </div>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Control Modal -->
        <Modal :show="showControlModal" max-width="md" @close="closeControlModal">
            <div class="p-6">
                <h3 class="mb-2 text-xl font-bold text-body">Set Controlling Teams</h3>
                <p class="mb-4 text-muted">Select one or more teams that have control of the next question. Use this for All Play ties.</p>
                <div class="mb-6 space-y-2">
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        class="flex cursor-pointer items-center justify-between rounded-lg border-2 p-3 transition-all"
                        :class="isTeamSelected(team.id) ? 'border-warning bg-warning/10' : 'border-border hover:border-border-strong'"
                        @click="toggleTeamControl(team.id)"
                    >
                        <div class="flex items-center gap-3">
                            <div class="h-5 w-5 rounded-full" :style="{ backgroundColor: team.color }"></div>
                            <span class="font-semibold text-body">{{ team.name }}</span>
                        </div>
                        <div v-if="isTeamSelected(team.id)" class="grid h-6 w-6 place-items-center rounded-full bg-warning text-black">✓</div>
                        <div v-else class="h-6 w-6 rounded-full border-2 border-border"></div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <Button variant="ghost" size="md" @click="closeControlModal">Cancel</Button>
                    <Button variant="accent" size="md" :disabled="selectedControllingTeams.length === 0" @click="saveControllingTeams">Save Control</Button>
                </div>
            </div>
        </Modal>

        <!-- Steal Round Modal -->
        <Modal :show="showStealModal" max-width="md" :closeable="false">
            <div class="p-6">
                <h3 class="mb-2 text-xl font-bold text-warning">Start Steal Round?</h3>
                <p class="mb-4 text-muted">Time's up! The other team can now try to steal remaining answers for {{ stealPointsPercentage }}% of the points.</p>
                <div class="flex justify-end gap-3">
                    <Button variant="ghost" size="md" @click="skipStealRound">Skip to Next Question</Button>
                    <Button variant="accent" size="md" @click="startStealRound">Start Steal Round</Button>
                </div>
            </div>
        </Modal>

        <!-- End game confirm -->
        <Confirm
            :show="showEndConfirm"
            title="End game?"
            message="This ends the game for everyone and returns you to your games."
            confirm-text="End Game"
            variant="danger"
            @confirm="confirmEndGame"
            @close="showEndConfirm = false"
        />
    </StandardLayout>
</template>
