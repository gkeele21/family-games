<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import Scoreboard from '@/Components/Scoreboard.vue';
import GameTimer from '@/Components/GameTimer.vue';
import Card from '@/Components/Base/Card.vue';
import Button from '@/Components/Base/Button.vue';
import Modal from '@/Components/Base/Modal.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';
import BlankText from '@/Components/BlankText.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
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
    // Feud only: what this reveal adds to the pool (0 for a steal reveal).
    pool_points?: number;
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
    round_number: number | null;
    segment: string | null;
    points_available: number;
    control_status: string;
    controlling_team_id: number | null;
    controlling_team_ids: number[];
    bonus_points: number;
    bonus_awarded_team_id: number | null;
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
    bonus_question: {
        question_text: string;
        answer_text: string | null;
    } | null;
}

interface FinalQuestion {
    id: number;
    question_text: string;
    answers_needed: number;
    status: string;
    total_answers: number;
    revealed_count: number;
    is_current: boolean;
}

// Family Feud Fast Money board (host view — carries each question's survey answers
// so the host can reveal what the player said).
interface FmSurveyAnswer { id: number; text: string; points: number }
// Host cell: captured = recorded (hidden), shown = text on TV, scored = points on TV.
interface FmCell { captured: boolean; shown: boolean; scored: boolean; answer_id?: number | null; text?: string | null; points?: number }
interface FmRow { id: number; question: string; p1: FmCell; p2: FmCell; answers: FmSurveyAnswer[] }
interface FastMoney {
    rows: FmRow[];
    target: number;
    active_player: number;
    show_previous: boolean;
    p1_total: number;
    p2_total: number;
    combined_total: number;
    result: 'win' | 'lose' | null;
    duplicate_buzz: number;
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
const isLastQuestion = ref(false);
const finalQueued = ref(false);
// Family Feud: a team has reached the target (300) so regular play is decided,
// and whether Fast Money is set up to follow. Drive the recap advance button.
const feudTargetReached = ref(false);
const feudFastMoneyReady = ref(false);
const finalQuestions = ref<FinalQuestion[]>([]);
const fastMoney = ref<FastMoney | null>(null);
const selectedControllingTeams = ref<number[]>([]);
const showControlModal = ref(false);
const selectedAllPlayTeams = ref<number[]>([]);
const showScoreModal = ref(false);
const scoreEdits = ref<Record<number, string>>({});
const bonusDismissedQuestionId = ref<number | null>(null);
const timerExpiredHandled = ref(false);
let pollInterval: number | null = null;

const isOodles = props.gameSession.game_type.slug === 'oodles';
const isAmericaSays = props.gameSession.game_type.slug === 'america-says';
const isFamilyFeud = props.gameSession.game_type.slug === 'family-feud';

// Label for the current question's round/segment (e.g. "Round 2", "Final Round").
const roundLabel = computed(() => {
    const q = currentQuestion.value;
    if (!q) return '';
    if (q.segment === 'final') return 'Final Round';
    if (q.segment === 'fast_money') return 'Fast Money';
    return q.round_number ? `Round ${q.round_number}` : '';
});

// America Says scores a flat per-round value for every answer; other games use
// each answer's own points.
const answerPoints = (answer: Answer): number =>
    isAmericaSays && (currentQuestion.value?.points_available ?? 0) > 0
        ? (currentQuestion.value?.points_available ?? 0)
        : answer.points;

const allAnswersRevealed = computed(() => {
    if (!currentQuestion.value) return false;
    return currentQuestion.value.answers.every(a => a.revealed);
});

// Order the host answer grid to match the display board. Family Feud's TV board
// runs the top answers DOWN the left column then the rest down the right (1-4
// left, 5-8 right), so interleave the ranked answers here — the 2-column grid
// fills row-major, so [a0, a4, a1, a5, …] renders as two stacked columns. Other
// games keep their natural order.
const orderedAnswers = computed<Answer[]>(() => {
    const list = currentQuestion.value?.answers ?? [];
    if (!isFamilyFeud) return list;
    const sorted = [...list].sort((a, b) => a.display_order - b.display_order);
    const half = Math.ceil(sorted.length / 2);
    const out: Answer[] = [];
    for (let i = 0; i < half; i++) {
        out.push(sorted[i]);
        if (sorted[half + i]) out.push(sorted[half + i]);
    }
    return out;
});

const getControllingTeamName = computed(() => {
    if (!currentQuestion.value?.controlling_team_id) return null;
    const team = teams.value.find(t => t.id === currentQuestion.value?.controlling_team_id);
    return team?.name ?? null;
});

// Which team(s) to highlight as in-control on the scoreboard.
const boardControllingTeamIds = computed<number[]>(() => {
    const q = currentQuestion.value;
    if (!q) return [];
    if (q.controlling_team_ids?.length) return q.controlling_team_ids;
    return q.controlling_team_id ? [q.controlling_team_id] : [];
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
        isLastQuestion.value = !!response.data.isLastQuestion;
        finalQueued.value = !!response.data.finalQueued;
        feudTargetReached.value = !!response.data.feudTargetReached;
        feudFastMoneyReady.value = !!response.data.feudFastMoneyReady;
        finalQuestions.value = response.data.finalQuestions || [];
        fastMoney.value = response.data.fastMoney ?? null;
    } catch (error) {
        console.error('Failed to fetch state:', error);
    }
};

const onTimerExpired = () => {
    // Final round: the moment the budget hits 0 the team is out of time, so drop
    // into review automatically (once). Regular rounds: the primary's time is up,
    // so auto-hand the board to the other team for the steal (once) — unless the
    // primary already cleared it (server will have moved us to recap).
    // Fire once per timer run (reset by startTimer/resetTimer) so a repeated
    // @expired can't double-hand the steal and flip control back.
    if (timerExpiredHandled.value) return;
    timerExpiredHandled.value = true;
    if (isFinal.value && phase.value === 'final_play' && !finalTimeoutFired.value) {
        finalTimeoutFired.value = true;
        finalTimeout();
    } else if (isAmericaSays && !isFinal.value && !isTiebreaker.value
        && phase.value === 'question' && !allAnswersRevealed.value) {
        stealStart();
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

// Reset the whole round: un-reveal every answer, reverse this round's points
// (and sweep bonuses) across all of its questions, replay from the round's first
// question, and put the timer back to full.
const showResetRoundConfirm = ref(false);
const resetRound = async () => {
    showResetRoundConfirm.value = false;
    timerExpiredHandled.value = false;
    finalTimeoutFired.value = false;
    bonusDismissedQuestionId.value = null;
    // Reset the round on the server. It sets the clock itself — the full final
    // budget (e.g. 60s) for the final round, or leaving the regular per-question
    // clock alone — so only nudge the regular timer back to full here. Resetting
    // it in the final would knock the minute back down to the 30s regular timer.
    const calls = [axios.post(route('host.round.reset', props.gameSession.id))];
    if (!isFinal.value) {
        calls.push(axios.post(route('host.timer.reset', props.gameSession.id)));
    }
    await Promise.all(calls);
    fetchState();
};

// When on, revealing an answer just shows it on the board — no team, no points.
// Used to reveal answers that neither team ever said once a round is over. It's a
// per-question mode: moving to another question drops back to normal scoring.
const revealWithoutPoints = ref(false);

// Steal tracking (America Says): the first team given control of a question is the
// controller (full timer, keeps guessing on a miss). Once control is handed to a
// SECOND team, that's the one-shot steal — no clock, one guess. So a wrong buzz
// during a steal is terminal, and we auto-flip into reveal-only (no points) so the
// host can reveal the rest without the host having to remember the toggle.
const firstControllerId = ref<number | null>(null);
const stealActive = ref(false);
watch(() => currentQuestion.value?.id, () => {
    revealWithoutPoints.value = false;
    firstControllerId.value = null;
    stealActive.value = false;
    advancingToScores.value = false;
});

const revealAnswer = async (answerId: number) => {
    const activeTeamId = gameState.value?.active_team_id;
    await axios.post(route('host.reveal', props.gameSession.id), {
        answer_id: answerId,
        team_id: revealWithoutPoints.value ? null : activeTeamId,
        award_points: !revealWithoutPoints.value,
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
    window.location.href = route('dashboard');
};

const confirmBackToSetup = async () => {
    await axios.post(route('games.back-to-lobby', props.gameSession.id));
    window.location.href = route('host.lobby', props.gameSession.id);
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
        window.location.href = route('dashboard');
    }
    // Reset the per-question clock for the next regular question. Skip it when
    // crossing into the final round or Fast Money — those already set their own
    // budget, and resetting would knock it back to the regular timer.
    if (!response.data.entering_final && !response.data.entering_fast_money) {
        await axios.post(route('host.timer.reset', props.gameSession.id));
    }
    fetchState();
};

const nextCard = async () => {
    const response = await axios.post(route('host.card.next', props.gameSession.id));
    if (response.data.game_complete) {
        window.location.href = route('dashboard');
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

// Top-header "Next Question": advance to the next question.
const advanceQuestion = async () => {
    await nextQuestion();
};

// Clicking a team on the scoreboard hands control (the turn) to that team.
const selectControllingTeam = async (teamId: number) => {
    // Handing control to a team means you're about to score them — leave
    // reveal-only mode so their reveals count again.
    revealWithoutPoints.value = false;
    // Track the steal: first team = controller; a hand-off to a different team is
    // the one-shot steal. (Handing back to the original controller isn't a steal.)
    if (firstControllerId.value === null) {
        firstControllerId.value = teamId;
        stealActive.value = false;
    } else {
        stealActive.value = teamId !== firstControllerId.value;
    }
    try {
        await axios.post(route('host.control.team', props.gameSession.id), { team_id: teamId });
        // If we're mid-steal (or revealing leftovers), handing control on the
        // scoreboard is a correction: return to that team's turn so the display and
        // step checklist follow — otherwise we'd be stuck showing "STEAL"/reveal for
        // the wrong team with no clock. America Says only — Feud resolves the steal
        // via explicit buttons, and its "show question" goes back to the face-off.
        if (isAmericaSays && ['steal', 'reveal'].includes(phase.value)) {
            await axios.post(route('host.question.show', props.gameSession.id));
        }
        fetchState();
    } catch (error: any) {
        console.error('Failed to set controlling team:', error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
};

// ---- America Says guided flow -------------------------------------------------
// Phase drives the single "next step" button: intro → question → (play) → recap.
const phase = computed<string>(() => gameState.value?.state_data?.phase ?? 'question');
const timerRunning = computed(() => !!gameState.value?.timer_started_at);
// The clock's full duration (config). When it's paused mid-question the banked
// duration is less than this, so the start button reads "Resume Timer" instead.
const fullTimerDuration = computed(() => Number(props.config?.control_timer_seconds ?? 30));
const timerPaused = computed(() => {
    const d = gameState.value?.timer_duration ?? 0;
    return !timerRunning.value && d > 0 && d < fullTimerDuration.value;
});
const startTimerLabel = computed(() => (timerPaused.value ? 'Resume Timer' : 'Reveal Board - Start Timer'));


// The four phases of a round, shown as a checklist so the host sees the whole
// progression (not just the next action). Index of the current phase:
// Global "Show Scores" escape: available on a live regular-round board whenever the
// clock isn't running, so the host can jump to the scoreboard at any point (e.g. to
// fix things) without waiting on the auto-advance.
const canShowScores = computed(() =>
    isAmericaSays && !isFinal.value && !isTiebreaker.value && !!currentQuestion.value
    && ['question', 'steal', 'reveal'].includes(phase.value) && !timerRunning.value,
);

// End Game only needs to appear near the actual finish, never during regular play.
// America Says: once the final answer board's clock is up (if a final is played),
// otherwise once the last regular round's scoreboard is showing. Family Feud ends
// via its own controls — the Fast Money card's End Game (after Fast Money is over)
// or the recap's "Finish Game" when Fast Money is off — so the header button stays
// hidden. Other games keep it always available.
const canEndGame = computed(() => {
    if (isFamilyFeud) return false;
    if (!isAmericaSays) return true;
    if (['final_play', 'final_cleared', 'final_review', 'final_result'].includes(phase.value)) return true;
    return isLastQuestion.value && !finalQueued.value && phase.value === 'recap';
});

const roundStepIndex = computed(() => {
    if (phase.value === 'intro') return 0;
    if (phase.value === 'recap') return 4;
    if (phase.value === 'steal' || phase.value === 'reveal') return 3;
    return timerRunning.value ? 2 : 1; // question phase: shown (idle) vs playing (running)
});
const roundSteps = computed(() => {
    const rn = gameState.value?.round_number ?? 1;
    const primaryName = primaryTeam.value?.name ?? 'the primary team';
    const stealName = stealTeam.value?.name ?? 'the other team';
    return [
        { title: 'Round Intro', hint: `Round ${rn} is on the board. Show the question when you’re ready to read it.` },
        { title: 'Question Shown', hint: 'Just the question is on the board — answers still hidden, no clock. Read it aloud, then start the timer.' },
        { title: 'Primary Team Playing', hint: `${primaryName} is up: reveal answers as they’re guessed. When the timer runs out it hands to the steal.` },
        {
            title: 'Steal',
            hint: phase.value === 'reveal'
                ? 'Steal missed — reveal the leftovers (no points). The board ends when every answer is up.'
                : `${stealName} is stealing — reveal each correct steal (they score). A wrong answer hands to Reveal only for the leftovers.`,
        },
        { title: 'Scores', hint: 'The scoreboard is on the board. Move on to the next question.' },
    ];
});

const showQuestion = async () => {
    await axios.post(route('host.question.show', props.gameSession.id));
    fetchState();
};

// A round step is "done" only when it's genuinely finished — the board steps
// (Primary Playing / Steal) stay UNchecked until every answer is on the board, so
// jumping to Scores with an answer still hidden reads as incomplete, not a
// misleading green check.
const stepComplete = (i: number): boolean => {
    if (i >= roundStepIndex.value) return false;
    if (i === 2 || i === 3) return allAnswersRevealed.value;
    return true;
};

// The steps double as navigation: click a step to jump to it (reveals are kept).
// Steal hands control to the other team; Scores drops to the recap.
const goToStep = async (i: number) => {
    if (i === roundStepIndex.value) return;
    if (i === 3) { // Steal → hand control to the other team
        await stealStart();
        return;
    }
    const routeName = i === 0
        ? 'host.round.intro'          // Round Intro → Get Ready
        : i === 4
            ? 'host.round.end'        // Scores → recap
            : 'host.question.show';   // Question Shown / Primary Playing → board
    await axios.post(route(routeName, props.gameSession.id));
    fetchState();
};

const endRound = async () => {
    await axios.post(route('host.round.end', props.gameSession.id));
    fetchState();
};

// Sound the wrong-answer buzzer on the display (no board change — just the cue).
// If a steal is underway, that wrong guess ends the steal (one shot, no clock), so
// auto-flip into reveal-only mode — the host can now reveal the remaining answers
// without them scoring, no need to remember the toggle by the scoreboard.
const buzzWrong = async () => {
    if (stealActive.value) {
        revealWithoutPoints.value = true;
        stealActive.value = false;
    }
    await axios.post(route('host.buzz.wrong', props.gameSession.id));
    fetchState();
};

// ---- America Says smart steal flow --------------------------------------------
// During the primary team's turn the board only ends on timer-out or a full clear;
// then control auto-hands to the other team for an untimed steal (they keep going
// while correct). The first wrong steal ends the board and reveals the misses.
// Whoever holds the turn is "active": that's the primary during their own turn,
// and the stealing team once we've handed over. Derive both sides from the phase
// (2-team game) so labels stay right in either phase.
const activeTurnTeam = computed<Team | null>(() =>
    teams.value.find(t => t.id === gameState.value?.active_team_id) ?? null
);
const idleTurnTeam = computed<Team | null>(() =>
    teams.value.find(t => t.id !== gameState.value?.active_team_id) ?? null
);
// The steal team stays "active" through the reveal-leftovers state (phase 'reveal').
const inStealOrReveal = computed(() => ['steal', 'reveal'].includes(phase.value));
const primaryTeam = computed<Team | null>(() =>
    inStealOrReveal.value ? idleTurnTeam.value : activeTurnTeam.value
);
const stealTeam = computed<Team | null>(() =>
    inStealOrReveal.value ? activeTurnTeam.value : idleTurnTeam.value
);

// Hand the board to the other team for the steal (auto on timer-out, or manual).
const stealStart = async () => {
    await axios.post(route('host.steal.start', props.gameSession.id));
    fetchState();
};
// Ending the primary's clock early is confirmed so an accidental click doesn't cut
// their turn short — then it hands to the steal exactly like the timer running out.
const showTimesUpConfirm = ref(false);
const confirmTimesUp = () => {
    showTimesUpConfirm.value = false;
    stealStart();
};
// The "Wrong Answer" buzzer on the board: during a steal, a wrong guess ends the
// steal but NOT the board — it hands control to Reveal only so the host reveals the
// leftovers (no points). The board then ends when every answer is up. Outside a
// steal it just sounds the cue.
const onWrongAnswer = () => {
    if (phase.value === 'steal') {
        revealWithoutPoints.value = true;
    }
    buzzWrong();
};

// The scoreboard's "Reveal only" tile. America Says shows it as a toggle whenever a
// (non-final) question is up. Family Feud shows it as a read-only indicator on the
// main board's reveal phases — the backend zero-scores reveals during 'reveal', so
// the tile just LIGHTS UP while the host puts up the leftover answers.
const showRevealOnly = computed(() => {
    if (isAmericaSays) return !!currentQuestion.value && !isFinal.value && !isTiebreaker.value;
    if (isFamilyFeud) {
        return !!currentQuestion.value
            && (currentQuestion.value.segment ?? 'main') !== 'fast_money'
            && ['question', 'steal', 'reveal'].includes(phase.value);
    }
    return false;
});
const revealOnlyActive = computed(() =>
    revealWithoutPoints.value || (isFamilyFeud && phase.value === 'reveal')
);

// The scoreboard's "Reveal only" control. Toggling it also syncs the board phase
// during a steal so the TV's STEAL banner tracks it: on → reveal (banner off),
// off → steal (banner back).
const toggleRevealOnly = async () => {
    const enabling = !revealWithoutPoints.value;
    revealWithoutPoints.value = enabling;
    if (isAmericaSays && !isFinal.value && !isTiebreaker.value && ['steal', 'reveal'].includes(phase.value)) {
        await axios.post(route('host.steal.reveal', props.gameSession.id), { reveal_only: enabling });
        fetchState();
    }
};

// Once every answer is revealed — a primary sweep, a stealer clearing the board, or
// the leftovers shown in Reveal only — hold 2s so the last reveal registers on the
// TV, then jump to the scoreboard. Pausing the clock first stops a swept board from
// ticking on (and from tripping the timer-out steal).
const advancingToScores = ref(false);
watch(allAnswersRevealed, (all) => {
    if (!all || advancingToScores.value) return;
    if (!isAmericaSays || isFinal.value || isTiebreaker.value) return;
    if (!['question', 'steal', 'reveal'].includes(phase.value)) return;
    advancingToScores.value = true;
    if (timerRunning.value) pauseTimer();
    window.setTimeout(async () => {
        await endRound();
        advancingToScores.value = false;
    }, 2000);
});

// ---- America Says final round -------------------------------------------------
// A single time budget covers all final questions; the leading team plays for a
// pass/fail win. The clock auto-pauses between questions (server-side) so the
// host reads the next one, then resumes.
const isFinal = computed(() => currentQuestion.value?.segment === 'final');

// ---- America Says tie-off -----------------------------------------------------
// After the last regular round, if teams tie for the lead a one-answer tiebreaker
// decides who plays the final. The board looks like Final question 1; the host
// reveals the answer if needed, then declares which tied team won.
const isTiebreaker = computed(() => currentQuestion.value?.segment === 'tiebreaker');
// The tie-off answer is clickable (revealable) once the board is up — i.e. from
// "Team's Playing" through "Declare Winner". Before that (intro / question plaque)
// it's host reference only.
const tiebreakerAnswersActive = computed(() =>
    isTiebreaker.value && (phase.value === 'tiebreaker_play' || phase.value === 'tiebreaker_declare')
);
const tiebreakerTeamIds = computed<number[]>(() => gameState.value?.state_data?.tiebreaker_team_ids ?? []);
const tiebreakerTeams = computed<Team[]>(() =>
    tiebreakerTeamIds.value
        .map(id => teams.value.find(t => t.id === id))
        .filter((t): t is Team => !!t)
);
// Two or more teams tied for the lead — the recap heads into a tie-off, not the
// final, so the advance button reads "Start Tiebreaker".
const isLeadTie = computed(() => {
    if (teams.value.length < 2) return false;
    const max = Math.max(...teams.value.map(t => t.total_score));
    return teams.value.filter(t => t.total_score === max).length > 1;
});

const tiebreakerShow = async () => {
    await axios.post(route('host.tiebreaker.show', props.gameSession.id));
    fetchState();
};
// Reveal the (blank) answer board — like a regular round's Start Timer. This does
// NOT reveal the answer text; it just brings up the board. Backend flips the phase
// to tiebreaker_play (the display swaps the question plaque for the board).
const tiebreakerRevealBoard = async () => {
    await axios.post(route('host.tiebreaker.reveal-board', props.gameSession.id));
    fetchState();
};
const tiebreakerToDeclare = async () => {
    await axios.post(route('host.tiebreaker.to-declare', props.gameSession.id));
    fetchState();
};
// Start the final round with the tie-off winner (from the winner slide).
const tiebreakerToFinal = async () => {
    await axios.post(route('host.tiebreaker.to-final', props.gameSession.id));
    fetchState();
};
const tiebreakerSwap = async () => {
    try {
        await axios.post(route('host.tiebreaker.swap', props.gameSession.id));
        fetchState();
    } catch (error: any) {
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
};

// The tie-off mirrors a regular round's 4-step checklist, but the last step is
// "Declare Winner" instead of "Scores".
const tiebreakerStepIndex = computed(() => {
    switch (phase.value) {
        case 'tiebreaker_intro': return 0;
        case 'tiebreaker_question': return 1;
        case 'tiebreaker_play': return 2;
        case 'tiebreaker_declare': return 3;
        case 'tiebreaker_result': return 4;
        default: return 0;
    }
});
const tiebreakerSteps = computed(() => [
    { title: 'Tie-Off', hint: 'Teams tied for the lead. Whoever answers first gets to guess — right, they win; wrong, the other team wins. Show the question when ready.' },
    { title: 'Question Shown', hint: 'Just the question is on the board. Read it aloud, then reveal the board (the blank answer) when they answer.' },
    { title: 'Team\'s Playing', hint: 'The blank answer board is up. See who got it (or was closest), then move on to declare the winner.' },
    { title: 'Declare Winner', hint: 'Declare the team that won the tie-off.' },
    { title: 'Winner', hint: 'The tie-off winner is crowned on the board. Start the final round when the room is ready.' },
]);
const tiebreakerResolve = async (teamId: number) => {
    await axios.post(route('host.tiebreaker.resolve', props.gameSession.id), { team_id: teamId });
    fetchState();
};
const finalResult = computed<string | null>(() => gameState.value?.state_data?.final_result ?? null);
const finalSkipUsed = computed(() => !!gameState.value?.state_data?.final_skip_used);
const finalTeam = computed<Team | null>(() => {
    const id = gameState.value?.state_data?.final_team_id;
    return id ? teams.value.find(t => t.id === id) ?? null : null;
});
// Guards the one-shot auto-timeout so it fires once when the final clock expires.
const finalTimeoutFired = ref(false);

// Show the current final question's plaque (answers hidden) so the host reads it.
const finalShowQuestion = async () => {
    await axios.post(route('host.final.show', props.gameSession.id));
    fetchState();
};
// Reveal the answer board and (re)start the clock for the shown question.
const finalStart = async () => {
    timerExpiredHandled.value = false;
    finalTimeoutFired.value = false;
    await axios.post(route('host.final.start', props.gameSession.id));
    fetchState();
};
// After a question is cleared, move to the next one (plaque only) when ready.
const finalNext = async () => {
    await axios.post(route('host.final.next', props.gameSession.id));
    fetchState();
};
const finalSkip = async () => {
    try {
        await axios.post(route('host.final.skip', props.gameSession.id));
        fetchState();
    } catch (error: any) {
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
};
const finalTimeout = async () => {
    await axios.post(route('host.final.timeout', props.gameSession.id));
    fetchState();
};
// The host can jump between the final questions whenever the clock isn't running
// — to reveal misses after time is up, or to look back at an earlier (cleared or
// skipped) question, whose revealed answers are still on record.
const canNavigateFinal = computed(() =>
    ['final_question', 'final_cleared', 'final_review'].includes(phase.value)
);
const finalSelect = async (sessionQuestionId: number) => {
    if (!canNavigateFinal.value) return;
    await axios.post(route('host.final.select', props.gameSession.id), { session_question_id: sessionQuestionId });
    fetchState();
};

// The final round is shown as a per-question step checklist, mirroring a regular
// round: one question at a time flows Get Ready → Question Shown → Team's Playing →
// Board Complete, with a "Question X of Y" header. The out-of-time / result beats
// swap the checklist for a review list / banner (see the template).
const finalSteps = [
    { title: 'Get Ready' },
    { title: 'Question Shown' },
    { title: 'Team\'s Playing' },
    { title: 'Board Complete' },
];
const finalStepIndex = computed(() => {
    switch (phase.value) {
        case 'final_intro':
        case 'final_ready': return 0;
        case 'final_question': return 1;
        case 'final_play': return 2;
        case 'final_cleared': return 3;
        default: return 0;
    }
});
// Out-of-time review / pass-fail result replace the step checklist.
const finalInReview = computed(() => phase.value === 'final_review');
const finalInResult = computed(() => phase.value === 'final_result');
const finalShowSteps = computed(() => !finalInReview.value && !finalInResult.value);
// The question currently on the board, and its position in the set (X of Y).
const currentFinalQuestion = computed<FinalQuestion | null>(() =>
    finalQuestions.value.find(q => q.is_current) ?? null
);
const finalQuestionNumber = computed(() => {
    const i = finalQuestions.value.findIndex(q => q.is_current);
    return i >= 0 ? i + 1 : 1;
});
const finalTotal = computed(() => finalQuestions.value.length);
// The skipped question (if any). A skip leaves the question pending and records it
// by id in state, so it's tracked separately from status.
const finalSkippedId = computed<number | null>(() => gameState.value?.state_data?.final_skipped_question_id ?? null);
// At-a-glance status of all four final questions for the key: done / current /
// skipped / upcoming (completed wins, then the one you're on, then a recorded skip).
const finalStatusList = computed(() =>
    finalQuestions.value.map((fq, i) => {
        let status: 'done' | 'current' | 'skipped' | 'upcoming' = 'upcoming';
        if (fq.status === 'completed') status = 'done';
        else if (fq.is_current) status = 'current';
        else if (finalSkippedId.value === fq.id) status = 'skipped';
        return { n: i + 1, id: fq.id, status };
    })
);
const finalStatusLabel: Record<string, string> = { done: 'Completed', current: 'On now', skipped: 'Skipped', upcoming: 'Up next' };

// ---- Family Feud regular round ------------------------------------------------
// Feud reuses the guided-flow scaffolding (phase-driven steps, the scoreboard, the
// answer grid) but scores a POINT POOL rather than per-answer: reveals accrue the
// answers' survey points, and the whole pool × the round multiplier is awarded once
// at resolution — to the controlling team if they clear or the steal fails, the
// stealing team if it succeeds (see docs/family-feud-game-rules.md). Strikes are
// authoritative state the display flashes; 3 strikes hands to the one-guess steal.
//
// Phases (shared with the AS scaffolding where they line up):
//   intro → faceoff → question (play) → steal/reveal → recap.
const feudMultiplier = computed(() => {
    const q = currentQuestion.value;
    if (!q) return 1;
    if ((q.points_available ?? 0) > 0) return q.points_available;
    const sched = props.config?.round_multipliers ?? {};
    return Number(sched[String(q.round_number ?? 1)] ?? 1);
});
// The running pool: each revealed answer's pool contribution (its survey points,
// or 0 for a steal reveal — the stealer wins only the banked pool). Falls back to
// survey points if pool_points is absent (older payloads).
const feudPool = computed(() =>
    (currentQuestion.value?.answers ?? []).reduce(
        (sum, a) => sum + (a.revealed ? (a.pool_points ?? a.points ?? 0) : 0),
        0,
    )
);
const feudPot = computed(() => feudPool.value * feudMultiplier.value);
const feudStrikes = computed(() => Number(gameState.value?.state_data?.strikes ?? 0));
const feudMaxStrikes = computed(() => Number(props.config?.max_strikes ?? 3));

const feudStepIndex = computed(() => {
    switch (phase.value) {
        // Arming the face-off (still 'intro' on the backend) advances the checklist to
        // the Face-Off step, where "Show Question" lives.
        case 'intro': return faceoffArmed.value ? 1 : 0;
        case 'faceoff': return 1;
        case 'question': return 2;
        case 'steal':
        case 'reveal': return 3;
        case 'recap': return 4;
        default: return 0;
    }
});
const feudSteps = computed(() => [
    { title: 'Round Intro', hint: `Round ${gameState.value?.round_number ?? 1} is on the board. Start the face-off when the room's ready.` },
    { title: 'Face-Off', hint: 'Show the question, then mark who buzzed in first and reveal their answer (or Strike). Get the #1 answer and they choose; otherwise the other team answers and the higher points decides Play or Pass.' },
    { title: 'Playing', hint: `${primaryTeam.value?.name ?? 'The controlling team'} guesses the board — reveal answers as they’re said. A wrong guess is a Strike; ${feudMaxStrikes.value} strikes hands to the steal. Clearing the board wins the pool.` },
    phase.value === 'reveal'
        ? { title: 'Reveal', hint: 'Steal resolved — reveal the remaining answers on the board (no points). The scores show once the whole board is up.' }
        : { title: 'Steal', hint: `${stealTeam.value?.name ?? 'The other team'} gets ONE guess. A correct steal wins the whole pool; a miss and the original team keeps it.` },
    { title: 'Scores', hint: 'The pool has been awarded. Move on to the next round.' },
]);
const feudStepComplete = (i: number): boolean => i < feudStepIndex.value;

// Steps double as navigation. The steal (step 3) is only reached via strikes / the
// hand-off, so it isn't a jump target.
const feudGoToStep = async (i: number) => {
    if (i === feudStepIndex.value) return;
    const routeName = i === 0 ? 'host.round.intro'
        : i === 1 ? 'host.question.show'
        : i === 2 ? 'host.feud.play'
        : i === 4 ? 'host.round.end'
        : null;
    if (!routeName) return;
    await axios.post(route(routeName, props.gameSession.id));
    fetchState();
};

// Face-off state (who buzzed, whose turn, who decides Play/Pass). Populated once
// a team buzzes in; the decider is filled once the flow resolves.
const faceoff = computed<{ buzzed: number | null; turn: number | null; answers: Record<string, number>; decider: number | null } | null>(
    () => gameState.value?.state_data?.faceoff ?? null
);
const faceoffTurnTeam = computed<Team | null>(() => teams.value.find(t => t.id === faceoff.value?.turn) ?? null);
const faceoffDecider = computed<Team | null>(() => teams.value.find(t => t.id === faceoff.value?.decider) ?? null);
// "Armed" = the host has started the face-off from the intro (TV plays the face-off
// music + lights the bulbs). "Show Question" only appears once armed.
const faceoffArmed = computed<boolean>(() => !!gameState.value?.state_data?.faceoff_armed);
const feudStartFaceoff = async () => {
    await axios.post(route('host.feud.faceoff.start', props.gameSession.id));
    fetchState();
};
const feudFaceoffBuzz = async (teamId: number) => {
    await axios.post(route('host.feud.faceoff.buzz', props.gameSession.id), { team_id: teamId });
    fetchState();
};

const feudStrike = async () => {
    await axios.post(route('host.feud.strike', props.gameSession.id));
    fetchState();
};
const feudClearStrikes = async () => {
    await axios.post(route('host.feud.clear-strikes', props.gameSession.id));
    fetchState();
};
const feudStartPlay = async (decision?: { decision: 'play' | 'pass'; decision_team_id: number }) => {
    await axios.post(route('host.feud.play', props.gameSession.id), decision ?? {});
    fetchState();
};
// Play keeps the face-off winner; Pass hands control to the other team. Both then
// begin the controlling team's turn. Either way we send the deciding (face-off
// winner) team + their choice so the board can flash a "TEAM — PLAY/PASS" banner —
// captured BEFORE a pass flips control to the other side.
const feudPlay = async () => {
    const decider = faceoffDecider.value ?? faceoffTurnTeam.value ?? primaryTeam.value;
    await feudStartPlay(decider ? { decision: 'play', decision_team_id: decider.id } : undefined);
};
const feudPass = async () => {
    const decider = faceoffDecider.value ?? faceoffTurnTeam.value ?? primaryTeam.value;
    const other = idleTurnTeam.value;
    if (other) {
        await axios.post(route('host.control.team', props.gameSession.id), { team_id: other.id });
    }
    await feudStartPlay(decider ? { decision: 'pass', decision_team_id: decider.id } : undefined);
};
const feudResolve = async (outcome: 'clear' | 'steal_success' | 'steal_fail') => {
    await axios.post(route('host.feud.resolve', props.gameSession.id), { outcome });
    fetchState();
};
// Drop from the leftover-reveal beat to the scoreboard (award already happened).
const feudFinishReveal = async () => {
    await axios.post(route('host.feud.finish-reveal', props.gameSession.id));
    fetchState();
};

// Two auto-advances, both gated on the board being FULLY revealed:
//   • 'question' — the controlling team cleared the board → award the pool ('clear').
//                  Hold 1s after the final reveal (matching the steal's beat), then
//                  transition to the recap with the winner sting in sync (see below).
//   • 'reveal'   — a steal resolved and the host has now revealed the leftovers
//                  (no points) → hold 2s so the last answer lands, then drop to the
//                  scoreboard. We never jump to the scores until every answer is up.
const feudResolving = ref(false);
watch([allAnswersRevealed, phase], () => {
    if (!allAnswersRevealed.value || feudResolving.value || !isFamilyFeud) return;
    if (phase.value === 'question') {
        feudResolving.value = true;
        window.setTimeout(async () => {
            await feudResolve('clear');
            feudResolving.value = false;
        }, 1000);
    } else if (phase.value === 'reveal') {
        feudResolving.value = true;
        window.setTimeout(async () => {
            await feudFinishReveal();
            feudResolving.value = false;
        }, 2000);
    }
});
watch(() => currentQuestion.value?.id, () => { feudResolving.value = false; });

// Between rounds the scoreboard recap is just a 3-second beat — the next round's
// face-off slide shows the scores again and needs its own "Start Face-Off" click
// anyway, so there's no reason to make the host click "Next Round" too. Auto-advance
// after 3s. ONLY the plain next-round case auto-advances; when a team has hit the
// target the host still chooses (Start Fast Money / Finish Game), so we leave that
// button in place.
const feudRecapAdvancing = ref(false);
let feudRecapTimer: number | undefined;
const feudRecapAutoAdvance = computed(() =>
    isFamilyFeud && phase.value === 'recap' && !feudTargetReached.value
);
watch(feudRecapAutoAdvance, (auto) => {
    if (auto && feudRecapTimer === undefined && !feudRecapAdvancing.value) {
        feudRecapTimer = window.setTimeout(async () => {
            feudRecapAdvancing.value = true;
            try {
                await advanceQuestion();
            } finally {
                feudRecapTimer = undefined;
                feudRecapAdvancing.value = false;
            }
        }, 3000);
    } else if (!auto && feudRecapTimer !== undefined) {
        clearTimeout(feudRecapTimer);
        feudRecapTimer = undefined;
    }
}, { immediate: true });

// ---- Family Feud Fast Money ---------------------------------------------------
// Real-show, capture-then-reveal, per player. Phases: fast_money_intro →
// p1_capture → p1_reveal → p2_capture → p2_reveal → result. During capture the
// host records what the player said (hidden on the TV); during reveal the host
// puts up each answer's text then points, one at a time. A combined total ≥ the
// target wins. Player 1 = gold, Player 2 = green (matching the info panel + the
// answer highlights).
const isFastMoney = computed(() =>
    phase.value.startsWith('fast_money') || currentQuestion.value?.segment === 'fast_money'
);
const fmActivePlayer = computed(() => fastMoney.value?.active_player ?? 1);
const fmRows = computed<FmRow[]>(() => fastMoney.value?.rows ?? []);
const fmIsCapture = computed(() => phase.value.endsWith('_capture'));
const fmIsReveal = computed(() => phase.value.endsWith('_reveal'));
const fmTimerRunning = computed(() => fmIsCapture.value && !!gameState.value?.timer_started_at);
const fmActiveCell = (row: FmRow): FmCell => (fmActivePlayer.value === 1 ? row.p1 : row.p2);

// Fast Money step checklist (mirrors the regular-round / final steps).
const fmStepIndex = computed(() => {
    switch (phase.value) {
        case 'fast_money_p1_capture': return 0;
        case 'fast_money_p1_reveal': return 1;
        case 'fast_money_p2_capture': return 2;
        case 'fast_money_p2_reveal': return 3;
        case 'fast_money_result': return 4;
        default: return 0; // intro
    }
});
const fmSteps = ['Player 1 · Answers', 'Player 1 · Reveal', 'Player 2 · Answers', 'Player 2 · Reveal', 'Result'];

// Capture progress + the reveal cursor (the question we're revealing now).
const fmAllCaptured = computed(() => fmRows.value.length > 0 && fmRows.value.every(r => fmActiveCell(r).captured));
const fmAllRevealed = computed(() => fmRows.value.length > 0 && fmRows.value.every(r => fmActiveCell(r).scored));
// The moment a revealed answer pushes the combined total to the target, the game
// is won — the host can end it now (winner slide + theme) or keep revealing.
const fmClinched = computed(() =>
    fmIsReveal.value && (fastMoney.value?.combined_total ?? 0) >= (fastMoney.value?.target ?? 200)
);
// The row currently being revealed = first whose active cell isn't fully scored.
const fmRevealRow = computed<FmRow | null>(() =>
    fmRows.value.find(r => !fmActiveCell(r).scored) ?? null
);
// Next reveal action for that row: show the answer text, then flip the points.
const fmRevealPart = computed<'answer' | 'points' | null>(() => {
    const row = fmRevealRow.value;
    if (!row) return null;
    return fmActiveCell(row).shown ? 'points' : 'answer';
});

const fmStartPlayer = async (player: 1 | 2) => {
    timerExpiredHandled.value = false;
    // Player 1 kicks off a fresh Fast Money — clear the auto-advance latch.
    if (player === 1) fmAutoAdvanced.value = false;
    await axios.post(route('host.feud.fm.start', props.gameSession.id), { player });
    fetchState();
};
// Capture (hidden) what the active player said. A duplicate (P2 tapped P1's answer)
// isn't stored — the display buzzes so they guess again.
const fmCapture = async (sessionQuestionId: number, answerId?: number) => {
    await axios.post(route('host.feud.fm.capture', props.gameSession.id), {
        session_question_id: sessionQuestionId,
        ...(answerId ? { answer_id: answerId } : {}),
    });
    fetchState();
};
const fmClear = async (sessionQuestionId: number) => {
    await axios.post(route('host.feud.fm.clear', props.gameSession.id), { session_question_id: sessionQuestionId });
    fetchState();
};
const fmToReveal = async () => {
    await axios.post(route('host.feud.fm.to-reveal', props.gameSession.id));
    fetchState();
};
// Reveal the current cell's answer text, then (next click) its points.
const fmRevealNext = async () => {
    const row = fmRevealRow.value;
    const part = fmRevealPart.value;
    if (!row || !part) return;
    await axios.post(route('host.feud.fm.reveal-cell', props.gameSession.id), {
        session_question_id: row.id,
        part,
    });
    fetchState();
};
const fmNextPlayer = async () => {
    await axios.post(route('host.feud.fm.next-player', props.gameSession.id));
    fetchState();
};
const fmShowPrevious = async (show: boolean) => {
    await axios.post(route('host.feud.fm.show-previous', props.gameSession.id), { show });
    fetchState();
};
const fmResult = async () => {
    await axios.post(route('host.feud.fm.result', props.gameSession.id));
    fetchState();
};
// Pop back from the winner slide to the reveal board so the host can reveal any
// answers that weren't shown before the win (crowd-pleaser, purely for show).
const fmBackToReveal = async () => {
    await axios.post(route('host.feud.fm.back-to-reveal', props.gameSession.id));
    fetchState();
};
// Is a survey answer the one Player 1 used for this question (highlight + dup)?
const fmIsP1Answer = (row: FmRow, answerId: number): boolean =>
    row.p1.captured && row.p1.answer_id === answerId;
const fmPlayerColorPill = computed(() =>
    fmActivePlayer.value === 1 ? 'border-gold bg-gold/15 text-gold' : 'border-primary bg-primary/15 text-primary'
);
const fmPlayerColorText = computed(() => (fmActivePlayer.value === 1 ? 'text-gold' : 'text-primary'));
// A capture pill's style: the active player's pick is filled in their color; during
// Player 2 a pill Player 1 used is flagged gold (tapping it buzzes a duplicate).
const fmPillClass = (row: FmRow, answerId: number): string => {
    const cell = fmActiveCell(row);
    if (cell.captured && cell.answer_id === answerId) return fmPlayerColorPill.value;
    if (fmActivePlayer.value === 2 && fmIsP1Answer(row, answerId)) return 'border-gold bg-gold/15 text-gold';
    return 'border-border bg-surface-overlay text-body hover:border-primary';
};
// The "No match" pill stands out with a danger outline; once it's the pick it
// fills in the player's color like the survey pills.
const fmNoMatchClass = (row: FmRow): string =>
    (fmActiveCell(row).captured && fmActiveCell(row).answer_id == null)
        ? fmPlayerColorPill.value
        : 'border-danger text-danger hover:bg-danger/10';
// Click a pill to capture it; click the one already captured to take it back off.
// Changing the pick resets that row's spoken-text draft + "blank OK" so the new
// answer's prefill shows and the row must be resolved afresh.
const fmToggleCapture = (row: FmRow, answerId?: number) => {
    const cell = fmActiveCell(row);
    const key = fmMissKey(row);
    delete fmMissDrafts.value[key];
    delete fmBlankAck.value[key];
    if (cell.captured && (cell.answer_id ?? null) === (answerId ?? null)) return fmClear(row.id);
    return fmCapture(row.id, answerId);
};

// What the player actually SAID, shown on the board at reveal. Every captured cell
// gets this field: a matched survey answer prefills its canonical text (e.g. "CAR")
// on capture, which the host can override to the player's own wording (e.g.
// "AUTOMOBILE") — the matched answer's POINTS still apply; a no-match starts blank
// (0 pts). Local drafts (keyed by player+question) keep the 1s poll from clobbering
// in-progress typing; saved on blur.
const fmIsCaptured = (row: FmRow): boolean => fmActiveCell(row).captured;
const fmMissDrafts = ref<Record<string, string>>({});
const fmMissKey = (row: FmRow) => `${fmActivePlayer.value}-${row.id}`;
const fmMissValue = (row: FmRow): string => fmMissDrafts.value[fmMissKey(row)] ?? (fmActiveCell(row).text ?? '');
// Rows the host has explicitly OK'd as blank (the player gave nothing usable). This
// makes a blank a deliberate choice, never a forgotten field. Typing clears it.
const fmBlankAck = ref<Record<string, boolean>>({});
const fmBlankAcked = (row: FmRow): boolean => fmBlankAck.value[fmMissKey(row)] === true;
const fmMissInput = (row: FmRow, v: string) => {
    fmMissDrafts.value[fmMissKey(row)] = v;
    if (v.trim()) delete fmBlankAck.value[fmMissKey(row)];
};
const fmSaveMiss = async (row: FmRow) => {
    await axios.post(route('host.feud.fm.miss-text', props.gameSession.id), {
        session_question_id: row.id,
        text: fmMissValue(row),
    });
    fetchState();
};
// Mark a captured cell as intentionally blank: clears its text and satisfies the gate.
const fmLeaveBlank = async (row: FmRow) => {
    fmMissInput(row, '');
    fmBlankAck.value[fmMissKey(row)] = true;
    await fmSaveMiss(row);
};

// A captured row is "ready" to reveal once it's resolved: it has the spoken text
// (prefilled for a matched answer, typed otherwise) OR the host has OK'd it as blank.
// We can't reveal / move to the next player until every row is resolved, so a blank
// is always deliberate rather than forgotten.
const fmRowReady = (row: FmRow): boolean => {
    if (!fmActiveCell(row).captured) return false;
    if (fmMissValue(row).trim().length > 0) return true;
    return fmBlankAcked(row);
};
const fmAllReady = computed(() =>
    fmRows.value.length > 0 && fmRows.value.every(fmRowReady)
);

// Auto-drop to the celebratory result slide (winner + music, or "so close"). Two triggers:
//   • a WIN is clinched — the running total crosses the target mid-reveal, so we go
//     straight to the winner slide once (after ~1s so the clinching points land),
//     without the host clicking anything, and
//   • Player 2's board is fully revealed — the loss, OR the last leftover answer
//     revealed after the host popped back post-win: go to the End Game step at once
//     (nothing left to land, so no delay).
// fmAutoAdvanced latches after the first winner-slide drop so the clinch trigger
// doesn't re-fire when the host goes BACK to reveal leftovers (fmBackToReveal) —
// the all-revealed trigger takes them the rest of the way.
const fmResolving = ref(false);
const fmAutoAdvanced = ref(false);
const fmDropToResult = (delay: number) => {
    if (fmResolving.value) return;
    fmResolving.value = true;
    window.setTimeout(async () => {
        fmAutoAdvanced.value = true;
        await fmResult();
        fmResolving.value = false;
    }, delay);
};
watch(fmClinched, (clinched) => {
    if (clinched && fmIsReveal.value && !fmAutoAdvanced.value) fmDropToResult(1000);
});
watch([fmAllRevealed, phase], () => {
    // LOSS: hold ~2s on the fully-revealed board, then drop to the "So Close" result.
    // WIN: the winner slide already showed at the clinch; after the host pops back to
    // reveal the leftover answers we STAY on the board (they finish with End Game) —
    // don't bounce back to the winner slide.
    if (phase.value === 'fast_money_p2_reveal' && fmAllRevealed.value && !fmAutoAdvanced.value) {
        fmDropToResult(2000);
    }
});

// Dismiss the "All Answers Revealed" bonus prompt for this question (no sweep).
const dismissBonus = () => {
    if (currentQuestion.value) bonusDismissedQuestionId.value = currentQuestion.value.id;
};

// Award the current question's sweep bonus to a team.
const awardBonus = async (teamId: number) => {
    try {
        await axios.post(route('host.bonus', props.gameSession.id), { team_id: teamId });
        fetchState();
    } catch (error: any) {
        console.error('Failed to award bonus:', error);
        alert('Error: ' + (error.response?.data?.error || error.message));
    }
};

// Score-edit modal (opened by the scoreboard pencil).
const openScoreModal = () => {
    const edits: Record<number, string> = {};
    teams.value.forEach(t => { edits[t.id] = String(t.total_score); });
    scoreEdits.value = edits;
    showScoreModal.value = true;
};
const saveScores = async () => {
    const updates = teams.value
        .map(t => ({ id: t.id, current: t.total_score, next: parseInt(scoreEdits.value[t.id], 10) }))
        .filter(u => !isNaN(u.next) && u.next >= 0 && u.next !== u.current);
    await Promise.all(updates.map(u => updateTeamScore(u.id, u.next)));
    showScoreModal.value = false;
};

onMounted(() => {
    fetchState();
    pollInterval = window.setInterval(fetchState, 1000);
});
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (feudRecapTimer !== undefined) clearTimeout(feudRecapTimer);
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
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="md" @click="confirmBackToSetup">Game Setup</Button>
                    <Button v-if="currentQuestion" variant="danger" size="md" @click="showResetRoundConfirm = true">Reset Round</Button>
                    <!-- Global escape to the scoreboard, enabled whenever the clock isn't running. -->
                    <Button v-if="canShowScores" variant="primary" size="md" @click="endRound">Show Scores &rarr;</Button>
                    <!-- America Says & Family Feud advance via their guided Round Steps /
                         Final cards; other games use this header button. -->
                    <Button v-if="!isAmericaSays && !isFamilyFeud && currentQuestion && !isLastQuestion" variant="primary" size="md" @click="advanceQuestion">Next Question &rarr;</Button>
                    <!-- Only near the finish: the final board's clock, or the last round's scores. -->
                    <Button v-if="canEndGame" :variant="isLastQuestion ? 'secondary' : 'outline'" size="md" @click="endGame">End Game</Button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <!-- Scoreboard (left). Hidden in the America Says final round — it's
                     pass/fail with no scoring, so the Final Round card stands alone. -->
                <div class="lg:col-span-1">
                    <Scoreboard
                        v-if="!(isAmericaSays && isFinal) && !(isFamilyFeud && isFastMoney)"
                        :teams="teams"
                        :active-team-id="revealOnlyActive ? null : gameState?.active_team_id"
                        :controlling-team-ids="revealOnlyActive ? [] : boardControllingTeamIds"
                        :selectable="!isOodles"
                        :editable="true"
                        :show-reveal-only="showRevealOnly"
                        :reveal-only-active="revealOnlyActive"
                        :reveal-only-selectable="!isFamilyFeud"
                        @select-team="selectControllingTeam"
                        @edit-scores="openScoreModal"
                        @reveal-only="toggleRevealOnly"
                    />
                    <p v-if="!isOodles && currentQuestion && !isFinal" class="mt-2 text-center text-xs text-muted">
                        Click a team to give them the turn, or “Reveal only” to reveal without scoring
                    </p>

                    <!-- Round steps (America Says): the whole phase progression with
                         hints; the current phase is highlighted and carries its action. -->
                    <Card v-if="isAmericaSays && currentQuestion && !isFinal && !isTiebreaker" title="Round Steps" class="mt-4">
                        <div class="mb-3 flex flex-wrap items-center gap-2 border-b border-border pb-3">
                            <span class="text-sm font-medium text-muted">Round {{ gameState?.round_number ?? 1 }}<span v-if="(totalQuestions ?? 0) > 1"> · Question {{ currentQuestionNumber }} of {{ totalQuestions }}</span></span>
                        </div>
                        <ol class="space-y-2">
                            <li
                                v-for="(step, i) in roundSteps"
                                :key="i"
                                class="rounded-lg border p-3 transition-all"
                                :class="[
                                    i === roundStepIndex ? 'border-border bg-surface-inset ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : 'border-border bg-surface-inset',
                                    i !== roundStepIndex ? 'cursor-pointer hover:border-border-strong' : '',
                                ]"
                                @click="i !== roundStepIndex ? goToStep(i) : null"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                        :class="stepComplete(i) ? 'bg-success text-white' : 'bg-warning text-white'"
                                    >
                                        <span v-if="stepComplete(i)">&check;</span><span v-else>{{ i + 1 }}</span>
                                    </span>
                                    <span class="font-semibold" :class="i === roundStepIndex ? 'text-body' : 'text-muted'">{{ step.title }}</span>
                                </div>
                                <p class="mt-1 pl-8 text-xs" :class="i === roundStepIndex ? 'text-body' : 'text-subtle'">{{ step.hint }}</p>

                                <!-- The action lives on the current step -->
                                <div v-if="i === roundStepIndex" class="mt-3 flex flex-wrap gap-2 pl-8">
                                    <Button v-if="phase === 'intro'" variant="primary" size="sm" @click="showQuestion">Show Question</Button>
                                    <template v-else-if="phase === 'recap'">
                                        <Button v-if="finalQueued" variant="primary" size="sm" @click="advanceQuestion">{{ isLeadTie ? 'Start Tiebreaker' : 'Start Final Round' }} &rarr;</Button>
                                        <Button v-else-if="isLastQuestion" variant="secondary" size="sm" @click="endGame">End Game</Button>
                                        <Button v-else variant="primary" size="sm" @click="advanceQuestion">Next Question &rarr;</Button>
                                    </template>
                                    <!-- Steal / reveal-leftovers: reveal each correct steal on the
                                         board; a wrong answer (board cell) hands to Reveal only for the
                                         leftovers. The board ends itself once every answer is up. -->
                                    <template v-else-if="phase === 'steal' || phase === 'reveal'">
                                        <span class="text-sm text-muted">{{ phase === 'reveal' ? 'Reveal the leftovers (no points).' : 'Reveal steals on the board; a wrong answer hands to Reveal only.' }}</span>
                                    </template>
                                    <!-- Question Shown: start the clock for the primary team. -->
                                    <template v-else-if="!timerRunning">
                                        <Button variant="primary" size="sm" @click="startTimer">{{ startTimerLabel }}</Button>
                                    </template>
                                    <!-- Primary team's turn: reveal their answers. Time's up (or the
                                         "End Timer" button under the clock) hands to the steal; a full
                                         sweep jumps to the scores on its own. -->
                                    <template v-else>
                                        <span class="text-sm text-muted">Reveal {{ primaryTeam?.name ?? 'the team' }}’s answers as they’re guessed.</span>
                                    </template>
                                </div>
                            </li>
                        </ol>
                    </Card>

                    <!-- Round steps (Family Feud): face-off → play (with strikes) →
                         steal → scores. Mirrors the America Says checklist but scores
                         a point pool; the current phase carries its action. -->
                    <Card v-if="isFamilyFeud && currentQuestion && currentQuestion.segment !== 'fast_money'" title="Round Steps" class="mt-4">
                        <div class="mb-3 flex flex-wrap items-center gap-2 border-b border-border pb-3">
                            <span class="text-sm font-medium text-muted">Round {{ gameState?.round_number ?? 1 }}</span>
                            <span v-if="feudMultiplier > 1" class="rounded-full bg-gold/20 px-2 py-0.5 text-xs font-bold text-gold">{{ feudMultiplier }}&times; points</span>
                            <span class="ml-auto text-sm font-semibold text-body">Pool: {{ feudPot }}</span>
                        </div>
                        <ol class="space-y-2">
                            <li
                                v-for="(step, i) in feudSteps"
                                :key="i"
                                class="rounded-lg border p-3 transition-all"
                                :class="[
                                    i === feudStepIndex ? 'border-border bg-surface-inset ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : 'border-border bg-surface-inset',
                                    i !== feudStepIndex ? 'cursor-pointer hover:border-border-strong' : '',
                                ]"
                                @click="i !== feudStepIndex ? feudGoToStep(i) : null"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                        :class="feudStepComplete(i) ? 'bg-success text-white' : 'bg-warning text-white'"
                                    >
                                        <span v-if="feudStepComplete(i)">&check;</span><span v-else>{{ i + 1 }}</span>
                                    </span>
                                    <span class="font-semibold" :class="i === feudStepIndex ? 'text-body' : 'text-muted'">{{ step.title }}</span>
                                </div>
                                <p class="mt-1 pl-8 text-xs" :class="i === feudStepIndex ? 'text-body' : 'text-subtle'">{{ step.hint }}</p>

                                <div v-if="i === feudStepIndex" class="mt-3 flex flex-wrap gap-2 pl-8">
                                    <!-- Round Intro step: the only action is to start the face-off
                                         (fires the face-off music + lights on the TV). -->
                                    <template v-if="phase === 'intro' && !faceoffArmed">
                                        <Button variant="primary" size="sm" @click="feudStartFaceoff">Start Face-Off</Button>
                                    </template>
                                    <!-- Face-Off step, before the board: show the question to bring it
                                         up for the buzz-in. (Armed but still 'intro' on the backend.) -->
                                    <template v-else-if="phase === 'intro' && faceoffArmed">
                                        <Button variant="primary" size="sm" @click="showQuestion">Show Question</Button>
                                    </template>

                                    <!-- Face-off proper. Before a buzz: who buzzed in first?
                                         After: reveal their answer (or Strike) in the
                                         answers section — the winner is worked out
                                         automatically. Play/Pass appears once decided. -->
                                    <template v-else-if="phase === 'faceoff'">
                                        <template v-if="faceoffDecider">
                                            <span class="w-full text-xs text-muted"><span class="font-semibold text-body">{{ faceoffDecider.name }}</span> won the face-off — play or pass?</span>
                                            <Button variant="primary" size="sm" @click="feudPlay">Play</Button>
                                            <Button variant="secondary" size="sm" @click="feudPass">Pass to {{ idleTurnTeam?.name ?? 'other team' }}</Button>
                                        </template>
                                        <template v-else-if="faceoff?.buzzed">
                                            <span class="w-full text-xs text-muted"><span class="font-semibold text-body">{{ faceoffTurnTeam?.name ?? 'The team' }}</span> is up — reveal their answer, or hit Strike in the answers section.</span>
                                        </template>
                                        <template v-else>
                                            <span class="w-full text-xs text-muted">Who buzzed in first?</span>
                                            <!-- Buzz-in buttons in each team's own color (team colors
                                                 are the sanctioned inline-style exception). -->
                                            <button
                                                v-for="team in teams"
                                                :key="team.id"
                                                type="button"
                                                class="rounded-lg px-3 py-1.5 text-sm font-bold text-white shadow transition-all hover:opacity-90"
                                                :style="{ backgroundColor: team.color }"
                                                @click="feudFaceoffBuzz(team.id)"
                                            >{{ team.name }}</button>
                                        </template>
                                    </template>

                                    <!-- Playing: strike tracker (the Strike button lives in the answers section). -->
                                    <template v-else-if="phase === 'question'">
                                        <div class="flex w-full items-center gap-2">
                                            <span class="text-xs text-muted">Strikes:</span>
                                            <span class="flex gap-1">
                                                <span
                                                    v-for="n in feudMaxStrikes"
                                                    :key="n"
                                                    class="flex h-6 w-6 items-center justify-center rounded-full border text-sm font-black"
                                                    :class="n <= feudStrikes ? 'border-danger bg-danger/20 text-danger' : 'border-border text-subtle'"
                                                >&times;</span>
                                            </span>
                                            <Button v-if="feudStrikes > 0" variant="muted" size="xs" class="ml-1" @click="feudClearStrikes">Clear</Button>
                                        </div>
                                    </template>

                                    <!-- Steal: one guess. A revealed answer wins the pool; the Strike
                                         button ends it as a miss — both resolve automatically. Then the
                                         board holds on 'reveal' so the host puts up the un-guessed
                                         answers (no points) before the scores appear. -->
                                    <template v-else-if="phase === 'steal'">
                                        <span class="w-full text-xs text-muted">{{ stealTeam?.name ?? 'The other team' }} gets one guess — reveal a correct answer, or hit Strike for a miss.</span>
                                    </template>
                                    <template v-else-if="phase === 'reveal'">
                                        <span class="w-full text-xs text-muted">Reveal the remaining answers on the board (no points). The scores show once the whole board is up.</span>
                                    </template>

                                    <!-- Scores. Regular play is score-driven: once a team hits the
                                         target (300) the advance goes to Fast Money (or finishes if
                                         it's off); otherwise it's the next round. The backend routes
                                         it — this just labels the button. -->
                                    <template v-else-if="phase === 'recap'">
                                        <!-- Target reached: the host still chooses what's next. -->
                                        <Button v-if="feudTargetReached" variant="primary" size="sm" @click="advanceQuestion">
                                            {{ feudFastMoneyReady ? 'Start Fast Money' : 'Finish Game' }} &rarr;
                                        </Button>
                                        <!-- Otherwise the scores hold for a beat, then the next round's
                                             face-off slide comes up on its own (no button — the face-off
                                             has its own Start Face-Off, and that slide shows the scores). -->
                                        <span v-else class="text-xs text-muted">Scores are up — next round starting…</span>
                                    </template>
                                </div>
                            </li>
                        </ol>
                    </Card>

                    <!-- Fast Money (Family Feud): the team scoreboard is replaced by a
                         Player 1 / Player 2 control panel (informational — flips only on
                         Next Player) plus the capture → reveal step checklist. -->
                    <template v-if="isFamilyFeud && isFastMoney">
                        <Card title="Fast Money">
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    class="rounded-lg border-2 p-3 text-center transition-all"
                                    :class="fmActivePlayer === 1 ? 'border-gold bg-gold/10' : 'border-border bg-surface-inset opacity-60'"
                                >
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gold">Player 1</div>
                                    <div class="mt-1 text-2xl font-black text-body">{{ fastMoney?.p1_total ?? 0 }}</div>
                                    <div v-if="fmActivePlayer === 1" class="mt-1 text-[10px] font-bold uppercase text-gold">In control</div>
                                </div>
                                <div
                                    class="rounded-lg border-2 p-3 text-center transition-all"
                                    :class="fmActivePlayer === 2 ? 'border-primary bg-primary/10' : 'border-border bg-surface-inset opacity-60'"
                                >
                                    <div class="text-xs font-semibold uppercase tracking-wide text-primary">Player 2</div>
                                    <div class="mt-1 text-2xl font-black text-body">{{ fastMoney?.p2_total ?? 0 }}</div>
                                    <div v-if="fmActivePlayer === 2" class="mt-1 text-[10px] font-bold uppercase text-primary">In control</div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-border pt-3">
                                <span class="text-sm text-muted">Total</span>
                                <span class="rounded-full bg-gold/20 px-3 py-1 text-sm font-bold text-gold">{{ fastMoney?.combined_total ?? 0 }}</span>
                            </div>
                        </Card>

                        <Card title="Steps" class="mt-4">
                            <ol class="space-y-2">
                                <li
                                    v-for="(step, i) in fmSteps"
                                    :key="i"
                                    class="rounded-lg border p-3 transition-all"
                                    :class="i === fmStepIndex ? 'border-border bg-surface-inset ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : 'border-border bg-surface-inset opacity-70'"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                            :class="i < fmStepIndex ? 'bg-success text-white' : (i === fmStepIndex ? 'bg-warning text-white' : 'bg-surface-overlay text-muted')"
                                        >
                                            <span v-if="i < fmStepIndex">&check;</span><span v-else>{{ i + 1 }}</span>
                                        </span>
                                        <span class="text-sm font-semibold" :class="i === fmStepIndex ? 'text-body' : 'text-muted'">{{ step }}</span>
                                    </div>

                                    <!-- The action lives on the current step (like the regular rounds). -->
                                    <div v-if="i === fmStepIndex" class="mt-3 flex flex-wrap gap-2 pl-8">
                                        <!-- Player 1 answers: start the clock (from the intro), then
                                             reveal once all five are captured. -->
                                        <template v-if="phase === 'fast_money_intro'">
                                            <Button variant="primary" size="sm" @click="fmStartPlayer(1)">Start Timer &rarr;</Button>
                                        </template>
                                        <template v-else-if="fmIsCapture">
                                            <!-- Player 2 bring-out (before the clock): flash P1's board, then start. -->
                                            <template v-if="!fmTimerRunning">
                                                <Button v-if="!fastMoney?.show_previous" variant="outline" size="sm" @click="fmShowPrevious(true)">Show Player 1's Answers</Button>
                                                <Button variant="primary" size="sm" @click="fmStartPlayer(2)">Start Timer &rarr;</Button>
                                            </template>
                                            <template v-else>
                                                <Button v-if="fmAllReady" variant="primary" size="sm" @click="fmToReveal">Reveal Answers &rarr;</Button>
                                                <span v-else-if="fmAllCaptured" class="text-xs text-danger">Finish each answer — type what they said, or mark it blank — before revealing.</span>
                                                <span v-else class="text-xs text-muted">Capture each answer on the board, then reveal.</span>
                                            </template>
                                        </template>
                                        <!-- Reveal: answer text, then points, one at a time. When the
                                             total crosses the target the game auto-drops to the winner
                                             slide (+ music) after ~2s — no button. If the host popped
                                             back to reveal leftovers, revealing the last one drops to the
                                             result (End Game) step on its own. -->
                                        <template v-else-if="fmIsReveal">
                                            <Button v-if="fmRevealRow" variant="primary" size="sm" @click="fmRevealNext">
                                                {{ fmRevealPart === 'points' ? 'Reveal Points' : 'Reveal Answer' }} &rarr;
                                            </Button>
                                            <span v-else-if="fmClinched && !fmAutoAdvanced" class="text-xs text-gold">🎉 Target reached — winner slide coming up…</span>
                                            <template v-else-if="!fmRevealRow">
                                                <Button v-if="phase === 'fast_money_p1_reveal'" variant="primary" size="sm" @click="fmNextPlayer">Next Player &rarr;</Button>
                                                <!-- Won already + every answer revealed → stay on the board; End Game to finish. -->
                                                <Button v-else-if="fmAllRevealed && fmAutoAdvanced" variant="primary" size="sm" @click="endGame">End Game &rarr;</Button>
                                                <span v-else class="text-xs text-muted">Revealing the result…</span>
                                            </template>
                                        </template>
                                        <!-- Result. The host may pop back to the board to reveal any
                                             answers that weren't shown before the win, just for fun. -->
                                        <template v-else-if="phase === 'fast_money_result'">
                                            <Button v-if="!fmAllRevealed" variant="outline" size="sm" @click="fmBackToReveal">Reveal Remaining Answers &rarr;</Button>
                                            <Button variant="secondary" size="sm" @click="endGame">End Game</Button>
                                        </template>
                                    </div>
                                </li>
                            </ol>
                        </Card>
                    </template>

                    <!-- Final round (America Says): a per-question step checklist that
                         mirrors a regular round — one question at a time flows Get Ready
                         → Question Shown → Team's Playing → Board Complete, with a
                         "Question X of Y" header. Out-of-time / result swap the checklist
                         for a review list / banner. -->
                    <Card v-if="isAmericaSays && isFinal" title="Final Round" class="mt-4">
                        <!-- Header: the team playing + which question of how many. -->
                        <div class="mb-3 flex flex-wrap items-center gap-2 border-b border-border pb-3">
                            <span v-if="finalTeam" class="flex items-center gap-2 text-sm font-semibold text-body">
                                <span class="h-3 w-3 flex-none rounded-full" :style="{ backgroundColor: finalTeam.color }"></span>
                                {{ finalTeam.name }}
                            </span>
                            <span class="text-sm font-medium text-muted">{{ finalInReview ? 'Reviewing misses' : `Question ${finalQuestionNumber} of ${finalTotal}` }}</span>
                        </div>

                        <!-- Status key: all four final questions at a glance
                             (done / skipped / on now / up next). -->
                        <div v-if="!finalInReview" class="mb-4">
                            <div class="flex gap-1.5">
                                <div
                                    v-for="q in finalStatusList"
                                    :key="q.id"
                                    class="flex flex-1 items-center justify-center gap-1 rounded-md border px-1.5 py-1 text-xs font-bold"
                                    :class="{
                                        'border-success bg-success/15 text-success': q.status === 'done',
                                        'border-info bg-info/10 text-info': q.status === 'skipped',
                                        'border-border bg-surface-overlay text-body ring-2 ring-white': q.status === 'current',
                                        'border-border bg-surface-inset text-subtle': q.status === 'upcoming',
                                    }"
                                    :title="`Question ${q.n} — ${finalStatusLabel[q.status]}`"
                                >
                                    <span>{{ q.n }}</span>
                                    <span v-if="q.status === 'done'">&check;</span>
                                </div>
                            </div>
                            <p class="mt-1.5 text-[11px] text-subtle">
                                <span class="text-success">Done</span> ·
                                <span class="text-info">Skipped</span> ·
                                <span class="text-body">On now</span> ·
                                <span>Up next</span>
                            </p>
                        </div>

                        <!-- Per-question step checklist -->
                        <ol v-if="finalShowSteps" class="space-y-2">
                            <li
                                v-for="(step, i) in finalSteps"
                                :key="i"
                                class="rounded-lg border border-border bg-surface-inset p-3 transition-all"
                                :class="i === finalStepIndex ? 'ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : ''"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                        :class="i < finalStepIndex ? 'bg-success text-white' : 'bg-warning text-white'"
                                    >
                                        <span v-if="i < finalStepIndex">&check;</span><span v-else>{{ i + 1 }}</span>
                                    </span>
                                    <span class="font-semibold" :class="i === finalStepIndex ? 'text-body' : 'text-muted'">{{ step.title }}</span>
                                    <span
                                        v-if="(i === 2 || i === 3) && currentFinalQuestion"
                                        class="ml-auto text-xs text-subtle"
                                    >{{ currentFinalQuestion.revealed_count }}/{{ currentFinalQuestion.total_answers }} answers</span>
                                </div>

                                <!-- Hint + action live on the current step -->
                                <template v-if="i === finalStepIndex">
                                    <p class="mt-1 pl-8 text-xs text-body">
                                        <template v-if="phase === 'final_intro' || phase === 'final_ready'">{{ finalTeam?.name ?? 'The team' }} is up. Show the question on the board when they’re ready.</template>
                                        <template v-else-if="phase === 'final_question'">The question is on the board — answers hidden, {{ gameState?.timer_duration ?? 60 }}s banked. Read it aloud, then reveal the board to start the clock.</template>
                                        <template v-else-if="phase === 'final_play'">Board and clock are up — reveal answers as they’re guessed. The clock auto-pauses when the board is complete; if it runs out, the board goes to review.</template>
                                        <template v-else-if="phase === 'final_cleared'">All answers in — they stay on the board. Show the next question when you’re ready.</template>
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2 pl-8">
                                        <Button v-if="phase === 'final_intro' || phase === 'final_ready'" variant="primary" size="sm" @click="finalShowQuestion">Show Question</Button>
                                        <Button v-else-if="phase === 'final_question'" variant="primary" size="sm" @click="finalStart">Reveal Board - Start Timer</Button>
                                        <template v-else-if="phase === 'final_play'">
                                            <Button v-if="!finalSkipUsed" variant="secondary" size="sm" @click="finalSkip">Skip this question (1 per final)</Button>
                                            <span v-else class="text-xs text-subtle">Skip already used</span>
                                        </template>
                                        <Button v-else-if="phase === 'final_cleared'" variant="primary" size="sm" @click="finalNext">Next Question &rarr;</Button>
                                    </div>
                                </template>
                            </li>
                        </ol>

                        <!-- Out of time → review: jump to any question to reveal misses. -->
                        <div v-else-if="finalInReview" class="space-y-3">
                            <div class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-center">
                                <p class="font-semibold text-danger">Out of time</p>
                                <p class="mt-1 text-xs text-muted">Tap a question to jump to it, then reveal the answers they missed.</p>
                            </div>
                            <ol class="space-y-2">
                                <li
                                    v-for="(fq, i) in finalQuestions"
                                    :key="fq.id"
                                    class="rounded-lg border border-border bg-surface-inset p-3 transition-all"
                                    :class="[
                                        fq.is_current ? 'ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : '',
                                        !fq.is_current ? 'cursor-pointer hover:border-border-strong' : '',
                                    ]"
                                    @click="!fq.is_current ? finalSelect(fq.id) : null"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                            :class="fq.status === 'completed' ? 'bg-success text-white' : 'bg-warning text-white'"
                                        >
                                            <span v-if="fq.status === 'completed'">&check;</span><span v-else>{{ i + 1 }}</span>
                                        </span>
                                        <span class="font-semibold" :class="fq.is_current ? 'text-body' : 'text-muted'">Question {{ i + 1 }}</span>
                                        <span class="ml-auto text-xs text-subtle">{{ fq.revealed_count }}/{{ fq.total_answers }} answers</span>
                                    </div>
                                </li>
                            </ol>
                            <Button variant="secondary" size="md" class="w-full" @click="endGame">End Game</Button>
                        </div>

                        <!-- Pass/fail result -->
                        <div v-else-if="finalInResult" class="space-y-3">
                            <div
                                class="rounded-lg p-3 text-center"
                                :class="finalResult === 'win' ? 'border border-success/40 bg-success/10' : 'border border-danger/40 bg-danger/10'"
                            >
                                <p class="text-lg font-bold" :class="finalResult === 'win' ? 'text-success' : 'text-danger'">
                                    {{ finalResult === 'win' ? 'They did it! 🎉' : 'Out of time' }}
                                </p>
                            </div>
                            <Button variant="secondary" size="md" class="w-full" @click="endGame">End Game</Button>
                        </div>
                    </Card>

                    <!-- Tie-off (America Says): mirrors a regular round's 4-step
                         checklist, but the last step declares the tie-off winner
                         (who then plays the final) instead of showing scores. -->
                    <Card v-if="isAmericaSays && isTiebreaker" title="Tiebreaker" class="mt-4">
                        <ol class="space-y-2">
                            <li
                                v-for="(step, i) in tiebreakerSteps"
                                :key="i"
                                class="rounded-lg border p-3 transition-all"
                                :class="i === tiebreakerStepIndex ? 'border-border bg-surface-inset ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]' : 'border-border bg-surface-inset'"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold"
                                        :class="i < tiebreakerStepIndex ? 'bg-success text-white' : 'bg-warning text-white'"
                                    >
                                        <span v-if="i < tiebreakerStepIndex">&check;</span><span v-else>{{ i + 1 }}</span>
                                    </span>
                                    <span class="font-semibold" :class="i === tiebreakerStepIndex ? 'text-body' : 'text-muted'">{{ step.title }}</span>
                                </div>
                                <p class="mt-1 pl-8 text-xs" :class="i === tiebreakerStepIndex ? 'text-body' : 'text-subtle'">{{ step.hint }}</p>

                                <!-- The action lives on the current step -->
                                <div v-if="i === tiebreakerStepIndex" class="mt-3 pl-8">
                                    <Button v-if="phase === 'tiebreaker_intro'" variant="primary" size="sm" @click="tiebreakerShow">Show Question &rarr;</Button>
                                    <Button v-else-if="phase === 'tiebreaker_question'" variant="primary" size="sm" @click="tiebreakerRevealBoard">Reveal Board</Button>
                                    <Button v-else-if="phase === 'tiebreaker_play'" variant="primary" size="sm" @click="tiebreakerToDeclare">Declare Winner &rarr;</Button>
                                    <div v-else-if="phase === 'tiebreaker_declare'" class="grid grid-cols-1 gap-2">
                                        <button
                                            v-for="team in tiebreakerTeams"
                                            :key="team.id"
                                            class="flex items-center justify-center gap-2 rounded-lg border-2 p-3 font-bold text-white transition-all"
                                            :style="{ backgroundColor: team.color, borderColor: team.color }"
                                            @click="tiebreakerResolve(team.id)"
                                        >🏆 {{ team.name }} won the tie-off</button>
                                    </div>
                                    <Button v-else-if="phase === 'tiebreaker_result'" variant="primary" size="sm" @click="tiebreakerToFinal">Start Final Round &rarr;</Button>
                                </div>
                            </li>
                        </ol>
                    </Card>

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
                        <!-- Just-for-fun opener: read it out, no points, no control -->
                        <div
                            v-if="currentCard.bonus_question"
                            class="mt-4 rounded-lg border border-border bg-surface-inset px-4 py-3"
                        >
                            <span class="text-xs font-bold uppercase tracking-widest text-muted">Just for fun</span>
                            <p class="mt-1 text-body">{{ currentCard.bonus_question.question_text }}</p>
                            <p class="mt-1 text-sm text-success">
                                Answer: {{ currentCard.bonus_question.answer_text ?? '—' }}
                            </p>
                        </div>
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
                                    <span class="font-medium"><BlankText :text="question.question_text" /></span>
                                </div>
                                <span v-if="question.status === 'completed'" class="font-semibold text-success">✓ Done</span>
                            </button>
                        </div>
                    </Card>

                    <!-- Fast Money (Family Feud): capture-then-reveal, per player.
                         Capture records what the player said (hidden on the TV); reveal
                         puts up each answer's text then points, one at a time. -->
                    <Card v-if="isFamilyFeud && isFastMoney" :title="`Fast Money · Player ${fmActivePlayer}`">
                        <!-- Intro: the action (Start Timer) lives in the Steps panel. -->
                        <div v-if="phase === 'fast_money_intro'" class="py-8 text-center text-muted">
                            Two players from the winning team play. Player 1 answers all 5 questions against the clock, then we reveal them one at a time. Start the timer from the Steps panel.
                        </div>

                        <!-- CAPTURE — record the active player's answers (hidden on TV). -->
                        <template v-else-if="fmIsCapture">
                            <!-- Bring-out beat: before the clock starts (Player 2). -->
                            <div v-if="!fmTimerRunning" class="py-8 text-center text-muted">
                                Bring out <span class="font-semibold text-primary">Player 2</span>. Use the Steps panel to flash Player 1's board while they're turned away, then start the timer.
                            </div>

                            <template v-else>
                                <!-- Timer (America Says style): size sm, right-aligned in the header. -->
                                <div class="mb-6 flex items-center justify-end gap-4 lg:grid lg:grid-cols-3">
                                    <span class="hidden lg:col-span-2 lg:block"></span>
                                    <GameTimer
                                        v-if="gameState"
                                        size="sm"
                                        :timer-started-at="gameState.timer_started_at"
                                        :timer-duration="gameState.timer_duration"
                                        :is-host="true"
                                        :hide-start="true"
                                        @pause="pauseTimer"
                                        @reset="resetTimer"
                                    />
                                </div>

                                <div class="space-y-3">
                                    <div v-for="row in fmRows" :key="row.id" class="rounded-lg border border-border bg-surface-inset p-3">
                                        <div class="mb-2 flex items-center gap-2">
                                            <span class="min-w-0 flex-1 font-semibold text-body">{{ row.question }}</span>
                                            <!-- Per-answer status: a green check once solidified; a red flag on a
                                                 No-match whose words haven't been typed yet. -->
                                            <span v-if="fmRowReady(row)" class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-success/20 text-xs font-bold text-success" title="Ready to reveal">&check;</span>
                                            <span v-else-if="fmIsCaptured(row)" class="flex-none text-xs font-semibold text-danger" title="Type what they said below, or mark it blank">Needs answer</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <button
                                                v-for="ans in row.answers"
                                                :key="ans.id"
                                                class="rounded-full border px-2.5 py-1 text-xs font-medium transition-all"
                                                :class="fmPillClass(row, ans.id)"
                                                :title="fmActivePlayer === 2 && fmIsP1Answer(row, ans.id) ? 'Player 1 used this — tapping buzzes a duplicate' : 'Click to capture; click again to remove'"
                                                @click="fmToggleCapture(row, ans.id)"
                                            >{{ ans.text }} <span class="opacity-60">{{ ans.points }}</span></button>
                                            <button class="rounded-full border px-2.5 py-1 text-xs font-medium transition-all" :class="fmNoMatchClass(row)" @click="fmToggleCapture(row)">No match</button>
                                        </div>
                                        <!-- What the player said (shown on the board at reveal). Prefilled
                                             from the chip for a matched answer — override it to their exact
                                             words and the chip's points still apply. A blank is allowed but
                                             must be confirmed with "Leave blank" so it's never just forgotten. -->
                                        <div v-if="fmIsCaptured(row)" class="mt-2 flex items-center gap-2">
                                            <input
                                                :value="fmMissValue(row)"
                                                type="text"
                                                placeholder="What did they say?"
                                                class="w-full rounded-lg bg-surface-inset text-sm text-body placeholder:text-muted"
                                                :class="fmRowReady(row)
                                                    ? 'border-border focus:border-primary focus:ring-primary'
                                                    : 'border-danger ring-1 ring-danger focus:border-danger focus:ring-danger'"
                                                @input="fmMissInput(row, ($event.target as HTMLInputElement).value)"
                                                @blur="fmSaveMiss(row)"
                                                @keyup.enter="($event.target as HTMLInputElement).blur()"
                                            />
                                            <Button v-if="!fmMissValue(row).trim() && !fmBlankAcked(row)" variant="outline" size="sm" class="flex-none" @click="fmLeaveBlank(row)">Leave blank</Button>
                                            <span v-else-if="!fmMissValue(row).trim()" class="flex-none text-xs font-medium text-muted">Left blank</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- REVEAL — the current answer is spotlighted; the Reveal Answer /
                             Reveal Points actions live in the Steps panel. -->
                        <template v-else-if="fmIsReveal">
                            <div class="mb-4 flex flex-wrap items-center gap-3 border-b border-border pb-3">
                                <span class="font-semibold text-body">Player {{ fmActivePlayer }} — reveal answers</span>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(row, ri) in fmRows"
                                    :key="row.id"
                                    class="rounded-lg border p-3 transition-all"
                                    :class="fmRevealRow && row.id === fmRevealRow.id
                                        ? 'border-border bg-surface-inset ring-2 ring-white shadow-[0_0_18px_2px_rgba(255,255,255,0.45)]'
                                        : 'border-border bg-surface-inset opacity-70'"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-surface-overlay text-xs font-bold text-body">{{ ri + 1 }}</span>
                                        <span class="min-w-0 flex-1 text-sm font-medium text-body">{{ row.question }}</span>
                                        <!-- Show exactly what the host typed for this cell (the player's
                                             words — prefilled from the chip, or overridden), never the chip
                                             label or "No match"; a confirmed blank shows a neutral dash.
                                             Answer + points start faded; Reveal Answer un-fades the text to
                                             the player's color, Reveal Points un-fades the points to white. -->
                                        <span
                                            class="text-sm font-bold transition-all"
                                            :class="fmActiveCell(row).shown ? fmPlayerColorText : 'text-subtle opacity-40'"
                                        >{{ fmActiveCell(row).text || '—' }}</span>
                                        <span
                                            class="w-10 text-right text-sm font-black transition-all"
                                            :class="fmActiveCell(row).scored ? 'text-body' : 'text-subtle opacity-40'"
                                        >{{ fmActiveCell(row).points ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- RESULT — End Game lives in the Steps panel. -->
                        <div v-else-if="phase === 'fast_money_result'" class="flex flex-col items-center gap-3 py-8 text-center">
                            <p class="text-3xl font-black" :class="fastMoney?.result === 'win' ? 'text-success' : 'text-danger'">
                                {{ fastMoney?.result === 'win' ? 'Winner! 🎉' : 'Missed the target' }}
                            </p>
                            <p class="text-xl font-bold text-body">{{ fastMoney?.combined_total ?? 0 }} points</p>
                        </div>
                    </Card>

                    <!-- Question & Answers -->
                    <Card v-if="!(isFamilyFeud && isFastMoney)">
                        <div v-if="currentQuestion">
                            <!-- Header: question info (left, 2/3) + timer (right, 1/3) -->
                            <div class="mb-6 grid grid-cols-1 items-center gap-4 lg:grid-cols-3">
                                <div class="text-center lg:col-span-2 lg:text-left">
                                    <div v-if="roundLabel || (currentQuestionNumber && (totalQuestions ?? 0) > 1)" class="mb-2 flex flex-wrap items-center gap-2">
                                        <span v-if="roundLabel" class="rounded-full border border-primary/50 px-3 py-1 text-sm font-semibold text-primary">{{ roundLabel }}</span>
                                        <span v-if="currentQuestionNumber && (totalQuestions ?? 0) > 1" class="rounded-full bg-surface-inset px-3 py-1 text-sm font-medium text-muted">Question {{ currentQuestionNumber }} of {{ totalQuestions }}</span>
                                    </div>
                                    <div class="flex items-center justify-center gap-2 lg:justify-start">
                                        <h3 class="text-2xl font-bold text-body"><BlankText :text="currentQuestion.question_text" /></h3>
                                        <Button
                                            v-if="isTiebreaker && ['tiebreaker_intro', 'tiebreaker_question'].includes(phase)"
                                            variant="muted"
                                            size="xs"
                                            class="flex-none"
                                            title="Swap the tiebreaker question"
                                            @click="tiebreakerSwap"
                                        >⇄ Swap</Button>
                                    </div>
                                </div>
                                <!-- Family Feud has no clock in the regular rounds (Fast
                                     Money uses its own timer in the Fast Money card). -->
                                <GameTimer
                                    v-if="gameState && !isTiebreaker && !isFamilyFeud"
                                    size="sm"
                                    :timer-started-at="gameState.timer_started_at"
                                    :timer-duration="gameState.timer_duration"
                                    :is-host="true"
                                    :hide-start="isAmericaSays"
                                    :hide-controls="isFinal"
                                    @start="startTimer"
                                    @pause="pauseTimer"
                                    @reset="resetTimer"
                                    @expired="onTimerExpired"
                                >
                                    <!-- End the primary clock early → hand to the steal (confirmed). -->
                                    <template #controls="{ size }">
                                        <Button
                                            v-if="isAmericaSays && !isFinal && phase === 'question' && timerRunning && stealTeam && !allAnswersRevealed"
                                            variant="danger"
                                            :size="size"
                                            @click="showTimesUpConfirm = true"
                                        >End Timer</Button>
                                    </template>
                                </GameTimer>
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

                            <!-- America Says: all revealed + optional sweep bonus -->
                            <div
                                v-if="isAmericaSays && !isFinal && !isTiebreaker && phase === 'question' && allAnswersRevealed && !revealWithoutPoints && bonusDismissedQuestionId !== currentQuestion.id"
                                class="mb-4 rounded-lg border border-success/40 bg-success/10 p-4 text-center"
                            >
                                <span class="text-xl font-bold text-success">All Answers Revealed!</span>
                                <template v-if="currentQuestion.bonus_points > 0">
                                    <p v-if="currentQuestion.bonus_awarded_team_id" class="mt-2 text-muted">
                                        Sweep bonus ({{ currentQuestion.bonus_points }} pts) awarded.
                                    </p>
                                    <template v-else>
                                        <p class="mt-2 text-muted">Award the {{ currentQuestion.bonus_points }} pt sweep bonus if one team cleared the board:</p>
                                        <div class="mt-3 flex flex-wrap justify-center gap-2">
                                            <Button
                                                v-for="team in teams"
                                                :key="team.id"
                                                size="md"
                                                class="text-white"
                                                :style="{ backgroundColor: team.color }"
                                                @click="awardBonus(team.id)"
                                            >
                                                {{ team.name }}
                                            </Button>
                                            <Button variant="muted" size="md" @click="dismissBonus">No Bonus</Button>
                                        </div>
                                    </template>
                                </template>
                            </div>

                            <!-- Answers (America Says / Family Feud). In the final round
                                 answers award no points and can only be revealed while a
                                 question is live (phase 'final_play'). -->
                            <div v-if="!isOodles" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <button
                                    v-for="answer in orderedAnswers"
                                    :key="answer.id"
                                    :disabled="(isFinal && phase !== 'final_play' && phase !== 'final_review') || (isTiebreaker && !tiebreakerAnswersActive)"
                                    :title="isTiebreaker && !tiebreakerAnswersActive ? 'Tie-off answer (reveal the board first)' : (answer.revealed ? 'Click to undo' : 'Click to reveal')"
                                    class="hover-glow rounded-lg border p-4 text-left transition-all"
                                    :class="[
                                        answer.revealed
                                            ? 'border-success bg-success/10 text-body shadow-[0_0_18px_-2px_rgb(var(--color-success)_/_0.55)]'
                                            : 'border-border bg-surface-inset text-body',
                                        (isFinal && phase !== 'final_play' && phase !== 'final_review') ? 'cursor-not-allowed opacity-50' : (isTiebreaker && !tiebreakerAnswersActive ? 'cursor-default' : 'cursor-pointer'),
                                    ]"
                                    @click="toggleAnswer(answer)"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold">{{ answer.answer_text }}</span>
                                        <span v-if="!isFinal && !isTiebreaker" class="text-lg font-bold text-muted">{{ answerPoints(answer) }} pts</span>
                                    </div>
                                </button>

                                <!-- Wrong-answer buzzer: sits in the next open grid cell
                                     (the 8th slot on a 7-answer board), styled like the
                                     answer cells but in danger colors. Just sounds the cue
                                     on the display — no board/score change. -->
                                <button
                                    v-if="isAmericaSays && !isTiebreaker"
                                    type="button"
                                    :title="phase === 'steal' ? 'Wrong steal — ends the board' : 'Sound the wrong-answer buzzer'"
                                    class="hover-glow cursor-pointer rounded-lg border border-danger bg-danger/10 p-4 text-left text-danger transition-all"
                                    @click="onWrongAnswer"
                                >
                                    <div class="flex items-center">
                                        <span class="font-semibold">Wrong Answer</span>
                                    </div>
                                </button>

                                <!-- Family Feud: the Strike button, in the next open grid
                                     cell. Used for a wrong face-off answer, a wrong guess in
                                     play (3 strikes → steal), and a missed steal. -->
                                <button
                                    v-if="isFamilyFeud && ['faceoff', 'question', 'steal', 'reveal'].includes(phase)"
                                    type="button"
                                    :title="phase === 'faceoff' ? 'Wrong face-off answer' : (phase === 'question' ? 'Add a strike (wrong guess)' : 'Missed steal')"
                                    class="hover-glow cursor-pointer rounded-lg border border-danger bg-danger/10 p-4 text-left text-danger transition-all"
                                    @click="feudStrike"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold">Strike</span>
                                        <span v-if="phase === 'question'" class="text-lg font-black">{{ feudStrikes }}/{{ feudMaxStrikes }}</span>
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

        <!-- Score edit modal (opened by the scoreboard pencil) -->
        <Modal :show="showScoreModal" max-width="md" @close="showScoreModal = false">
            <div class="p-6">
                <h3 class="mb-4 text-xl font-bold text-body">Adjust Scores</h3>
                <div class="mb-6 space-y-3">
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface-inset p-3"
                    >
                        <div class="flex items-center gap-3">
                            <span class="font-semibold" :style="{ color: team.color }">{{ team.name }}</span>
                        </div>
                        <input
                            v-model="scoreEdits[team.id]"
                            type="number"
                            min="0"
                            class="w-28 rounded-lg border-border bg-surface-inset text-center text-xl font-bold text-body focus:border-primary focus:ring-primary"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <Button variant="ghost" size="md" @click="showScoreModal = false">Cancel</Button>
                    <Button variant="primary" size="md" @click="saveScores">Save Scores</Button>
                </div>
            </div>
        </Modal>

        <!-- Reset round confirm -->
        <Confirm
            :show="showResetRoundConfirm"
            title="Reset this round?"
            message="This clears every revealed answer and reverses the points (and any sweep bonus) earned this round. Scores from earlier rounds are not affected."
            confirm-text="Reset Round"
            variant="danger"
            @confirm="resetRound"
            @cancel="showResetRoundConfirm = false"
            @close="showResetRoundConfirm = false"
        />

        <!-- Time's up (end the primary's clock early) confirm -->
        <Confirm
            :show="showTimesUpConfirm"
            title="End the timer?"
            message="This ends the primary team's turn now and hands the board to the other team to steal — the same as the clock running out."
            confirm-text="End Timer"
            variant="danger"
            @confirm="confirmTimesUp"
            @cancel="showTimesUpConfirm = false"
            @close="showTimesUpConfirm = false"
        />

        <!-- End game confirm -->
        <Confirm
            :show="showEndConfirm"
            title="End game?"
            message="This ends the game for everyone and returns you to your games."
            confirm-text="End Game"
            variant="danger"
            @confirm="confirmEndGame"
            @cancel="showEndConfirm = false"
            @close="showEndConfirm = false"
        />
    </StandardLayout>
</template>
