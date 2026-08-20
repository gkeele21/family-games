<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import GameWordmark from '@/Components/GameWordmark.vue';
import GameBadge from '@/Components/GameBadge.vue';
import Modal from '@/Components/Base/Modal.vue';
import Button from '@/Components/Base/Button.vue';
import Select from '@/Components/Form/Select.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

interface ActiveGame {
    kind: 'trivia' | 'scorekeeper' | 'propoff';
    // True when the signed-in user is playing in it, rather than it merely
    // running in one of their households.
    is_mine: boolean;
    id: number;
    name: string | null;
    status: 'lobby' | 'playing' | 'paused' | 'scoring' | 'open' | 'locked' | 'in_progress';
    invite_code: string | null;
    code: string | null;
    competitor_count: number;
    team_based: boolean;
    game_type: { name: string; slug: string };
    updated_at: string;
}

interface Winner {
    name: string;
    color: string | null;
    score: number;
}

interface RecentGame {
    kind: 'trivia' | 'scorekeeper' | 'propoff';
    id: number;
    code: string | null;
    name: string;
    game_type: { name: string; slug: string };
    finished_at: string;
    player_count: number;
    players: string[];
    present_player_ids?: number[];
    winners: Winner[];
}

interface PendingInvite {
    token: string;
    household_name: string;
    inviter_name: string | null;
    role: string;
}

interface RosterPlayer { id: number; name: string }
interface AttendanceHousehold { id: number; name: string; players: RosterPlayer[] }

const props = defineProps<{
    activeGames: ActiveGame[];
    pendingInvites: PendingInvite[];
    recentGames: RecentGame[];
    attendanceRosters: AttendanceHousehold[];
}>();

// Top-menu links to the three game modules (same set as /games).
const navItems = [
    {
        label: 'Question Library',
        route: 'questions.index',
        activeMatch: ['questions.*'],
    },
];

// ---- join by code ----
const joinCode = ref('');
const join = () => {
    const code = joinCode.value.trim().toUpperCase();
    router.get(route('player.join'), code ? { code } : {});
};

// ---- "Who played" editor (post-hoc attendance on recent trivia games) ----
const playerName = new Map<number, string>();
props.attendanceRosters.forEach((h) => h.players.forEach((p) => playerName.set(p.id, p.name)));
const hasRoster = props.attendanceRosters.some((h) => h.players.length);

// Live attendance per game so edits reflect immediately without a page reload.
const attendanceState = ref<Record<number, number[]>>({});
props.recentGames.forEach((g) => {
    if (g.kind === 'trivia') attendanceState.value[g.id] = g.present_player_ids ?? [];
});

const canEditPlayers = (game: RecentGame) => game.kind === 'trivia' && hasRoster;
const displayPlayers = (game: RecentGame): string[] =>
    game.kind === 'trivia'
        ? (attendanceState.value[game.id] ?? []).map((id) => playerName.get(id)).filter((n): n is string => !!n)
        : game.players;
const displayCount = (game: RecentGame): number =>
    game.kind === 'trivia' ? (attendanceState.value[game.id] ?? []).length : game.player_count;

const editing = ref<RecentGame | null>(null);
const editHouseholdId = ref<number | null>(null);
const editPresent = ref<Set<number>>(new Set());
const saving = ref(false);

const householdOptions = computed(() =>
    props.attendanceRosters.map((h) => ({ value: h.id, label: `${h.name} (${h.players.length})` })),
);
const editRoster = computed<RosterPlayer[]>(() =>
    props.attendanceRosters.find((h) => h.id === editHouseholdId.value)?.players ?? [],
);
const allEditPresent = computed(() =>
    editRoster.value.length > 0 && editRoster.value.every((p) => editPresent.value.has(p.id)),
);

const openEditor = (game: RecentGame) => {
    editing.value = game;
    editPresent.value = new Set(attendanceState.value[game.id] ?? []);
    // Default to the household that already holds the attendees, else the first.
    editHouseholdId.value =
        props.attendanceRosters.find((h) => h.players.some((p) => editPresent.value.has(p.id)))?.id
        ?? props.attendanceRosters[0]?.id
        ?? null;
};
const toggleEdit = (playerId: number) => {
    const next = new Set(editPresent.value);
    next.has(playerId) ? next.delete(playerId) : next.add(playerId);
    editPresent.value = next;
};
const toggleAllEdit = () => {
    const next = new Set(editPresent.value);
    if (allEditPresent.value) editRoster.value.forEach((p) => next.delete(p.id));
    else editRoster.value.forEach((p) => next.add(p.id));
    editPresent.value = next;
};
const saveEditor = async () => {
    if (!editing.value) return;
    saving.value = true;
    const ids = [...editPresent.value];
    try {
        await axios.post(route('host.attendance', editing.value.id), { player_ids: ids });
        attendanceState.value = { ...attendanceState.value, [editing.value.id]: ids };
        editing.value = null;
    } finally {
        saving.value = false;
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'lobby':
            return { class: 'bg-warning/15 text-warning', text: 'In Lobby' };
        case 'playing':
            return { class: 'bg-success/15 text-success', text: 'Playing' };
        case 'paused':
            return { class: 'bg-warning/15 text-warning', text: 'Paused' };
        case 'scoring':
            return { class: 'bg-warning/15 text-warning', text: 'Scoring' };
        case 'open':
            return { class: 'bg-info/15 text-info', text: 'Open' };
        case 'locked':
            return { class: 'bg-danger/15 text-danger', text: 'Locked' };
        case 'in_progress':
            return { class: 'bg-success/15 text-success', text: 'In Progress' };
        case 'completed':
            return { class: 'bg-white/10 text-muted', text: 'Completed' };
        default:
            return { class: 'bg-white/10 text-muted', text: status };
    }
};

const formatDate = (dateString: string) =>
    new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

const getRecentGameLink = (game: RecentGame) => {
    if (game.kind === 'scorekeeper') return route('scorekeeper.games.show', game.id);
    if (game.kind === 'propoff') return route('propoff.play.leaderboard', game.code ?? '');
    return route('host.game', game.id);
};

const getActiveGameLink = (game: ActiveGame) => {
    if (game.kind === 'scorekeeper') return { href: route('scorekeeper.games.show', game.id), text: 'Score' };
    if (game.kind === 'propoff') return { href: route('propoff.play.hub', game.code ?? ''), text: 'Play' };
    return game.status === 'lobby'
        ? { href: route('host.lobby', game.id), text: 'Setup' }
        : { href: route('host.game', game.id), text: 'Resume' };
};
</script>

<template>
    <Head title="Dashboard" />

    <StandardLayout :nav-items="navItems">
        <div class="mx-auto max-w-[1440px] space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <!-- ===== Hub — what do you want to do ===== -->
            <div>
                <p class="eyebrow mb-3">Start something</p>
                <div class="hub">
                    <!-- Party Games -->
                    <div class="tile tile-party">
                        <GameWordmark first="PARTY" second="GAMES" color="rgb(var(--color-info))" />
                        <p class="desc">Host a live game-show battle on the big screen.</p>
                        <p class="cap">Includes</p>
                        <ul class="gamelist">
                            <li>
                                <GameBadge slug="america-says" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-text))">America Says</span>
                            </li>
                            <li>
                                <GameBadge slug="family-feud" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-warning))">Family Feud</span>
                            </li>
                            <li>
                                <GameBadge slug="oodles" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-danger))">Oodles</span>
                            </li>
                        </ul>
                        <Link :href="route('games.create')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                            </svg>
                            Host a Game
                        </Link>
                    </div>

                    <!-- Score Keeper -->
                    <div class="tile tile-score">
                        <GameWordmark first="SCORE" second="KEEPER" color="rgb(var(--color-danger))" />
                        <p class="desc">Keep a running score for any card or board game — players update from their phones.</p>
                        <Link :href="route('scorekeeper.home')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Keep Score
                        </Link>
                    </div>

                    <!-- PropOff -->
                    <div class="tile tile-prop">
                        <GameWordmark first="PROP" second="OFF" color="rgb(var(--color-success))" />
                        <p class="desc">Go head-to-head on prop-bet predictions. Set the questions, invite your group, crown a winner.</p>
                        <Link :href="route('propoff.home')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M8 5v14l11-7z" stroke-linejoin="round" />
                            </svg>
                            Play PropOff
                        </Link>
                    </div>

                    <!-- ShotMadness (coming soon) -->
                    <div class="tile tile-shot">
                        <GameWordmark first="SHOT" second="MADNESS" color="rgb(var(--color-warning))" />
                        <p class="desc">Bracket-style basketball showdown — call the shots, ride the upsets, and top the leaderboard.</p>
                        <span class="btn btn-static">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M3 12h18M12 3v18M5.6 5.6l12.8 12.8M18.4 5.6L5.6 18.4" stroke-linecap="round" />
                            </svg>
                            Coming Soon
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===== Join by code ===== -->
            <div class="join">
                <div>
                    <h3 class="text-lg font-extrabold text-body">Have a game code?</h3>
                    <p class="mt-0.5 text-sm text-muted">Drop into a game that's already in progress.</p>
                </div>
                <form class="ml-auto flex min-w-[280px] max-w-[420px] flex-1 gap-2.5" @submit.prevent="join">
                    <input
                        v-model="joinCode"
                        maxlength="6"
                        placeholder="GAME01"
                        aria-label="Game code"
                        class="code-input flex-1 rounded-xl border border-border bg-surface-inset px-4 py-3 text-center text-base font-bold uppercase tracking-[0.3em] text-body placeholder:tracking-[0.3em] placeholder:text-subtle focus:outline-none"
                    />
                    <button type="submit" class="btn-join">Join</button>
                </form>
            </div>

            <!-- ===== Pending invites ===== -->
            <div v-if="pendingInvites.length" class="overflow-hidden rounded-[18px] border border-info/30 bg-info/5">
                <div class="border-b border-info/30 px-6 py-4">
                    <h3 class="text-base font-semibold text-body">You've been invited</h3>
                </div>
                <div class="divide-y divide-info/20">
                    <div
                        v-for="invite in pendingInvites"
                        :key="invite.token"
                        class="flex items-center gap-4 px-6 py-4"
                    >
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-body">{{ invite.household_name }}</h4>
                            <div class="text-sm text-muted">
                                <template v-if="invite.inviter_name">{{ invite.inviter_name }} invited you</template>
                                <template v-else>Invitation pending</template>
                                <span> &middot; joins as {{ invite.role }}</span>
                            </div>
                        </div>
                        <Link :href="route('scorekeeper.invites.show', invite.token)" class="btn-resume">
                            View invite
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ===== Active now ===== -->
            <div v-if="activeGames.length" class="overflow-hidden rounded-[18px] border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-body">Active now</h3>
                </div>
                <div class="divide-y divide-border">
                    <div
                        v-for="game in activeGames"
                        :key="`${game.kind}-${game.id}`"
                        class="flex items-center gap-4 px-6 py-4 transition hover:bg-white/5"
                    >
                        <GameBadge :slug="game.game_type.slug" />
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                            <div class="flex items-center gap-2 text-sm text-muted">
                                <template v-if="game.invite_code">
                                    <span class="font-mono">{{ game.invite_code }}</span>
                                    <span>&middot;</span>
                                </template>
                                <span>{{ game.competitor_count }} {{ game.team_based ? 'teams' : 'players' }}</span>
                            </div>
                        </div>
                        <span
                            v-if="game.kind === 'scorekeeper' && game.is_mine"
                            class="rounded-full bg-primary/15 px-3 py-1 text-xs font-semibold text-primary"
                        >
                            You're in
                        </span>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="getStatusBadge(game.status).class">
                            {{ getStatusBadge(game.status).text }}
                        </span>
                        <Link :href="getActiveGameLink(game).href" class="btn-resume">
                            {{ getActiveGameLink(game).text }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ===== Recent games ===== -->
            <div v-if="recentGames.length" class="overflow-hidden rounded-[18px] border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-body">Recent games</h3>
                </div>
                <div class="divide-y divide-border">
                    <Link
                        v-for="game in recentGames"
                        :key="`${game.kind}-${game.id}`"
                        :href="getRecentGameLink(game)"
                        class="flex items-center gap-4 px-6 py-4 transition hover:bg-white/5"
                    >
                        <GameBadge :slug="game.game_type.slug" />
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                            <div class="truncate text-sm text-muted">
                                {{ formatDate(game.finished_at) }}
                                <template v-if="displayCount(game) > 0">
                                    &middot; {{ displayCount(game) }} {{ displayCount(game) === 1 ? 'player' : 'players' }}
                                </template>
                                <template v-if="displayPlayers(game).length"> &middot; {{ displayPlayers(game).join(', ') }}</template>
                            </div>
                        </div>
                        <button
                            v-if="canEditPlayers(game)"
                            type="button"
                            class="flex-none rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-muted transition hover:border-border-strong hover:text-body"
                            title="Edit who played"
                            @click.stop.prevent="openEditor(game)"
                        >
                            ✎ Players
                        </button>
                        <div class="whitespace-nowrap text-right text-sm">
                            <div v-if="game.winners.length" class="flex items-center gap-2">
                                <span class="text-lg text-gold">&#127942;</span>
                                <template v-for="(winner, index) in game.winners" :key="winner.name">
                                    <span v-if="index > 0" class="text-muted">&amp;</span>
                                    <span class="font-semibold" :style="winner.color ? { color: winner.color } : {}">
                                        {{ winner.name }}
                                    </span>
                                </template>
                                <span class="text-muted">({{ game.winners[0].score }} pts)</span>
                            </div>
                            <div v-else class="text-subtle">No winner</div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Who-played editor for a recent trivia game -->
        <Modal :show="editing !== null" max-width="lg" @close="editing = null">
            <div class="p-6">
                <h3 class="text-base font-semibold text-body">Who played?</h3>
                <p class="mt-0.5 text-sm text-muted">{{ editing?.name }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Select v-if="householdOptions.length > 1" v-model="editHouseholdId" :options="householdOptions" />
                    <span class="text-xs text-subtle">{{ editPresent.size }} selected</span>
                    <Button variant="muted" size="xs" class="ml-auto" @click="toggleAllEdit">{{ allEditPresent ? 'Clear all' : 'Select all' }}</Button>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="p in editRoster"
                        :key="p.id"
                        type="button"
                        :class="['rounded-full border px-3 py-1.5 text-sm transition', editPresent.has(p.id) ? 'border-primary bg-primary/10 text-body' : 'border-border bg-surface-inset text-muted hover:border-border-strong']"
                        @click="toggleEdit(p.id)"
                    >
                        <span v-if="editPresent.has(p.id)" class="mr-1 text-primary">✓</span>{{ p.name }}
                    </button>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <Button variant="secondary" size="md" @click="editing = null">Cancel</Button>
                    <Button variant="primary" size="md" :disabled="saving" @click="saveEditor">{{ saving ? 'Saving…' : 'Save' }}</Button>
                </div>
            </div>
        </Modal>
    </StandardLayout>
</template>

<style scoped>
.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.16em;
    font-size: 12px;
    font-weight: 700;
    color: rgb(var(--color-text-subtle));
}

/* ---- hub of neon game tiles ---- */
.hub {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
@media (max-width: 1080px) {
    .hub {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 560px) {
    .hub {
        grid-template-columns: 1fr;
    }
}

.tile {
    --ac: var(--color-success);
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 20px;
    padding: 26px 24px 24px;
    background: rgb(var(--color-surface));
    border: 1px solid rgb(var(--ac));
    box-shadow:
        0 0 0 1px rgb(var(--ac)),
        0 0 30px rgb(var(--ac) / 0.22);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.tile:hover {
    transform: translateY(-3px);
    box-shadow:
        0 0 0 1px rgb(var(--ac)),
        0 0 44px rgb(var(--ac) / 0.34);
}
.tile-party {
    --ac: var(--color-info);
}
.tile-score {
    --ac: var(--color-danger);
}
.tile-prop {
    --ac: var(--color-success);
}
.tile-shot {
    --ac: var(--color-warning);
}

.tile .desc {
    margin: 14px 0 0;
    color: rgb(var(--color-text-muted));
    font-size: 14.5px;
    line-height: 1.5;
}

.tile .cap {
    margin: 16px 0 9px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgb(var(--color-text-subtle));
}
.gamelist {
    list-style: none;
    margin: 0 0 4px;
    padding: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 16px;
}
.gamelist li {
    display: flex;
    align-items: center;
    gap: 9px;
}
.gname {
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
}

.btn {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 700;
    font-size: 14.5px;
    padding: 11px 18px;
    border-radius: 11px;
    background: rgb(var(--ac));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--ac));
    border-color: rgb(var(--ac));
}
.btn svg {
    width: 17px;
    height: 17px;
}
/* orange button that isn't clickable (ShotMadness "Coming Soon") */
.btn-static {
    cursor: default;
}
.btn-static:hover {
    background: rgb(var(--ac));
    color: #fff;
    border-color: transparent;
}

/* mt-auto needs a flex-grow above the pinned button so tiles keep equal button rows */
.tile > :is(.desc, .gamelist) {
    flex: 0 0 auto;
}

/* ---- join by code ---- */
.join {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    border-radius: 18px;
    padding: 22px 24px;
    background: rgb(var(--color-surface));
    border: 1px solid rgb(var(--color-border));
}
.code-input:focus {
    border-color: transparent;
    box-shadow:
        0 0 0 1px rgb(var(--color-text)),
        0 0 0 3px rgb(var(--color-info)),
        0 0 15px rgb(var(--color-info) / 0.3);
}
.btn-join {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14.5px;
    background: rgb(var(--color-info));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn-join:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--color-info));
    border-color: rgb(var(--color-info));
}

/* ---- list action button ---- */
.btn-resume {
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
    white-space: nowrap;
    background: rgb(var(--color-success));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn-resume:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--color-success));
    border-color: rgb(var(--color-success));
}
</style>
