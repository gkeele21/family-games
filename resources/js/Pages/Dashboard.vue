<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
}

interface GameType {
    id: number;
    name: string;
    slug: string;
}

interface GameSession {
    id: number;
    name: string | null;
    status: 'lobby' | 'playing' | 'paused' | 'completed';
    invite_code: string;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
    game_type: GameType;
    teams: Team[];
}

interface Props {
    activeGames: GameSession[];
    recentGames: GameSession[];
    stats: {
        totalGamesHosted: number;
        completedGames: number;
        favoriteGameType: string | null;
    };
}

defineProps<Props>();

const user = usePage().props.auth.user;

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'lobby':
            return { class: 'bg-gold/15 text-gold', text: 'In Lobby' };
        case 'playing':
            return { class: 'bg-success/15 text-success', text: 'Playing' };
        case 'paused':
            return { class: 'bg-warning/15 text-warning', text: 'Paused' };
        case 'completed':
            return { class: 'bg-white/10 text-muted', text: 'Completed' };
        default:
            return { class: 'bg-white/10 text-muted', text: status };
    }
};

const getGameTypeColor = (slug: string) => {
    switch (slug) {
        case 'family-feud':
            return 'from-red-500 to-orange-500';
        case 'america-says':
            return 'from-blue-500 to-indigo-600';
        case 'oodles':
            return 'from-green-500 to-teal-500';
        default:
            return 'from-gray-500 to-gray-600';
    }
};

const getGameTypeIcon = (slug: string) => {
    switch (slug) {
        case 'family-feud':
            return '&#128170;';
        case 'america-says':
            return '&#127479;&#127480;';
        case 'oodles':
            return '&#127922;';
        default:
            return '&#127918;';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const getWinner = (teams: Team[]) => {
    if (teams.length === 0) return null;
    return teams.reduce((a, b) => (a.total_score > b.total_score ? a : b));
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-body">Welcome back, {{ user.first_name }}!</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Link
                        :href="route('games.create')"
                        class="group flex items-center gap-4 rounded-2xl bg-gradient-to-br from-info to-primary p-6 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl"
                    >
                        <div class="text-4xl transition group-hover:scale-110">&#127918;</div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Host a Game</h3>
                            <p class="text-sm text-white/80">Start a new game session</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('player.join')"
                        class="group flex items-center gap-4 rounded-2xl bg-gradient-to-br from-success to-primary-hover p-6 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl"
                    >
                        <div class="text-4xl transition group-hover:scale-110">&#127881;</div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Join a Game</h3>
                            <p class="text-sm text-white/80">Enter a game code to play</p>
                        </div>
                    </Link>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-surface p-6">
                        <div class="grid h-12 w-12 place-items-center rounded-full bg-info/15 text-2xl">&#127942;</div>
                        <div>
                            <p class="text-sm text-muted">Games Hosted</p>
                            <p class="text-3xl font-extrabold tabular-nums text-body">{{ stats.totalGamesHosted }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-surface p-6">
                        <div class="grid h-12 w-12 place-items-center rounded-full bg-success/15 text-2xl">&#9989;</div>
                        <div>
                            <p class="text-sm text-muted">Completed</p>
                            <p class="text-3xl font-extrabold tabular-nums text-body">{{ stats.completedGames }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-surface p-6">
                        <div class="grid h-12 w-12 place-items-center rounded-full bg-gold/15 text-2xl">&#11088;</div>
                        <div>
                            <p class="text-sm text-muted">Favorite Game</p>
                            <p class="text-xl font-bold text-body">{{ stats.favoriteGameType || 'None yet' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Active Games -->
                <div class="overflow-hidden rounded-2xl border border-border bg-surface">
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h3 class="text-lg font-semibold text-body">Active Games</h3>
                        <Link :href="route('games.index')" class="text-sm font-semibold text-primary hover:underline">
                            View all
                        </Link>
                    </div>

                    <div v-if="activeGames.length > 0" class="divide-y divide-border">
                        <div
                            v-for="game in activeGames"
                            :key="game.id"
                            class="flex items-center justify-between px-6 py-4 transition hover:bg-white/5"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br text-2xl text-white"
                                    :class="getGameTypeColor(game.game_type.slug)"
                                    v-html="getGameTypeIcon(game.game_type.slug)"
                                ></div>
                                <div>
                                    <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                                    <div class="flex items-center gap-2 text-sm text-muted">
                                        <span class="font-mono">{{ game.invite_code }}</span>
                                        <span>&#183;</span>
                                        <span>{{ game.teams.length }} teams</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="getStatusBadge(game.status).class">
                                    {{ getStatusBadge(game.status).text }}
                                </span>
                                <Link
                                    :href="game.status === 'lobby' ? route('host.lobby', game.id) : route('host.game', game.id)"
                                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110"
                                >
                                    {{ game.status === 'lobby' ? 'Setup' : 'Resume' }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-12 text-center">
                        <div class="mb-4 text-4xl">&#127918;</div>
                        <p class="mb-4 text-muted">No active games</p>
                        <Link
                            :href="route('games.create')"
                            class="inline-block rounded-lg bg-primary px-6 py-2 font-semibold text-white transition hover:brightness-110"
                        >
                            Host a Game
                        </Link>
                    </div>
                </div>

                <!-- Recent Games -->
                <div v-if="recentGames.length > 0" class="overflow-hidden rounded-2xl border border-border bg-surface">
                    <div class="border-b border-border px-6 py-4">
                        <h3 class="text-lg font-semibold text-body">Recent Games</h3>
                    </div>
                    <div class="divide-y divide-border">
                        <div
                            v-for="game in recentGames"
                            :key="game.id"
                            class="flex items-center justify-between px-6 py-4 transition hover:bg-white/5"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br text-2xl text-white opacity-75"
                                    :class="getGameTypeColor(game.game_type.slug)"
                                    v-html="getGameTypeIcon(game.game_type.slug)"
                                ></div>
                                <div>
                                    <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                                    <div class="text-sm text-muted">{{ formatDate(game.completed_at || game.created_at) }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div v-if="getWinner(game.teams)" class="flex items-center gap-2">
                                    <span class="text-lg text-gold">&#127942;</span>
                                    <span class="font-semibold" :style="{ color: getWinner(game.teams)?.color }">
                                        {{ getWinner(game.teams)?.name }}
                                    </span>
                                    <span class="text-sm text-muted">({{ getWinner(game.teams)?.total_score }} pts)</span>
                                </div>
                                <div v-else class="text-sm text-subtle">No winner</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
