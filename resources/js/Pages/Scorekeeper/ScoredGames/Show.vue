<script setup lang="ts">
import AddPlayerControl from '@/Components/Scorekeeper/AddPlayerControl.vue';
import InvitePlayerControl from '@/Components/Scorekeeper/InvitePlayerControl.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

interface ScoreField {
    key: string;
    label: string;
    counts_toward_total: boolean;
    color?: string | null;
}
interface Competitor {
    id: number;
    name: string;
    display_order: number;
    members: Array<{ id: number; name: string; has_account: boolean }>;
}
interface Round {
    id: number;
    round_number: number;
    scores: Record<string, Record<string, number>>;
}

const props = defineProps<{
    game: {
        id: number;
        name: string;
        base_game_type: string | null;
        target_score: number | null;
        low_score_wins: boolean;
        max_rounds: number | null;
        is_complete: boolean;
        team_based: boolean;
        allow_self_scoring: boolean;
        started_at: string | null;
        score_fields: ScoreField[];
        household_id: number;
    };
    can: {
        score_all: boolean;
        self_competitor_id: number | null;
    };
    household: { id: number; name: string };
    competitors: Competitor[];
    availablePlayers: Array<{ id: number; name: string }>;
    rounds: Round[];
    totals: Record<string, number>;
    fieldSubtotals: Record<string, Record<string, number>>;
    standings: Array<{ competitor_id: number; name: string; total: number; rank: number }>;
    completionMet: boolean;
}>();

const page = usePage();

const fields = computed(() => props.game.score_fields);
const singleField = computed(() => fields.value.length === 1);

// Scorers edit everything; a guest playing in the game edits only their own
// column when the template allowed self-scoring.
const canEditCompetitor = (competitorId: number): boolean =>
    !props.game.is_complete &&
    (props.can.score_all ||
        (props.game.allow_self_scoring &&
            competitorId === props.can.self_competitor_id));
const isEditor = computed(
    () =>
        !props.game.is_complete &&
        (props.can.score_all ||
            (props.game.allow_self_scoring &&
                props.can.self_competitor_id != null)),
);
const countingKeys = computed(() =>
    fields.value.filter((f) => f.counts_toward_total).map((f) => f.key),
);

// This round's total for a competitor (sum of counting fields, from saved scores).
const roundTotal = (round: Round, competitorId: number): number =>
    countingKeys.value.reduce(
        (sum, key) => sum + (Number(round.scores?.[competitorId]?.[key]) || 0),
        0,
    );

// Size each score input to the biggest value that field deals with, so a
// 9,999-point game gets wide cells and a 9-point game gets narrow ones.
const digitsOf = (n: number) => Math.max(1, String(Math.trunc(Math.abs(n))).length);
// Tiers assume spinner-less inputs (see .no-spinner) — sized snugly: a
// 3-digit field fits exactly 999.
const widthForDigits = (d: number) =>
    d <= 2 ? 'w-10' : d === 3 ? 'w-12' : d === 4 ? 'w-14' : d === 5 ? 'w-16' : 'w-20';
const fieldWidths = computed<Record<string, string>>(() => {
    const digits: Record<string, number> = {};
    fields.value.forEach((f) => (digits[f.key] = 2));
    const consider = (key: string, raw: number | string | undefined) => {
        const v = Number(raw);
        if (!Number.isNaN(v) && v !== 0)
            digits[key] = Math.max(digits[key] ?? 2, digitsOf(v));
    };
    props.rounds.forEach((r) =>
        props.competitors.forEach((c) =>
            fields.value.forEach((f) => consider(f.key, r.scores?.[c.id]?.[f.key])),
        ),
    );
    Object.values(inputs).forEach((byComp) =>
        Object.values(byComp).forEach((byField) =>
            fields.value.forEach((f) => consider(f.key, byField[f.key])),
        ),
    );
    if (singleField.value && props.game.target_score != null)
        digits[fields.value[0].key] = Math.max(
            digits[fields.value[0].key],
            digitsOf(props.game.target_score),
        );

    const out: Record<string, string> = {};
    Object.entries(digits).forEach(([k, d]) => (out[k] = widthForDigits(d)));
    return out;
});

// Editable cells: inputs[roundId][competitorId][fieldKey]
const inputs = reactive<
    Record<number, Record<number, Record<string, number | string>>>
>({});
// Cells the user has typed into but not saved — protected from live refresh.
// Reactive so the Save button can enable itself when edits appear.
const dirty = reactive(new Set<string>());
const cellKey = (roundId: number, competitorId: number, fieldKey: string) =>
    `${roundId}:${competitorId}:${fieldKey}`;
const markDirty = (roundId: number, competitorId: number, fieldKey: string) =>
    dirty.add(cellKey(roundId, competitorId, fieldKey));
function sync() {
    props.rounds.forEach((r) => {
        if (!inputs[r.id]) inputs[r.id] = {};
        props.competitors.forEach((c) => {
            if (!inputs[r.id][c.id]) inputs[r.id][c.id] = {};
            fields.value.forEach((f) => {
                // Server values flow in unless the cell has unsaved local edits.
                if (
                    inputs[r.id][c.id][f.key] === undefined ||
                    !dirty.has(cellKey(r.id, c.id, f.key))
                ) {
                    inputs[r.id][c.id][f.key] = r.scores?.[c.id]?.[f.key] ?? '';
                }
            });
        });
    });
}
sync();
watch(() => props.rounds, sync, { deep: true });

// Live refresh: poll for other people's saves while the game is in progress.
const deleting = ref(false);
const REFRESH_MS = 5000;
let refreshTimer: ReturnType<typeof setInterval> | null = null;
const refresh = () => {
    if (
        deleting.value ||
        document.hidden ||
        props.game.is_complete ||
        saving.value
    )
        return;
    router.reload({
        only: [
            'rounds',
            'totals',
            'fieldSubtotals',
            'standings',
            'completionMet',
            'competitors',
            'availablePlayers',
            'game',
        ],
    });
};
onMounted(() => {
    refreshTimer = setInterval(refresh, REFRESH_MS);
});
onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});

const saving = ref(false);
const hasUnsaved = computed(() => dirty.size > 0);

const rules = computed(() => {
    const parts: string[] = [];
    if (props.game.target_score !== null)
        parts.push(`First to ${props.game.target_score}`);
    if (props.game.max_rounds !== null)
        parts.push(`${props.game.max_rounds} rounds`);
    parts.push(
        props.game.low_score_wins ? 'lowest score wins' : 'highest score wins',
    );
    if (props.game.team_based) parts.push('teams');
    return parts.join(' · ');
});

// Play date: displayed next to the rules, editable by scorers.
const playedOnLabel = computed(() =>
    props.game.started_at
        ? new Date(`${props.game.started_at}T00:00:00`).toLocaleDateString(
              undefined,
              { year: 'numeric', month: 'short', day: 'numeric' },
          )
        : '',
);
const editingDate = ref(false);
const dateForm = useForm({
    played_at: props.game.started_at ?? new Date().toISOString().slice(0, 10),
});
const startEditDate = () => {
    dateForm.played_at =
        props.game.started_at ?? new Date().toISOString().slice(0, 10);
    editingDate.value = true;
};
const saveDate = () => {
    dateForm.patch(route('scorekeeper.games.playdate.update', props.game.id), {
        preserveScroll: true,
        onSuccess: () => (editingDate.value = false),
    });
};

const winners = computed(() =>
    props.game.is_complete ? props.standings.filter((s) => s.rank === 1) : [],
);

const ordinal = (n: number): string => {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (suffixes[(v - 20) % 10] ?? suffixes[v] ?? suffixes[0]);
};

// One color per competitor column (cycles for large rosters).
const palette = [
    { body: '#eef2ff', head: '#c7d2fe', total: '#e0e7ff', text: '#3730a3' }, // indigo
    { body: '#ecfdf5', head: '#a7f3d0', total: '#d1fae5', text: '#065f46' }, // emerald
    { body: '#fffbeb', head: '#fde68a', total: '#fef3c7', text: '#8a4b0a' }, // amber
    { body: '#fff1f2', head: '#fecdd3', total: '#ffe4e6', text: '#9f1239' }, // rose
    { body: '#f0f9ff', head: '#bae6fd', total: '#e0f2fe', text: '#075985' }, // sky
    { body: '#f5f3ff', head: '#ddd6fe', total: '#ede9fe', text: '#5b21b6' }, // violet
    { body: '#f0fdfa', head: '#99f6e4', total: '#ccfbf1', text: '#115e59' }, // teal
    { body: '#fff7ed', head: '#fed7aa', total: '#ffedd5', text: '#9a3412' }, // orange
];
const colorAt = (i: number) =>
    palette[((i % palette.length) + palette.length) % palette.length];
const colorIndexFor = (competitorId: number) =>
    props.competitors.findIndex((c) => c.id === competitorId);
const membersFor = (competitorId: number): string =>
    props.competitors
        .find((c) => c.id === competitorId)
        ?.members.map((m) => m.name)
        .join(', ') ?? '';

// Players in this game without an account — invitable even after the game
// completes, so a finished game night can still turn into sign-ups.
const uninvitedMembers = computed(() =>
    props.competitors.flatMap((c) =>
        c.members
            .filter((m) => !m.has_account)
            .map((m) => ({ ...m, competitor: c.name })),
    ),
);

// Colors stay attached to a competitor (by its Manage position), not the column
// slot — so sorting the grid doesn't reshuffle anyone's color.
const colorFor = (competitorId: number) => colorAt(colorIndexFor(competitorId));

// View-only column sort. 'manage' = the order set in the Manage section.
// Field sorts use the MOST RECENT round's saved values (not the cumulative
// subtotal); Total sorts by the running total.
const sortBy = ref<string>('manage');
const latestRoundValues = (key: string): Record<number, number> => {
    // Walk back from the newest round to the last one with a saved value for
    // this field, so a just-added (unsaved) round doesn't sort everyone to 0.
    for (let i = props.rounds.length - 1; i >= 0; i--) {
        const r = props.rounds[i];
        const hasAny = props.competitors.some(
            (c) => r.scores?.[c.id]?.[key] != null,
        );
        if (hasAny) {
            const out: Record<number, number> = {};
            props.competitors.forEach(
                (c) => (out[c.id] = Number(r.scores?.[c.id]?.[key]) || 0),
            );
            return out;
        }
    }
    return {};
};
const orderedCompetitors = computed(() => {
    if (sortBy.value === 'manage') return props.competitors;

    if (sortBy.value === 'total') {
        // Leader first, respecting lowest-wins. Ties keep the set order
        // (sort is stable).
        const dir = props.game.low_score_wins ? 1 : -1;
        return [...props.competitors].sort(
            (a, b) =>
                ((props.totals[a.id] ?? 0) - (props.totals[b.id] ?? 0)) * dir,
        );
    }

    // Field sort: latest round's values, highest first. Ties follow the order
    // set at game start, rotated to begin at the field's OVERALL leader.
    const key = sortBy.value;
    const latest = latestRoundValues(key);
    const overall = (c: Competitor) => props.fieldSubtotals[c.id]?.[key] ?? 0;

    let anchor = 0;
    props.competitors.forEach((c, i) => {
        if (overall(c) > overall(props.competitors[anchor])) anchor = i;
    });
    const setIndex = new Map(props.competitors.map((c, i) => [c.id, i]));
    const rotated = (c: Competitor) => {
        const i = setIndex.get(c.id) ?? 0;
        return (i - anchor + props.competitors.length) % props.competitors.length;
    };

    return [...props.competitors].sort(
        (a, b) =>
            (latest[b.id] ?? 0) - (latest[a.id] ?? 0) ||
            rotated(a) - rotated(b),
    );
});

// --- Mid-game roster editing ----------------------------------------------
type AddPayload = {
    player_id?: number;
    new_player_name?: string;
    add_to_household?: boolean;
};
const newTeamName = ref('');

const addPlayer = (payload: AddPayload) => {
    router.post(
        route('scorekeeper.games.competitors.store', props.game.id),
        payload,
        { preserveScroll: true },
    );
};
const addTeam = () => {
    if (!newTeamName.value.trim()) return;
    router.post(
        route('scorekeeper.games.competitors.store', props.game.id),
        { name: newTeamName.value },
        { preserveScroll: true, onSuccess: () => (newTeamName.value = '') },
    );
};
const addMemberToTeam = (competitorId: number, payload: AddPayload) => {
    router.post(
        route('scorekeeper.games.competitors.members.add', [
            props.game.id,
            competitorId,
        ]),
        payload,
        { preserveScroll: true },
    );
};
const removeCompetitor = (competitorId: number, label: string) => {
    if (!confirm(`Remove ${label} from the game? Their scores are discarded.`))
        return;
    router.delete(
        route('scorekeeper.games.competitors.destroy', [
            props.game.id,
            competitorId,
        ]),
        { preserveScroll: true },
    );
};
const removeMember = (competitorId: number, playerId: number) => {
    router.delete(
        route('scorekeeper.games.competitors.members.remove', [
            props.game.id,
            competitorId,
            playerId,
        ]),
        { preserveScroll: true },
    );
};
const applyOrder = (ids: number[]) => {
    router.post(
        route('scorekeeper.games.competitors.reorder', props.game.id),
        { competitor_ids: ids },
        { preserveScroll: true },
    );
};
const moveCompetitor = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= props.competitors.length) return;
    const ids = props.competitors.map((c) => c.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    applyOrder(ids);
};

// Drag-and-drop reordering (handle-initiated so inner inputs stay usable).
const dragIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const onDragStart = (index: number, event: DragEvent) => {
    dragIndex.value = index;
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};
const onDragOver = (index: number) => {
    dragOverIndex.value = index;
};
const onDrop = (toIndex: number) => {
    const from = dragIndex.value;
    dragIndex.value = null;
    dragOverIndex.value = null;
    if (from === null || from === toIndex) return;
    const ids = props.competitors.map((c) => c.id);
    const [moved] = ids.splice(from, 1);
    ids.splice(toIndex, 0, moved);
    applyOrder(ids);
};
const onDragEnd = () => {
    dragIndex.value = null;
    dragOverIndex.value = null;
};
const isDropTarget = (index: number) =>
    dragOverIndex.value === index &&
    dragIndex.value !== null &&
    dragIndex.value !== index;

const addRound = () => {
    router.post(
        route('scorekeeper.games.rounds.add', props.game.id),
        {},
        { preserveScroll: true },
    );
};

// Tab moves through all of a player's fields before advancing to the next
// player (the DOM's natural order is the opposite — across players per
// field). After a round's last cell, Tab continues into the next round; after
// the final round it lands on the Save button.
const onScoreTab = (
    e: KeyboardEvent,
    roundId: number,
    competitorId: number,
    fieldKey: string,
) => {
    const cells = orderedCompetitors.value
        .filter((c) => canEditCompetitor(c.id))
        .flatMap((c) => fields.value.map((f) => ({ c: c.id, f: f.key })));
    const idx = cells.findIndex(
        (cell) => cell.c === competitorId && cell.f === fieldKey,
    );
    if (idx === -1) return;

    const next = e.shiftKey ? cells[idx - 1] : cells[idx + 1];
    let target: HTMLElement | null = null;
    if (next) {
        target = document.querySelector<HTMLElement>(
            `[data-cell="${roundId}:${next.c}:${next.f}"]`,
        );
    } else if (!e.shiftKey) {
        const ri = props.rounds.findIndex((r) => r.id === roundId);
        const nextRound = props.rounds[ri + 1];
        target =
            nextRound && cells[0]
                ? document.querySelector<HTMLElement>(
                      `[data-cell="${nextRound.id}:${cells[0].c}:${cells[0].f}"]`,
                  )
                : document.querySelector<HTMLElement>('[data-save-all]');
    }
    if (target) {
        e.preventDefault();
        target.focus();
    }
};

const saveAll = () => {
    const rounds: Record<
        number,
        Record<number, Record<string, number | null>>
    > = {};
    props.rounds.forEach((r) => {
        rounds[r.id] = {};
        props.competitors.forEach((c) => {
            if (!canEditCompetitor(c.id)) return; // guests submit only their own
            rounds[r.id][c.id] = {};
            fields.value.forEach((f) => {
                const v = inputs[r.id]?.[c.id]?.[f.key];
                rounds[r.id][c.id][f.key] =
                    v === '' || v === null || v === undefined ? null : Number(v);
            });
        });
    });
    saving.value = true;
    router.patch(
        route('scorekeeper.games.scores.update', props.game.id),
        { rounds },
        {
            preserveScroll: true,
            onFinish: () => (saving.value = false),
            // Saved — server is the source of truth again.
            onSuccess: () => dirty.clear(),
        },
    );
};

const completeGame = () => {
    if (!confirm('Complete this game? No more scores can be entered.')) return;
    router.post(
        route('scorekeeper.games.complete', props.game.id),
        {},
        { preserveScroll: true },
    );
};

const deleteGame = () => {
    if (
        !confirm(
            'Delete this game? It will be discarded permanently, along with its rounds and scores.',
        )
    )
        return;
    // Pause the live-refresh poll: once the game is deleted server-side, a
    // poll against this page would 404 before the redirect lands.
    deleting.value = true;
    router.delete(route('scorekeeper.games.destroy', props.game.id), {
        onError: () => (deleting.value = false),
    });
};
</script>

<template>
    <Head :title="game.name" />

    <ScorekeeperLayout :household="household" tab="games">
        <div class="py-8">
            <div class="space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex items-baseline gap-3">
                    <Link
                        :href="
                            route(
                                'scorekeeper.households.games.index',
                                game.household_id,
                            )
                        "
                        class="text-sm text-gray-500 hover:text-gray-700"
                        >&larr; Games</Link
                    >
                    <h2
                        class="text-xl font-semibold leading-tight text-[#0b5d3b]"
                    >
                        {{ game.name }}
                    </h2>
                    <span class="text-sm text-gray-500">{{ rules }}</span>
                    <span
                        class="flex items-center gap-1.5 text-sm text-gray-500"
                    >
                        <template v-if="!editingDate">
                            <span v-if="playedOnLabel">· {{ playedOnLabel }}</span>
                            <button
                                v-if="can.score_all"
                                type="button"
                                class="text-gray-400 hover:text-[#0b5d3b]"
                                title="Edit play date"
                                @click="startEditDate"
                            >
                                ✎
                            </button>
                        </template>
                        <template v-else>
                            <input
                                v-model="dateForm.played_at"
                                type="date"
                                class="rounded-md border-gray-300 bg-white text-gray-900 py-0.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <button
                                type="button"
                                class="font-medium text-[#0b5d3b] hover:text-[#084a2f] disabled:opacity-40"
                                :disabled="dateForm.processing"
                                @click="saveDate"
                            >
                                Save
                            </button>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-600"
                                @click="editingDate = false"
                            >
                                Cancel
                            </button>
                        </template>
                    </span>
                </div>
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Winner banner -->
                <div
                    v-if="game.is_complete"
                    class="rounded-md border border-[#e7d8a8] bg-[#f8edc9] p-4 text-[#6b5407]"
                >
                    <span class="font-semibold">
                        {{ winners.length > 1 ? 'Tie:' : 'Winner:' }}
                    </span>
                    {{ winners.map((w) => w.name).join(', ') }}
                    <span v-if="winners.length">({{ winners[0].total }})</span>
                </div>

                <!-- Completion suggestion -->
                <div
                    v-else-if="completionMet"
                    class="flex items-center justify-between rounded-md bg-amber-50 p-4 text-amber-900"
                >
                    <span>A completion condition has been met.</span>
                    <PrimaryButton v-if="can.score_all" @click="completeGame"
                        >Complete game</PrimaryButton
                    >
                </div>

                <!-- Standings -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <h3
                        class="border-b px-6 py-3 text-lg font-medium text-[#0b5d3b]"
                    >
                        Standings
                    </h3>
                    <ol class="divide-y divide-gray-100">
                        <li
                            v-for="s in standings"
                            :key="s.competitor_id"
                            class="flex items-center gap-3 px-6 py-3"
                        >
                            <span
                                class="inline-flex min-w-[2.75rem] justify-center rounded-full px-2 py-0.5 text-sm font-semibold"
                                :class="
                                    s.rank === 1
                                        ? 'bg-[#f2d27c] text-[#5b4708]'
                                        : 'bg-gray-100 text-gray-600'
                                "
                                >{{ ordinal(s.rank) }}</span
                            >
                            <span
                                class="h-3 w-3 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor: colorAt(
                                        colorIndexFor(s.competitor_id),
                                    ).head,
                                }"
                            ></span>
                            <span
                                class="w-36 truncate font-medium text-gray-900"
                                >{{ s.name }}</span
                            >
                            <span
                                class="w-14 text-right font-semibold tabular-nums text-indigo-900"
                                >{{ s.total }}</span
                            >
                            <span
                                v-if="
                                    game.team_based &&
                                    membersFor(s.competitor_id)
                                "
                                class="text-sm text-gray-500"
                                >{{ membersFor(s.competitor_id) }}</span
                            >
                        </li>
                    </ol>
                </div>

                <!-- Score grid -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div
                        v-if="rounds.length"
                        class="flex items-center gap-3 border-b px-4 py-2 text-sm"
                    >
                        <span
                            v-if="!game.is_complete"
                            class="flex items-center gap-1.5 text-xs text-gray-400"
                            title="Scores refresh automatically"
                        >
                            <span
                                class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                            ></span>
                            Live
                        </span>
                        <label for="sort-by" class="text-gray-500">Sort by</label>
                        <select
                            id="sort-by"
                            v-model="sortBy"
                            class="rounded-md border-gray-300 bg-white text-gray-900 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="manage">Order set in Manage</option>
                            <option value="total">Total</option>
                            <template v-if="!singleField">
                                <option
                                    v-for="f in fields"
                                    :key="f.key"
                                    :value="f.key"
                                >
                                    {{ f.label }} (latest round)
                                </option>
                            </template>
                        </select>
                        <PrimaryButton
                            v-if="isEditor"
                            class="ml-auto"
                            data-save-all
                            :disabled="saving || !hasUnsaved"
                            @click="saveAll"
                            >{{ saving ? 'Saving…' : 'Save scores' }}</PrimaryButton
                        >
                    </div>
                    <div class="max-h-[70vh] overflow-auto overscroll-x-contain">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr>
                                    <th
                                        class="sticky top-0 z-10 bg-white px-3 py-3 text-left font-medium text-gray-500"
                                    >
                                        Rd
                                    </th>
                                    <th
                                        v-if="!singleField"
                                        class="sticky top-0 z-10 bg-white px-2 py-3"
                                    ></th>
                                    <th
                                        v-for="c in orderedCompetitors"
                                        :key="c.id"
                                        class="sticky top-0 z-10 px-2 py-3 text-left font-medium"
                                        :style="{
                                            backgroundColor: colorFor(c.id).head,
                                            color: colorFor(c.id).text,
                                        }"
                                    >
                                        <div>{{ c.name }}</div>
                                        <div
                                            v-if="game.team_based"
                                            class="text-xs font-normal opacity-80"
                                        >
                                            {{ c.members.map((m) => m.name).join(', ') }}
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template v-for="r in rounds" :key="r.id">
                                <!-- Single field: one row per round -->
                                <tr v-if="singleField">
                                    <td
                                        class="px-3 py-2 align-top font-medium text-gray-700"
                                    >
                                        {{ r.round_number }}
                                    </td>
                                    <td
                                        v-for="c in orderedCompetitors"
                                        :key="c.id"
                                        class="px-2 py-2 align-top"
                                        :style="{
                                            backgroundColor: colorFor(c.id).body,
                                        }"
                                    >
                                        <input
                                            v-if="canEditCompetitor(c.id)"
                                            v-model="
                                                inputs[r.id][c.id][
                                                    fields[0].key
                                                ]
                                            "
                                            type="number"
                                            @input="
                                                markDirty(
                                                    r.id,
                                                    c.id,
                                                    fields[0].key,
                                                )
                                            "
                                            class="no-spinner rounded-md border-gray-300 bg-white text-gray-900 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :class="fieldWidths[fields[0].key]"
                                        />
                                        <span v-else class="text-gray-900">{{
                                            r.scores?.[c.id]?.[
                                                fields[0].key
                                            ] ?? '—'
                                        }}</span>
                                    </td>
                                </tr>

                                <!-- Multi field: pad layout — labels once on
                                     the left, one sub-row per field -->
                                <template v-else>
                                    <tr v-for="(f, fi) in fields" :key="f.key">
                                        <td
                                            v-if="fi === 0"
                                            :rowspan="fields.length"
                                            class="px-3 py-2 align-top font-medium text-gray-700"
                                        >
                                            {{ r.round_number }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-2 py-1"
                                        >
                                            <span
                                                class="flex items-center gap-1.5 text-sm text-gray-500"
                                            >
                                                <span
                                                    v-if="f.color"
                                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                                    :style="{
                                                        backgroundColor:
                                                            f.color,
                                                    }"
                                                ></span>
                                                {{ f.label }}
                                            </span>
                                        </td>
                                        <td
                                            v-for="c in orderedCompetitors"
                                            :key="c.id"
                                            class="px-2 py-1"
                                            :style="{
                                                backgroundColor:
                                                    colorFor(c.id).body,
                                            }"
                                        >
                                            <input
                                                v-if="canEditCompetitor(c.id)"
                                                v-model="
                                                    inputs[r.id][c.id][f.key]
                                                "
                                                type="number"
                                                :data-cell="`${r.id}:${c.id}:${f.key}`"
                                                @input="
                                                    markDirty(r.id, c.id, f.key)
                                                "
                                                @keydown.tab="
                                                    onScoreTab(
                                                        $event,
                                                        r.id,
                                                        c.id,
                                                        f.key,
                                                    )
                                                "
                                                class="no-spinner rounded-md border-gray-300 bg-white text-gray-900 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                :class="fieldWidths[f.key]"
                                            />
                                            <span v-else class="text-gray-900">{{
                                                r.scores?.[c.id]?.[f.key] ?? '—'
                                            }}</span>
                                        </td>
                                    </tr>
                                    <tr class="text-sm">
                                        <td
                                            colspan="2"
                                            class="px-3 py-1 font-medium text-gray-500"
                                        >
                                            Round total
                                        </td>
                                        <td
                                            v-for="c in orderedCompetitors"
                                            :key="c.id"
                                            class="px-2 py-1 font-semibold"
                                            :style="{
                                                backgroundColor:
                                                    colorFor(c.id).body,
                                                color: colorFor(c.id).text,
                                            }"
                                        >
                                            {{ roundTotal(r, c.id) }}
                                        </td>
                                    </tr>
                                </template>
                                </template>
                                <tr
                                    v-if="rounds.length === 0"
                                    class="text-gray-500"
                                >
                                    <td
                                        :colspan="
                                            competitors.length +
                                            (singleField ? 1 : 2)
                                        "
                                        class="px-4 py-6 text-center"
                                    >
                                        No rounds yet — add the first round to
                                        start scoring.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td
                                        :colspan="singleField ? 1 : 2"
                                        class="border-t-2 border-gray-200 px-3 py-3 align-top font-semibold text-gray-700"
                                    >
                                        Total
                                    </td>
                                    <td
                                        v-for="c in orderedCompetitors"
                                        :key="c.id"
                                        class="border-t-2 border-white px-2 py-3 align-top"
                                        :style="{
                                            backgroundColor: colorFor(c.id).total,
                                            color: colorFor(c.id).text,
                                        }"
                                    >
                                        <div class="font-bold">
                                            {{ totals[c.id] ?? 0 }}
                                        </div>
                                        <div
                                            v-if="!singleField"
                                            class="text-xs opacity-70"
                                        >
                                            <span
                                                v-for="(f, i) in fields"
                                                :key="f.key"
                                                >{{ f.label }}
                                                {{
                                                    fieldSubtotals[c.id]?.[
                                                        f.key
                                                    ] ?? 0
                                                }}<span
                                                    v-if="i < fields.length - 1"
                                                    >, </span
                                                ></span
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div
                        v-if="isEditor"
                        class="flex items-center border-t px-4 py-3"
                    >
                        <PrimaryButton
                            v-if="can.score_all"
                            @click="addRound"
                            >Add round</PrimaryButton
                        >
                        <PrimaryButton
                            class="ml-auto"
                            :disabled="saving || !hasUnsaved"
                            @click="saveAll"
                            >{{
                                saving ? 'Saving…' : 'Save scores'
                            }}</PrimaryButton
                        >
                    </div>
                </div>

                <!-- Manage players / teams (in progress only) -->
                <div
                    v-if="!game.is_complete && can.score_all"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <h3
                        class="border-b px-6 py-3 text-lg font-medium text-[#0b5d3b]"
                    >
                        {{ game.team_based ? 'Manage teams' : 'Manage players' }}
                    </h3>

                    <!-- Team-based -->
                    <div
                        v-if="game.team_based"
                        class="space-y-3 px-6 py-4"
                    >
                        <div
                            v-for="(c, ci) in competitors"
                            :key="c.id"
                            class="rounded-md border border-gray-200 p-3"
                            :class="{ 'ring-2 ring-indigo-400': isDropTarget(ci) }"
                            @dragover.prevent="onDragOver(ci)"
                            @drop.prevent="onDrop(ci)"
                            @dragend="onDragEnd"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex items-center gap-2 font-medium text-gray-900"
                                >
                                    <span
                                        class="cursor-grab select-none text-gray-400 active:cursor-grabbing"
                                        draggable="true"
                                        title="Drag to reorder"
                                        @dragstart="onDragStart(ci, $event)"
                                        >⠿</span
                                    >
                                    <span
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor: colorAt(ci).head,
                                        }"
                                    ></span>
                                    {{ c.name }}
                                </span>
                                <div class="flex items-center gap-3">
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            class="rounded border border-gray-200 px-1.5 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            :disabled="ci === 0"
                                            title="Move up"
                                            @click="moveCompetitor(ci, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-gray-200 px-1.5 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            :disabled="ci === competitors.length - 1"
                                            title="Move down"
                                            @click="moveCompetitor(ci, 1)"
                                        >
                                            ↓
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-sm text-red-600 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="competitors.length <= 2"
                                        @click="removeCompetitor(c.id, c.name)"
                                    >
                                        Remove team
                                    </button>
                                </div>
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li
                                    v-for="m in c.members"
                                    :key="m.id"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-gray-700">{{
                                        m.name
                                    }}</span>
                                    <span class="flex items-center gap-3">
                                        <InvitePlayerControl
                                            v-if="!m.has_account"
                                            :household-id="household.id"
                                            :player-id="m.id"
                                        />
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="c.members.length <= 1"
                                            @click="removeMember(c.id, m.id)"
                                        >
                                            Remove
                                        </button>
                                    </span>
                                </li>
                            </ul>
                            <div class="mt-2">
                                <AddPlayerControl
                                    :available-players="availablePlayers"
                                    @add="(p) => addMemberToTeam(c.id, p)"
                                />
                            </div>
                        </div>

                        <div class="flex items-end gap-2 border-t pt-3">
                            <div class="flex-1">
                                <InputLabel for="new-team" value="Add a team" />
                                <TextInput
                                    id="new-team"
                                    v-model="newTeamName"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Team name"
                                />
                            </div>
                            <SecondaryButton
                                type="button"
                                :disabled="!newTeamName.trim()"
                                @click="addTeam"
                                >Add team</SecondaryButton
                            >
                        </div>
                    </div>

                    <!-- Individual -->
                    <div v-else class="space-y-3 px-6 py-4">
                        <ul class="space-y-1">
                            <li
                                v-for="(c, ci) in competitors"
                                :key="c.id"
                                class="flex items-center justify-between rounded text-sm"
                                :class="{ 'ring-2 ring-indigo-400': isDropTarget(ci) }"
                                @dragover.prevent="onDragOver(ci)"
                                @drop.prevent="onDrop(ci)"
                                @dragend="onDragEnd"
                            >
                                <span
                                    class="flex items-center gap-2 text-gray-800"
                                >
                                    <span
                                        class="cursor-grab select-none text-gray-400 active:cursor-grabbing"
                                        draggable="true"
                                        title="Drag to reorder"
                                        @dragstart="onDragStart(ci, $event)"
                                        >⠿</span
                                    >
                                    <span
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor: colorAt(ci).head,
                                        }"
                                    ></span>
                                    {{ c.name }}
                                </span>
                                <div class="flex items-center gap-3">
                                    <InvitePlayerControl
                                        v-if="
                                            c.members[0] &&
                                            !c.members[0].has_account
                                        "
                                        :household-id="household.id"
                                        :player-id="c.members[0].id"
                                    />
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            class="rounded border border-gray-200 px-1.5 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            :disabled="ci === 0"
                                            title="Move up"
                                            @click="moveCompetitor(ci, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-gray-200 px-1.5 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            :disabled="ci === competitors.length - 1"
                                            title="Move down"
                                            @click="moveCompetitor(ci, 1)"
                                        >
                                            ↓
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="competitors.length <= 2"
                                        @click="removeCompetitor(c.id, c.name)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        </ul>
                        <div class="border-t pt-3">
                            <AddPlayerControl
                                :available-players="availablePlayers"
                                label="Add a player"
                                @add="addPlayer"
                            />
                        </div>
                    </div>
                </div>

                <!-- Invite players from a completed game -->
                <div
                    v-if="
                        game.is_complete &&
                        can.score_all &&
                        uninvitedMembers.length
                    "
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-medium text-[#0b5d3b]">
                            Invite players
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            These players don't have accounts yet — invite them
                            and they'll see this game (and their scores) when
                            they sign up.
                        </p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="m in uninvitedMembers"
                            :key="m.id"
                            class="flex items-center justify-between gap-3 px-6 py-3"
                        >
                            <span class="text-gray-900">
                                {{ m.name }}
                                <span
                                    v-if="game.team_based"
                                    class="text-sm text-gray-400"
                                    >· {{ m.competitor }}</span
                                >
                            </span>
                            <InvitePlayerControl
                                :household-id="household.id"
                                :player-id="m.id"
                            />
                        </li>
                    </ul>
                </div>

                <!-- Actions (scorekeepers only) -->
                <div v-if="can.score_all" class="flex items-center gap-3">
                    <SecondaryButton
                        v-if="!game.is_complete"
                        @click="completeGame"
                        >Complete game</SecondaryButton
                    >
                    <DangerButton class="ml-auto" @click="deleteGame"
                        >Delete game</DangerButton
                    >
                </div>
            </div>
        </div>
    </ScorekeeperLayout>
</template>

<style>
/* Hide the native number-input spinners on score cells to save width. */
.no-spinner::-webkit-outer-spin-button,
.no-spinner::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
