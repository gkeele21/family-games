<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import GamePicker from '@/Components/GamePicker.vue';
import Card from '@/Components/Base/Card.vue';
import Button from '@/Components/Base/Button.vue';
import Modal from '@/Components/Base/Modal.vue';
import TextField from '@/Components/Form/TextField.vue';
import Select from '@/Components/Form/Select.vue';
import NumberInput from '@/Components/Form/NumberInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

interface TeamMember {
    id: number;
    user_id: number | null;
    guest_name: string | null;
    is_captain: boolean;
    display_name: string;
}

interface Team {
    id: number;
    name: string;
    color: string;
    display_order: number;
    members: TeamMember[];
}

interface Friend {
    id: number;
    name: string;
    first_name: string;
    nickname: string | null;
}

interface WaitingPlayer {
    id: number;
    user_id: number | null;
    guest_name: string | null;
    display_name: string;
}

interface GameSession {
    id: number;
    name: string | null;
    status: string;
    invite_code: string;
    game_type: { name: string; slug: string };
    teams: Team[];
}

interface GameTypeOption {
    id: number;
    name: string;
    slug: string;
}

interface QuestionCategory { id: number; name: string; count: number }
interface BankQuestion { id: number; question_text: string; category: string | null; difficulty: string; answers_count: number }
interface QuestionData {
    categories: QuestionCategory[];
    stats: { total: number; by_difficulty: { easy: number; medium: number; hard: number } };
    bank: BankQuestion[];
}
interface QuestionSelection {
    mode: 'random' | 'hand_picked';
    filter: 'any' | 'category' | 'difficulty';
    category_ids: number[];
    difficulty: 'easy' | 'medium' | 'hard';
    question_ids: number[];
}

interface Props {
    gameSession: GameSession;
    config: Record<string, any>;
    friends: Friend[];
    waitingPlayers: WaitingPlayer[];
    gameTypes: GameTypeOption[];
    questionData: QuestionData | null;
}

const props = defineProps<Props>();

const gameSlug = computed(() => props.gameSession.game_type.slug);

// Switch the game in place (resets settings to the new game's defaults; teams kept).
const changeGame = (gt: GameTypeOption) => {
    if (gt.slug === props.gameSession.game_type.slug) return;
    router.post(route('games.game-type.update', props.gameSession.id), { game_type_id: gt.id }, {
        preserveScroll: true,
        preserveState: false,
    });
};

// ---- Confirm dialog (shared) ----
const confirmState = ref<{ show: boolean; title: string; message: string; confirmText: string; action: (() => void) | null }>({
    show: false, title: '', message: '', confirmText: 'Confirm', action: null,
});
const askConfirm = (opts: { title: string; message: string; confirmText?: string }, action: () => void) => {
    confirmState.value = { show: true, title: opts.title, message: opts.message, confirmText: opts.confirmText ?? 'Confirm', action };
};
const runConfirm = () => {
    const action = confirmState.value.action;
    confirmState.value.show = false;
    action?.();
};

// ---- Number of teams (reconciles to the target count) ----
const teamCountInput = ref(props.gameSession.teams.length);
watch(() => props.gameSession.teams.length, (n) => (teamCountInput.value = n));
const applyTeamCount = (n: number) => {
    const count = Math.max(1, Math.min(8, Number(n) || 1));
    teamCountInput.value = count;
    if (count === props.gameSession.teams.length) return;
    router.post(route('games.teams.count', props.gameSession.id), { count }, { preserveScroll: true });
};

// ---- Team rename (inline, save on blur/Enter) ----
const renameTeam = (team: Team, event: Event) => {
    const el = event.target as HTMLInputElement;
    const name = el.value.trim();
    if (!name) {
        el.value = team.name; // don't allow a blank name — revert
        return;
    }
    if (name === team.name) return; // unchanged
    router.patch(route('games.teams.update', [props.gameSession.id, team.id]), { name }, { preserveScroll: true });
};
const removeTeam = (team: Team) => {
    askConfirm(
        { title: 'Remove team?', message: `Remove "${team.name}"? Any members on it will be removed too.`, confirmText: 'Remove' },
        () => router.delete(route('games.teams.remove', [props.gameSession.id, team.id]), { preserveScroll: true }),
    );
};

// ---- Team members ----
const activeTeamId = ref<number | null>(null);
const showAddMemberModal = ref(false);
const addMemberType = ref<'guest' | 'friend' | 'waiting'>('guest');
const guestNameInput = ref('');
const selectedFriendId = ref<number | null>(null);
const selectedWaitingPlayerId = ref<number | null>(null);
const memberForm = useForm({
    type: 'guest' as 'guest' | 'friend' | 'session_player',
    guest_name: '',
    user_id: null as number | null,
    session_player_id: null as number | null,
});
const openAddMember = (teamId: number) => {
    activeTeamId.value = teamId;
    showAddMemberModal.value = true;
    addMemberType.value = 'guest';
    guestNameInput.value = '';
    selectedFriendId.value = null;
    selectedWaitingPlayerId.value = null;
};
const closeAddMemberModal = () => {
    showAddMemberModal.value = false;
    activeTeamId.value = null;
};
const addTeamMember = () => {
    if (!activeTeamId.value) return;
    if (addMemberType.value === 'guest') {
        memberForm.type = 'guest';
        memberForm.guest_name = guestNameInput.value;
        memberForm.user_id = null;
        memberForm.session_player_id = null;
    } else if (addMemberType.value === 'friend') {
        memberForm.type = 'friend';
        memberForm.guest_name = '';
        memberForm.user_id = selectedFriendId.value;
        memberForm.session_player_id = null;
    } else {
        memberForm.type = 'session_player';
        memberForm.guest_name = '';
        memberForm.user_id = null;
        memberForm.session_player_id = selectedWaitingPlayerId.value;
    }
    memberForm.post(route('games.teams.members.add', [props.gameSession.id, activeTeamId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddMemberModal();
            memberForm.reset();
        },
    });
};
const removeTeamMember = (team: Team, member: TeamMember) => {
    askConfirm(
        { title: 'Remove member?', message: `Remove "${member.display_name}" from ${team.name}?`, confirmText: 'Remove' },
        () => router.delete(route('games.teams.members.remove', [props.gameSession.id, team.id, member.id]), { preserveScroll: true }),
    );
};
const isFriendOnTeam = (friendId: number): boolean =>
    props.gameSession.teams.some((team) => team.members?.some((m) => m.user_id === friendId));
const availableFriends = computed(() => props.friends.filter((f) => !isFriendOnTeam(f.id)));
const addMemberDisabled = computed(() =>
    memberForm.processing
    || (addMemberType.value === 'guest' && !guestNameInput.value)
    || (addMemberType.value === 'friend' && !selectedFriendId.value)
    || (addMemberType.value === 'waiting' && !selectedWaitingPlayerId.value),
);

// ---- Settings (inline; saved via games.settings.update) ----
const settingsForm = useForm({
    name: props.gameSession.name ?? '',
    settings: { ...props.config } as Record<string, any>,
});
const justSaved = ref(false);
const saveSettings = (after?: () => void) => {
    settingsForm.patch(route('games.settings.update', props.gameSession.id), {
        preserveScroll: true,
        onSuccess: () => {
            justSaved.value = true;
            setTimeout(() => (justSaved.value = false), 2000);
            after?.();
        },
    });
};

// Auto-save name & settings shortly after they change (debounced), so there's
// no separate "Save" step — everything on this page persists on its own.
let saveTimer: ReturnType<typeof setTimeout> | null = null;
watch(
    () => settingsForm.data(),
    () => {
        if (!settingsForm.isDirty) return;
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            if (settingsForm.isDirty && !settingsForm.processing) saveSettings();
        }, 700);
    },
    { deep: true },
);

// ---- Rounds & scoring (America Says) ----
interface RoundScore { points_per_answer: number; bonus_points: number }
const teamsCount = computed(() => props.gameSession.teams.length || 1);
const answersPerQuestion = computed(() => Number(settingsForm.settings.answers_per_question) || 7);
const answerOptions = [
    { value: 5, label: '5' },
    { value: 6, label: '6' },
    { value: 7, label: '7' },
    { value: 8, label: '8' },
];
const strikeOptions = [
    { value: 2, label: '2' },
    { value: 3, label: '3' },
    { value: 4, label: '4' },
];
const rounds = computed<RoundScore[]>({
    get: () => (settingsForm.settings.round_scoring ?? []) as RoundScore[],
    set: (v) => (settingsForm.settings.round_scoring = v),
});
const totalQuestions = computed(() => rounds.value.length * teamsCount.value);
const sweptTotal = (r: RoundScore) => r.points_per_answer * answersPerQuestion.value + r.bonus_points;

// Number-of-rounds stepper (reconciles the round_scoring list to the target).
const roundCountInput = ref(rounds.value.length);
watch(() => rounds.value.length, (n) => (roundCountInput.value = n));
const applyRoundCount = (n: number) => {
    const target = Math.max(1, Math.min(8, Number(n) || 1));
    roundCountInput.value = target;
    if (target === rounds.value.length) return;
    const next = rounds.value.slice(0, target);
    while (next.length < target) {
        const last = next[next.length - 1];
        const pts = last ? last.points_per_answer + 100 : 100;
        next.push({ points_per_answer: pts, bonus_points: pts * 10 });
    }
    rounds.value = next;
};
const removeRound = (index: number) => {
    rounds.value = rounds.value.filter((_, i) => i !== index);
};

// ---- Question selection (America Says) ----
if (!settingsForm.settings.question_selection) {
    settingsForm.settings.question_selection = {
        mode: 'random', filter: 'any', category_ids: [], difficulty: 'medium', question_ids: [],
    } as QuestionSelection;
}
if (settingsForm.settings.final_round_enabled === undefined) {
    settingsForm.settings.final_round_enabled = true;
}
const qsel = computed(() => settingsForm.settings.question_selection as QuestionSelection);
const qCategories = computed(() => props.questionData?.categories ?? []);
const qBank = computed(() => props.questionData?.bank ?? []);
const qStats = computed(() => props.questionData?.stats ?? { total: 0, by_difficulty: { easy: 0, medium: 0, hard: 0 } });
const difficultyOptions = [
    { value: 'easy', label: 'Easy' },
    { value: 'medium', label: 'Medium' },
    { value: 'hard', label: 'Hard' },
];
const questionSearch = ref('');
const filteredBank = computed(() => {
    const s = questionSearch.value.trim().toLowerCase();
    return s ? qBank.value.filter((q) => q.question_text.toLowerCase().includes(s)) : qBank.value;
});
const toggleCategory = (id: number) => {
    const arr = qsel.value.category_ids;
    const i = arr.indexOf(id);
    if (i >= 0) arr.splice(i, 1); else arr.push(id);
};
const togglePick = (id: number) => {
    const arr = qsel.value.question_ids;
    const i = arr.indexOf(id);
    if (i >= 0) arr.splice(i, 1); else arr.push(id);
};
// Games whose questions are chosen in setup. Oodles is always random (card omitted).
const usesSelection = computed(() => ['america-says', 'family-feud'].includes(gameSlug.value));
const neededQuestions = computed(() =>
    gameSlug.value === 'family-feud'
        ? Number(settingsForm.settings.rounds_per_game ?? 4)
        : totalQuestions.value);
const matchCount = computed(() => {
    const sel = qsel.value;
    if (sel.filter === 'category') return qCategories.value.filter((c) => sel.category_ids.includes(c.id)).reduce((n, c) => n + c.count, 0);
    if (sel.filter === 'difficulty') return qStats.value.by_difficulty[sel.difficulty] ?? 0;
    return qStats.value.total;
});
const enoughRandom = computed(() => matchCount.value >= neededQuestions.value);
const enoughPicked = computed(() => qsel.value.question_ids.length >= neededQuestions.value);
// America Says final round: 4 slots pulled at random; needs that many extra in the bank.
const finalRoundCount = computed(() =>
    gameSlug.value === 'america-says' && settingsForm.settings.final_round_enabled
        ? Number(settingsForm.settings.final_round_questions ?? 4)
        : 0);
const finalReady = computed(() =>
    finalRoundCount.value === 0 || qStats.value.total >= neededQuestions.value + finalRoundCount.value);
const questionSelectionReady = computed(() => {
    if (!usesSelection.value) return true;
    const mainOk = qsel.value.mode === 'hand_picked' ? enoughPicked.value : enoughRandom.value;
    return mainOk && finalReady.value;
});
const tileClass = (on: boolean) => ['rounded-lg border p-3 text-left transition', on ? 'border-primary bg-primary/10' : 'border-border bg-surface-inset hover:border-border-strong'];
const pillClass = (on: boolean) => ['rounded-full border px-4 py-2 text-sm font-semibold', on ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface-inset text-muted'];
const catClass = (on: boolean) => ['inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm', on ? 'border-primary bg-primary/10 text-body' : 'border-border bg-surface-inset text-muted'];

// ---- Start / cancel ----
const startGame = () => {
    if (settingsForm.isDirty) saveSettings(() => router.post(route('games.start', props.gameSession.id)));
    else router.post(route('games.start', props.gameSession.id));
};
const cancelGame = () => {
    askConfirm(
        { title: 'Cancel game?', message: 'This deletes the setup and cannot be undone.', confirmText: 'Cancel game' },
        () => router.delete(route('games.destroy', props.gameSession.id)),
    );
};

// ---- Copy helpers ----
const codeCopied = ref(false);
const urlCopied = ref(false);
const displayUrlCopied = ref(false);
const joinUrl = computed(() => `${window.location.origin}/play?code=${props.gameSession.invite_code}`);
const displayUrl = computed(() => `${window.location.origin}/display/${props.gameSession.invite_code}`);
const copyInviteCode = () => {
    navigator.clipboard.writeText(props.gameSession.invite_code);
    codeCopied.value = true;
    setTimeout(() => (codeCopied.value = false), 2000);
};
const copyJoinUrl = () => {
    navigator.clipboard.writeText(joinUrl.value);
    urlCopied.value = true;
    setTimeout(() => (urlCopied.value = false), 2000);
};
const copyDisplayUrl = () => {
    navigator.clipboard.writeText(displayUrl.value);
    displayUrlCopied.value = true;
    setTimeout(() => (displayUrlCopied.value = false), 2000);
};
</script>

<template>
    <Head :title="`Set up — ${gameSession.game_type.name}`" />

    <StandardLayout sticky-header>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-body">Set up your game</h1>
                    <p class="mt-1 text-sm text-muted">Pick a game, name it, set teams &amp; scoring, then start.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="settingsForm.processing" class="text-sm text-muted">Saving…</span>
                    <span v-else-if="justSaved" class="text-sm text-primary">Saved ✓</span>
                    <Button variant="outline" size="md" @click="cancelGame">Cancel</Button>
                    <Button variant="success" size="md" :disabled="!gameSession.teams.length || !questionSelectionReady" @click="startGame">Start Game →</Button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Game & name -->
            <Card title="Game">
                <div class="mb-5">
                    <GamePicker :game-types="gameTypes" :model-value="gameSlug" @select="changeGame" />
                </div>
                <TextField v-model="settingsForm.name" label="Session name" placeholder="e.g., Sunday Family Night" />
            </Card>

            <!-- Teams -->
            <Card title="Teams">
                <template #headerActions>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted">Number of teams</span>
                        <div class="inline-flex items-center overflow-hidden rounded-lg border border-border">
                            <button type="button" class="h-9 w-9 bg-surface-overlay text-lg text-body hover:bg-surface-elevated" @click="applyTeamCount(teamCountInput - 1)">−</button>
                            <input
                                v-model.number="teamCountInput"
                                type="text"
                                inputmode="numeric"
                                aria-label="Number of teams"
                                class="w-12 border-x border-border bg-surface-inset py-1.5 text-center text-body focus:outline-none"
                                @change="applyTeamCount(teamCountInput)"
                            />
                            <button type="button" class="h-9 w-9 bg-surface-overlay text-lg text-body hover:bg-surface-elevated" @click="applyTeamCount(teamCountInput + 1)">+</button>
                        </div>
                    </div>
                </template>

                <p class="mb-4 text-xs text-subtle">Members optional — a team can play with just a name</p>

                <div class="space-y-3">
                    <div v-for="team in gameSession.teams" :key="team.id" class="rounded-lg border border-border">
                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="flex flex-1 items-center gap-3">
                                <input
                                    :value="team.name"
                                    type="text"
                                    aria-label="Team name"
                                    :style="{ color: team.color }"
                                    class="min-w-0 max-w-[16rem] flex-1 rounded bg-transparent px-2 py-1 text-lg font-semibold transition hover:bg-surface-inset focus:bg-surface-inset focus:outline-none focus:ring-1 focus:ring-primary"
                                    @change="renameTeam(team, $event)"
                                    @keyup.enter="($event.target as HTMLInputElement).blur()"
                                />
                                <span class="whitespace-nowrap text-sm text-muted">({{ team.members?.length || 0 }} members)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="secondary" size="xs" icon="plus" @click="openAddMember(team.id)">Members</Button>
                                <Button variant="danger" size="xs" @click="removeTeam(team)">Remove</Button>
                            </div>
                        </div>
                        <div v-if="team.members && team.members.length" class="flex flex-wrap gap-2 p-3">
                            <span v-for="member in team.members" :key="member.id" class="flex items-center gap-2 rounded-full bg-surface-inset px-3 py-1.5 text-sm text-body">
                                {{ member.display_name }}
                                <button class="text-muted hover:text-danger" @click="removeTeamMember(team, member)">&times;</button>
                            </span>
                        </div>
                    </div>

                    <div v-if="!gameSession.teams.length" class="py-6 text-center text-muted">No teams yet — use the control above.</div>
                </div>

                <div class="mt-4 border-t border-border pt-4">
                    <Toggle v-model="settingsForm.settings.allow_team_selection" label="Let players pick their own team when they join by code" />
                </div>
            </Card>

            <!-- Rounds & scoring (America Says) -->
            <Card v-if="gameSlug === 'america-says'" title="Rounds & scoring">
                <template #headerActions>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted">Rounds</span>
                        <div class="inline-flex items-center overflow-hidden rounded-lg border border-border">
                            <button type="button" class="h-9 w-9 bg-surface-overlay text-lg text-body hover:bg-surface-elevated" @click="applyRoundCount(roundCountInput - 1)">−</button>
                            <input
                                v-model.number="roundCountInput"
                                type="text"
                                inputmode="numeric"
                                aria-label="Number of rounds"
                                class="w-12 border-x border-border bg-surface-inset py-1.5 text-center text-body focus:outline-none"
                                @change="applyRoundCount(roundCountInput)"
                            />
                            <button type="button" class="h-9 w-9 bg-surface-overlay text-lg text-body hover:bg-surface-elevated" @click="applyRoundCount(roundCountInput + 1)">+</button>
                        </div>
                    </div>
                </template>

                <p class="mb-4 text-sm text-muted">
                    A round plays one question per team ({{ teamsCount }} {{ teamsCount === 1 ? 'team' : 'teams' }}). Each correct answer scores its round's points; sweep the whole board for the bonus.
                    <span class="text-body">{{ rounds.length }} rounds × {{ teamsCount }} = {{ totalQuestions }} questions.</span>
                </p>

                <div class="mb-5 grid max-w-md grid-cols-2 gap-4">
                    <Select v-model="settingsForm.settings.answers_per_question" :options="answerOptions" label="Answers / question" />
                    <NumberInput v-model="settingsForm.settings.control_timer_seconds" label="Control timer (s)" :min="10" :max="120" />
                </div>

                <label class="mb-2 block text-sm font-medium text-body">Points per round</label>
                <div class="space-y-2">
                    <div v-for="(round, i) in rounds" :key="i" class="flex flex-wrap items-end gap-x-4 gap-y-2 rounded-lg border border-border bg-surface-inset p-3">
                        <span class="mb-2 w-16 font-semibold text-body">Round {{ i + 1 }}</span>
                        <NumberInput v-model="round.points_per_answer" label="Pts / answer" :min="0" class="w-28" />
                        <NumberInput v-model="round.bonus_points" label="Sweep bonus" :min="0" class="w-28" />
                        <span class="mb-2 text-xs text-primary">Sweep all {{ answersPerQuestion }} = {{ sweptTotal(round).toLocaleString() }} pts</span>
                        <Button v-if="rounds.length > 1" variant="ghost" size="xs" class="mb-1 ml-auto !text-danger" @click="removeRound(i)">Remove</Button>
                    </div>
                </div>
            </Card>

            <!-- Questions (America Says & Family Feud) -->
            <Card v-if="usesSelection" title="Questions">
                <template #headerActions>
                    <span class="text-sm text-muted">{{ neededQuestions }} needed<span v-if="gameSlug === 'america-says'"> · {{ rounds.length }} × {{ teamsCount }}</span><span v-else> · {{ neededQuestions }} rounds</span></span>
                </template>

                <div class="mb-5 grid grid-cols-2 gap-3">
                    <button type="button" :class="tileClass(qsel.mode === 'random')" @click="qsel.mode = 'random'">
                        <div class="font-semibold text-body">🎲 Random</div>
                        <p class="mt-1 text-xs text-muted">Pull questions automatically when the game starts.</p>
                    </button>
                    <button type="button" :class="tileClass(qsel.mode === 'hand_picked')" @click="qsel.mode = 'hand_picked'">
                        <div class="font-semibold text-body">✋ Hand-Picked</div>
                        <p class="mt-1 text-xs text-muted">Choose exactly which questions play, in order.</p>
                    </button>
                </div>

                <!-- Random -->
                <div v-if="qsel.mode === 'random'">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-subtle">Pull from</p>
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button type="button" :class="pillClass(qsel.filter === 'any')" @click="qsel.filter = 'any'">Any</button>
                        <button type="button" :class="pillClass(qsel.filter === 'category')" @click="qsel.filter = 'category'">By Category</button>
                        <button type="button" :class="pillClass(qsel.filter === 'difficulty')" @click="qsel.filter = 'difficulty'">By Difficulty</button>
                    </div>

                    <div v-if="qsel.filter === 'category'" class="flex flex-wrap gap-2">
                        <button v-for="c in qCategories" :key="c.id" type="button" :class="catClass(qsel.category_ids.includes(c.id))" @click="toggleCategory(c.id)">
                            <span v-if="qsel.category_ids.includes(c.id)" class="text-primary">✓</span>
                            {{ c.name }} <span class="text-xs text-subtle">{{ c.count }}</span>
                        </button>
                        <p v-if="!qCategories.length" class="text-sm text-muted">No categories yet — add some in the Question Library.</p>
                    </div>

                    <div v-else-if="qsel.filter === 'difficulty'" class="max-w-[200px]">
                        <Select v-model="qsel.difficulty" :options="difficultyOptions" />
                    </div>

                    <div :class="['mt-4 flex items-center gap-3 rounded-lg border px-4 py-3 text-sm', enoughRandom ? 'border-primary/40 bg-surface-inset' : 'border-warning/50 bg-warning/5']">
                        <span :class="['h-2 w-2 flex-none rounded-full', enoughRandom ? 'bg-primary' : 'bg-warning']"></span>
                        <span v-if="enoughRandom" class="text-body"><b>{{ matchCount }}</b> questions match — enough for a {{ neededQuestions }}-question game.</span>
                        <span v-else class="text-body"><b>{{ matchCount }}</b> match — need {{ neededQuestions }}. Widen the filter or add questions.</span>
                        <span class="ml-auto whitespace-nowrap text-xs text-subtle">picks {{ neededQuestions }} at random</span>
                    </div>
                </div>

                <!-- Hand-Picked -->
                <div v-else>
                    <TextField v-model="questionSearch" placeholder="Search the bank…" class="mb-3" />
                    <div class="max-h-72 overflow-y-auto rounded-lg border border-border">
                        <button
                            v-for="q in filteredBank"
                            :key="q.id"
                            type="button"
                            :class="['flex w-full items-center gap-3 border-t border-border/60 px-3 py-2.5 text-left first:border-t-0', qsel.question_ids.includes(q.id) ? 'bg-primary/5' : 'hover:bg-surface-inset']"
                            @click="togglePick(q.id)"
                        >
                            <span :class="['flex h-5 w-5 flex-none items-center justify-center rounded border-2 text-xs font-bold', qsel.question_ids.includes(q.id) ? 'border-primary bg-primary text-surface-inset' : 'border-border-strong']">
                                <span v-if="qsel.question_ids.includes(q.id)">✓</span>
                            </span>
                            <span class="flex-1 text-sm text-body">{{ q.question_text }}</span>
                            <span class="whitespace-nowrap text-xs text-subtle">{{ q.category || '—' }} · {{ q.answers_count }}</span>
                        </button>
                        <p v-if="!filteredBank.length" class="px-3 py-6 text-center text-sm text-muted">No questions found.</p>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full border border-border bg-surface-inset">
                            <div class="h-full bg-primary" :style="{ width: Math.min(100, (qsel.question_ids.length / Math.max(1, neededQuestions)) * 100) + '%' }"></div>
                        </div>
                        <span :class="['whitespace-nowrap text-sm font-semibold', enoughPicked ? 'text-primary' : 'text-warning']">{{ qsel.question_ids.length }} / {{ neededQuestions }} selected</span>
                    </div>
                </div>

                <div v-if="gameSlug === 'america-says'" class="mt-5 border-t border-border pt-4">
                    <Toggle v-model="settingsForm.settings.final_round_enabled" label="Final round" />
                    <p class="mt-1 text-xs text-subtle">4 questions revealing the top 1 → 4 answers on one 60-second clock. Pulled at random from questions with enough answers.</p>
                    <p v-if="settingsForm.settings.final_round_enabled && !finalReady" class="mt-1 text-xs text-warning">Needs {{ neededQuestions + finalRoundCount }} active questions to fill the main game plus the final round — add more or widen the filter.</p>
                </div>

                <div class="mt-5 flex items-center gap-2 border-t border-border pt-4 text-sm">
                    <Link :href="route('questions.index')" class="font-semibold text-primary hover:underline">Manage question library →</Link>
                    <span class="text-subtle">Add or edit questions</span>
                </div>
            </Card>

            <!-- Family Feud rules -->
            <Card v-if="gameSlug === 'family-feud'" title="Rules">
                <div class="mb-4 max-w-xs">
                    <Select v-model="settingsForm.settings.max_strikes" :options="strikeOptions" label="Strikes before steal" />
                </div>
                <Toggle v-model="settingsForm.settings.fast_money_enabled" label="Enable Fast Money round" />
            </Card>

            <!-- Oodles rules -->
            <Card v-if="gameSlug === 'oodles'" title="Rules">
                <div class="grid max-w-md grid-cols-2 gap-4">
                    <NumberInput v-model="settingsForm.settings.cards_per_game" label="Cards per game" :min="1" :max="26" />
                    <NumberInput v-model="settingsForm.settings.control_timer_seconds" label="Timer (s)" :min="5" :max="120" />
                </div>
                <div class="mt-5 flex items-center gap-2 border-t border-border pt-4 text-sm">
                    <Link :href="route('questions.index')" class="font-semibold text-primary hover:underline">Manage question library →</Link>
                    <span class="text-subtle">Oodles cards are pulled at random. Add or edit questions.</span>
                </div>
            </Card>

            <!-- Share -->
            <Card title="Share & display">
                <label class="mb-1 block text-sm font-medium text-muted">Game code</label>
                <div class="mb-4 flex items-center gap-3">
                    <span class="rounded-lg border border-border bg-surface-inset px-6 py-2 font-mono text-2xl font-bold tracking-widest text-body">{{ gameSession.invite_code }}</span>
                    <Button variant="primary" size="md" @click="copyInviteCode">{{ codeCopied ? 'Copied!' : 'Copy code' }}</Button>
                </div>
                <label class="mb-1 block text-sm font-medium text-muted">Join link</label>
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex-1 truncate rounded-lg border border-border bg-surface-inset px-4 py-2 font-mono text-sm text-muted">{{ joinUrl }}</span>
                    <Button variant="muted" size="md" @click="copyJoinUrl">{{ urlCopied ? 'Copied!' : 'Copy' }}</Button>
                </div>
                <label class="mb-1 block text-sm font-medium text-muted">TV / projector display</label>
                <div class="flex items-center gap-3">
                    <span class="flex-1 truncate rounded-lg border border-info/30 bg-surface-inset px-4 py-2 font-mono text-sm text-info">{{ displayUrl }}</span>
                    <Button variant="secondary" size="md" @click="copyDisplayUrl">{{ displayUrlCopied ? 'Copied!' : 'Copy' }}</Button>
                </div>
            </Card>

        </div>

        <!-- Add member modal -->
        <Modal :show="showAddMemberModal" max-width="md" @close="closeAddMemberModal">
            <div class="p-6">
                <h3 class="mb-4 text-lg font-semibold text-body">Add team member</h3>
                <div class="mb-4 flex border-b border-border">
                    <button :class="['-mb-px border-b-2 px-4 py-2 text-sm font-medium', addMemberType === 'guest' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-body']" @click="addMemberType = 'guest'">Guest</button>
                    <button :class="['-mb-px border-b-2 px-4 py-2 text-sm font-medium', addMemberType === 'friend' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-body']" @click="addMemberType = 'friend'">Friends</button>
                    <button v-if="waitingPlayers.length" :class="['-mb-px border-b-2 px-4 py-2 text-sm font-medium', addMemberType === 'waiting' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-body']" @click="addMemberType = 'waiting'">Joined ({{ waitingPlayers.length }})</button>
                </div>

                <TextField v-if="addMemberType === 'guest'" v-model="guestNameInput" label="Guest name" placeholder="Enter a name" />
                <div v-else-if="addMemberType === 'friend'" class="space-y-2">
                    <div v-if="!availableFriends.length" class="py-4 text-center text-muted">No friends available.</div>
                    <div v-for="friend in availableFriends" :key="friend.id" :class="['cursor-pointer rounded-lg border p-3', selectedFriendId === friend.id ? 'border-primary bg-primary/10' : 'border-border hover:border-border-strong']" @click="selectedFriendId = friend.id">
                        <div class="font-medium text-body">{{ friend.nickname || friend.name }}</div>
                        <div v-if="friend.nickname" class="text-sm text-muted">{{ friend.name }}</div>
                    </div>
                </div>
                <div v-else class="space-y-2">
                    <div v-for="player in waitingPlayers" :key="player.id" :class="['cursor-pointer rounded-lg border p-3', selectedWaitingPlayerId === player.id ? 'border-primary bg-primary/10' : 'border-border hover:border-border-strong']" @click="selectedWaitingPlayerId = player.id">
                        <div class="font-medium text-body">{{ player.display_name }}</div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="ghost" size="md" @click="closeAddMemberModal">Cancel</Button>
                    <Button variant="primary" size="md" :disabled="addMemberDisabled" @click="addTeamMember">Add</Button>
                </div>
            </div>
        </Modal>

        <!-- Confirmations -->
        <Confirm
            :show="confirmState.show"
            :title="confirmState.title"
            :message="confirmState.message"
            :confirm-text="confirmState.confirmText"
            variant="danger"
            @confirm="runConfirm"
            @cancel="confirmState.show = false"
            @close="confirmState.show = false"
        />
    </StandardLayout>
</template>
