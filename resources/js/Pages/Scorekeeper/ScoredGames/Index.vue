<script setup lang="ts">
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    household: { id: number; name: string };
    games: Array<{
        id: number;
        name: string;
        game_type: string | null;
        is_complete: boolean;
        started_at: string | null;
        players: string[];
        winners: string[];
    }>;
}>();

const page = usePage();
</script>

<template>
    <Head title="Games" />

    <ScorekeeperLayout :household="household" tab="games">
        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <ul class="divide-y divide-gray-100">
                        <li v-for="g in games" :key="g.id">
                            <Link
                                :href="route('scorekeeper.games.show', g.id)"
                                class="block px-6 py-4 hover:bg-gray-50"
                            >
                                <span class="flex items-center justify-between">
                                    <span class="flex items-baseline gap-3">
                                        <span
                                            v-if="g.started_at"
                                            class="text-sm text-gray-500"
                                            >{{
                                                new Date(
                                                    `${g.started_at}T00:00:00`,
                                                ).toLocaleDateString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })
                                            }}</span>
                                        <span class="font-medium text-gray-900">{{
                                            g.name
                                        }}</span>
                                        <span
                                            v-if="
                                                g.game_type &&
                                                g.game_type !== g.name
                                            "
                                            class="text-sm text-gray-500"
                                            >{{ g.game_type }}</span
                                        >
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span
                                            v-if="g.winners.length"
                                            class="text-sm font-medium text-amber-600"
                                            >🏆
                                            {{ g.winners.join(', ') }}</span
                                        >
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                g.is_complete
                                                    ? 'bg-gray-100 text-gray-600'
                                                    : 'bg-[#d9f3e5] text-[#0b7a48]'
                                            "
                                            >{{
                                                g.is_complete
                                                    ? 'Completed'
                                                    : 'In progress'
                                            }}</span
                                        >
                                    </span>
                                </span>
                                <span
                                    v-if="g.players.length"
                                    class="mt-1 block text-sm text-gray-500"
                                >
                                    {{ g.players.length }}
                                    {{
                                        g.players.length === 1
                                            ? 'player'
                                            : 'players'
                                    }}
                                    · {{ g.players.join(', ') }}
                                </span>
                            </Link>
                        </li>
                        <li
                            v-if="games.length === 0"
                            class="px-6 py-8 text-center text-sm text-gray-500"
                        >
                            No games yet. Start one to keep score.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </ScorekeeperLayout>
</template>
