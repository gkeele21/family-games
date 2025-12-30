<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface GameType {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
}

interface GameSession {
    id: number;
    name: string | null;
    status: 'lobby' | 'playing' | 'paused' | 'completed';
    invite_code: string;
    created_at: string;
    started_at: string | null;
    game_type: GameType;
    teams: Team[];
}

interface Props {
    sessions: GameSession[];
    gameTypes: GameType[];
}

defineProps<Props>();

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'lobby':
            return 'bg-yellow-100 text-yellow-800';
        case 'playing':
            return 'bg-green-100 text-green-800';
        case 'paused':
            return 'bg-orange-100 text-orange-800';
        case 'completed':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="My Games" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    My Games
                </h2>
                <Link
                    :href="route('games.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    New Game
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="sessions.length === 0" class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <div class="text-6xl mb-4">&#127918;</div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No games yet</h3>
                    <p class="text-gray-600 mb-6">Create your first game session to get started!</p>
                    <Link
                        :href="route('games.create')"
                        class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Create New Game
                    </Link>
                </div>

                <!-- Game Sessions List -->
                <div v-else class="space-y-4">
                    <div
                        v-for="session in sessions"
                        :key="session.id"
                        class="bg-white shadow-sm sm:rounded-lg p-6"
                    >
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ session.name || session.game_type.name }}
                                    </h3>
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full capitalize"
                                        :class="getStatusBadgeClass(session.status)"
                                    >
                                        {{ session.status }}
                                    </span>
                                </div>

                                <div class="text-sm text-gray-600 space-y-1">
                                    <p>
                                        <span class="font-medium">Game Type:</span>
                                        {{ session.game_type.name }}
                                    </p>
                                    <p>
                                        <span class="font-medium">Code:</span>
                                        <span class="font-mono">{{ session.invite_code }}</span>
                                    </p>
                                    <p>
                                        <span class="font-medium">Teams:</span>
                                        {{ session.teams.length > 0 ? session.teams.map(t => t.name).join(', ') : 'No teams yet' }}
                                    </p>
                                    <p>
                                        <span class="font-medium">Created:</span>
                                        {{ formatDate(session.created_at) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Link
                                    v-if="session.status === 'lobby'"
                                    :href="route('host.lobby', session.id)"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                >
                                    Continue Setup
                                </Link>
                                <Link
                                    v-else-if="session.status === 'playing' || session.status === 'paused'"
                                    :href="route('host.game', session.id)"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                                >
                                    Resume Game
                                </Link>
                                <Link
                                    v-else
                                    :href="route('host.game', session.id)"
                                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition"
                                >
                                    View Results
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
