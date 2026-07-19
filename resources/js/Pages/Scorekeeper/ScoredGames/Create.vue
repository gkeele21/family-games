<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TemplateFormFields from '@/Components/Scorekeeper/TemplateFormFields.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

interface Template {
    id: number;
    name: string;
    target_score: number | null;
    low_score_wins: boolean;
    max_rounds: number | null;
    team_based: boolean;
    score_fields: Array<{ label: string; counts_toward_total: boolean }>;
}

const props = defineProps<{
    household: { id: number; name: string };
    templates: Template[];
    games: Array<{ id: number; name: string }>;
    players: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    game_template_id: props.templates[0]?.id ?? null,
    played_at: new Date().toISOString().slice(0, 10), // today; backdatable
    player_ids: [] as number[],
    teams: [] as Array<{ name: string; player_ids: number[] }>,
});

const selectedTemplate = computed(() =>
    props.templates.find((t) => t.id === form.game_template_id),
);
const isTeamBased = computed(() => !!selectedTemplate.value?.team_based);

const selectedPlayers = computed(() =>
    props.players.filter((p) => form.player_ids.includes(p.id)),
);

// --- Team builder (only over the players chosen to play) -------------------
const teams = ref<Array<{ id: number; name: string }>>([
    { id: 1, name: 'Team 1' },
    { id: 2, name: 'Team 2' },
]);
let nextTeamId = 3;
const assignment = reactive<Record<number, number | null>>({});
function ensureAssignments() {
    props.players.forEach((p) => {
        if (!(p.id in assignment)) assignment[p.id] = null;
    });
}
ensureAssignments();
watch(() => props.players, ensureAssignments, { deep: true });

const addTeam = () => {
    teams.value.push({ id: nextTeamId++, name: `Team ${teams.value.length + 1}` });
};
const removeTeam = (id: number) => {
    teams.value = teams.value.filter((t) => t.id !== id);
    props.players.forEach((p) => {
        if (assignment[p.id] === id) assignment[p.id] = null;
    });
};

const teamCounts = computed(() => {
    const counts: Record<number, number> = {};
    teams.value.forEach((t) => (counts[t.id] = 0));
    selectedPlayers.value.forEach((p) => {
        const tid = assignment[p.id];
        if (tid != null && tid in counts) counts[tid]++;
    });
    return counts;
});

const builtTeams = computed(() =>
    teams.value.map((t) => ({
        name: t.name,
        player_ids: selectedPlayers.value
            .filter((p) => assignment[p.id] === t.id)
            .map((p) => p.id),
    })),
);

const canSubmit = computed(() => {
    if (!form.game_template_id) return false;
    if (!isTeamBased.value) return form.player_ids.length >= 2;
    if (form.player_ids.length < 2 || teams.value.length < 2) return false;
    const everyoneAssigned = selectedPlayers.value.every(
        (p) => assignment[p.id] != null,
    );
    const everyTeamHasPlayer = teams.value.every(
        (t) => (teamCounts.value[t.id] ?? 0) >= 1,
    );
    return everyoneAssigned && everyTeamHasPlayer;
});

const submit = () => {
    if (isTeamBased.value) {
        form.teams = builtTeams.value;
        form.player_ids = [];
    } else {
        form.teams = [];
        form.player_ids = props.players
            .map((p) => p.id)
            .filter((id) => form.player_ids.includes(id));
    }
    form.post(route('scorekeeper.households.games.store', props.household.id));
};

// --- Inline add player (stays on this page) --------------------------------
const playerForm = useForm({ name: '' });
const addPlayer = () => {
    const existingIds = props.players.map((p) => p.id);
    playerForm.post(
        route('scorekeeper.households.players.store', props.household.id),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const players =
                    ((page.props as { players?: Array<{ id: number }> })
                        .players) ?? [];
                const created = players.find((p) => !existingIds.includes(p.id));
                if (created) form.player_ids.push(created.id); // auto-select
                playerForm.reset();
            },
        },
    );
};

// --- Inline template creation ---------------------------------------------
const showTemplateModal = ref(false);
const tplForm = useForm({
    name: '',
    game_type_id: null as number | null,
    new_game_name: null as string | null,
    target_score: null as number | null,
    low_score_wins: false,
    max_rounds: null as number | null,
    team_based: false,
    allow_self_scoring: false,
    is_global: false,
    score_fields: [{ label: 'Score', counts_toward_total: true, color: null }],
});

const openTemplateModal = () => {
    tplForm.reset();
    tplForm.clearErrors();
    showTemplateModal.value = true;
};

const createTemplate = () => {
    const existingIds = props.templates.map((t) => t.id);
    tplForm.post(
        route('scorekeeper.households.templates.store', props.household.id),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const templates =
                    ((page.props as { templates?: Array<{ id: number }> })
                        .templates) ?? [];
                const created = templates.find(
                    (t) => !existingIds.includes(t.id),
                );
                if (created) form.game_template_id = created.id;
                showTemplateModal.value = false;
                tplForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head title="New game" />

    <ScorekeeperLayout :household="household" tab="games">
        <div class="py-10">
            <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-semibold leading-tight text-[#0b5d3b]">
                    New game
                </h2>
                <form
                    class="space-y-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <!-- Template -->
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel for="template" value="Template" />
                            <button
                                type="button"
                                class="text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
                                @click="openTemplateModal"
                            >
                                + New game template
                            </button>
                        </div>
                        <select
                            id="template"
                            v-model="form.game_template_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="t in templates"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}{{ t.team_based ? ' (teams)' : '' }}
                            </option>
                        </select>
                        <InputError
                            class="mt-1"
                            :message="form.errors.game_template_id"
                        />
                    </div>

                    <!-- Play date (backdatable for games played earlier) -->
                    <div>
                        <InputLabel for="played-at" value="Play date" />
                        <input
                            id="played-at"
                            v-model="form.played_at"
                            type="date"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Recording a game from a while back? Set the date it
                            was actually played.
                        </p>
                        <InputError
                            class="mt-1"
                            :message="form.errors.played_at"
                        />
                    </div>

                    <!-- Who's playing (both modes) -->
                    <div>
                        <InputLabel value="Players (pick at least 2)" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="p in players"
                                :key="p.id"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="form.player_ids"
                                    type="checkbox"
                                    :value="p.id"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-gray-900">{{ p.name }}</span>
                            </label>
                            <p
                                v-if="players.length === 0"
                                class="text-sm text-gray-500"
                            >
                                No players in the roster yet — add one below.
                            </p>
                        </div>
                        <InputError
                            class="mt-1"
                            :message="form.errors.player_ids"
                        />

                        <!-- Inline add player -->
                        <div class="mt-3 flex items-end gap-2 border-t pt-3">
                            <div class="flex-1">
                                <InputLabel
                                    for="new-player"
                                    value="Add a player"
                                />
                                <TextInput
                                    id="new-player"
                                    v-model="playerForm.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Name"
                                    @keydown.enter.prevent="addPlayer"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="playerForm.errors.name"
                                />
                            </div>
                            <SecondaryButton
                                type="button"
                                :disabled="playerForm.processing"
                                @click="addPlayer"
                                >Add</SecondaryButton
                            >
                        </div>
                    </div>

                    <!-- Team assignment: only the selected players -->
                    <div v-if="isTeamBased" class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel value="Teams" />
                                <button
                                    type="button"
                                    class="text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
                                    @click="addTeam"
                                >
                                    + Add team
                                </button>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="team in teams"
                                    :key="team.id"
                                    class="flex items-center gap-3"
                                >
                                    <TextInput
                                        v-model="team.name"
                                        type="text"
                                        class="block w-full"
                                        placeholder="Team name"
                                    />
                                    <button
                                        v-if="teams.length > 2"
                                        type="button"
                                        class="shrink-0 text-sm text-red-600 hover:text-red-500"
                                        @click="removeTeam(team.id)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Assign selected players" />
                            <p
                                v-if="selectedPlayers.length === 0"
                                class="mt-2 text-sm text-gray-500"
                            >
                                Pick the players above, then assign each to a
                                team.
                            </p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="p in selectedPlayers"
                                    :key="p.id"
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-gray-900">{{
                                        p.name
                                    }}</span>
                                    <select
                                        v-model="assignment[p.id]"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">
                                            — Pick team —
                                        </option>
                                        <option
                                            v-for="team in teams"
                                            :key="team.id"
                                            :value="team.id"
                                        >
                                            {{ team.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <PrimaryButton :disabled="!canSubmit || form.processing"
                        >Start game</PrimaryButton
                    >
                </form>
            </div>
        </div>

        <!-- Inline "create template" so you never leave the New Game flow -->
        <Modal :show="showTemplateModal" @close="showTemplateModal = false">
            <form class="space-y-4 p-6" @submit.prevent="createTemplate">
                <h3 class="text-lg font-medium text-[#0b5d3b]">
                    New game template
                </h3>
                <TemplateFormFields
                    :form="tplForm"
                    :games="games"
                    id-prefix="modal"
                />
                <div class="flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        @click="showTemplateModal = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="tplForm.processing"
                        >Create &amp; use</PrimaryButton
                    >
                </div>
            </form>
        </Modal>
    </ScorekeeperLayout>
</template>
