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
            return { class: 'bg-yellow-100 text-yellow-800', text: 'In Lobby' };
        case 'playing':
            return { class: 'bg-green-100 text-green-800', text: 'Playing' };
        case 'paused':
            return { class: 'bg-orange-100 text-orange-800', text: 'Paused' };
        case 'completed':
            return { class: 'bg-gray-100 text-gray-800', text: 'Completed' };
        default:
            return { class: 'bg-gray-100 text-gray-800', text: status };
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
    return teams.reduce((a, b) => a.total_score > b.total_score ? a : b);
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Welcome back, {{ user.first_name }}!
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Link
                        :href="route('games.create')"
                        class="group bg-gradient-to-br from-purple-600 to-indigo-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-4">
                            <div class="text-4xl group-hover:animate-bounce">&#127918;</div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Host a Game</h3>
                                <p class="text-white/80 text-sm">Start a new game session</p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="route('player.join')"
                        class="group bg-gradient-to-br from-teal-500 to-cyan-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-4">
                            <div class="text-4xl group-hover:animate-bounce">&#127881;</div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Join a Game</h3>
                                <p class="text-white/80 text-sm">Enter a game code to play</p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-2xl">&#127942;</span>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Games Hosted</p>
                                <p class="text-3xl font-bold text-gray-900">{{ stats.totalGamesHosted }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-2xl">&#9989;</span>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Completed</p>
                                <p class="text-3xl font-bold text-gray-900">{{ stats.completedGames }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <span class="text-2xl">&#11088;</span>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Favorite Game</p>
                                <p class="text-xl font-bold text-gray-900">{{ stats.favoriteGameType || 'None yet' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Games -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Active Games</h3>
                            <Link
                                :href="route('games.index')"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                View all
                            </Link>
                        </div>
                    </div>

                    <div v-if="activeGames.length > 0" class="divide-y divide-gray-100">
                        <div
                            v-for="game in activeGames"
                            :key="game.id"
                            class="p-4 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-12 h-12 rounded-lg bg-gradient-to-br flex items-center justify-center text-white text-2xl"
                                        :class="getGameTypeColor(game.game_type.slug)"
                                        v-html="getGameTypeIcon(game.game_type.slug)"
                                    ></div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">
                                            {{ game.name || game.game_type.name }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-sm text-gray-500">
                                            <span class="font-mono">{{ game.invite_code }}</span>
                                            <span>&#183;</span>
                                            <span>{{ game.teams.length }} teams</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-medium"
                                        :class="getStatusBadge(game.status).class"
                                    >
                                        {{ getStatusBadge(game.status).text }}
                                    </span>
                                    <Link
                                        :href="game.status === 'lobby' ? route('host.lobby', game.id) : route('host.game', game.id)"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition"
                                    >
                                        {{ game.status === 'lobby' ? 'Setup' : 'Resume' }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-12 text-center">
                        <div class="text-4xl mb-4">&#127918;</div>
                        <p class="text-gray-500 mb-4">No active games</p>
                        <Link
                            :href="route('games.create')"
                            class="inline-block px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                        >
                            Host a Game
                        </Link>
                    </div>
                </div>

                <!-- Recent Completed Games -->
                <div v-if="recentGames.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Games</h3>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="game in recentGames"
                            :key="game.id"
                            class="p-4 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-12 h-12 rounded-lg bg-gradient-to-br flex items-center justify-center text-white text-2xl opacity-75"
                                        :class="getGameTypeColor(game.game_type.slug)"
                                        v-html="getGameTypeIcon(game.game_type.slug)"
                                    ></div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">
                                            {{ game.name || game.game_type.name }}
                                        </h4>
                                        <div class="text-sm text-gray-500">
                                            {{ formatDate(game.completed_at || game.created_at) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div v-if="getWinner(game.teams)" class="flex items-center gap-2">
                                        <span class="text-yellow-500 text-lg">&#127942;</span>
                                        <span class="font-semibold" :style="{ color: getWinner(game.teams)?.color }">
                                            {{ getWinner(game.teams)?.name }}
                                        </span>
                                        <span class="text-gray-500 text-sm">
                                            ({{ getWinner(game.teams)?.total_score }} pts)
                                        </span>
                                    </div>
                                    <div v-else class="text-gray-400 text-sm">
                                        No winner
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
