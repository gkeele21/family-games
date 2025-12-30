<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface TeamMember {
    id: number;
    display_name: string;
}

interface Team {
    id: number;
    name: string;
    color: string;
    members: TeamMember[];
    member_count: number;
}

interface Props {
    gameSession: {
        id: number;
        name: string;
        invite_code: string;
    };
    teams: Team[];
    teamSize: number; // 0 = unlimited
    playerName: string;
}

const props = defineProps<Props>();

const form = useForm({
    team_id: null as number | null,
});

const submit = () => {
    if (!form.team_id) return;
    form.post(route('player.join.team', props.gameSession.id));
};

const canJoinTeam = (team: Team): boolean => {
    if (props.teamSize === 0) return true; // Unlimited
    return team.member_count < props.teamSize;
};

const getTeamStatus = (team: Team): string => {
    if (props.teamSize === 0) return '';
    const spots = props.teamSize - team.member_count;
    if (spots === 0) return 'Full';
    return `${spots} spot${spots !== 1 ? 's' : ''} left`;
};

const refreshPage = () => {
    globalThis.location.reload();
};
</script>

<template>
    <Head title="Select Team" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-purple-900 p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-lg">
            <!-- Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ gameSession.name }}</h1>
                <p class="text-gray-500 mt-1">
                    Game Code: <span class="font-mono font-bold">{{ gameSession.invite_code }}</span>
                </p>
                <p class="text-gray-600 mt-2">
                    Welcome, <span class="font-semibold">{{ playerName }}</span>!
                </p>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-center text-gray-700 mb-4">Choose Your Team</h2>

                <div v-if="teams.length === 0" class="text-center py-8 text-gray-500">
                    <p>No teams have been created yet.</p>
                    <p class="text-sm mt-2">Wait for the host to add teams, then refresh this page.</p>
                    <button
                        @click="refreshPage"
                        class="mt-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        Refresh
                    </button>
                </div>

                <div v-else class="space-y-3">
                    <button
                        v-for="team in teams"
                        :key="team.id"
                        @click="canJoinTeam(team) ? form.team_id = team.id : null"
                        :disabled="!canJoinTeam(team)"
                        :class="[
                            'w-full p-4 rounded-lg border-2 text-left transition-all',
                            form.team_id === team.id
                                ? 'ring-2 ring-offset-2'
                                : '',
                            canJoinTeam(team)
                                ? 'cursor-pointer hover:shadow-md'
                                : 'opacity-50 cursor-not-allowed',
                        ]"
                        :style="{
                            borderColor: team.color,
                            backgroundColor: form.team_id === team.id ? team.color + '20' : 'white',
                            '--tw-ring-color': team.color,
                        }"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold"
                                    :style="{ backgroundColor: team.color }"
                                >
                                    {{ team.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-800">{{ team.name }}</div>
                                    <div class="text-sm text-gray-500">
                                        {{ team.member_count }} member{{ team.member_count !== 1 ? 's' : '' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span
                                    v-if="getTeamStatus(team)"
                                    :class="[
                                        'text-sm font-medium',
                                        canJoinTeam(team) ? 'text-green-600' : 'text-red-500'
                                    ]"
                                >
                                    {{ getTeamStatus(team) }}
                                </span>
                                <div
                                    v-if="form.team_id === team.id"
                                    class="mt-1 text-sm font-medium"
                                    :style="{ color: team.color }"
                                >
                                    Selected
                                </div>
                            </div>
                        </div>

                        <!-- Team members preview -->
                        <div v-if="team.members && team.members.length > 0" class="mt-3 pt-3 border-t border-gray-100">
                            <div class="text-xs text-gray-500 mb-1">Team members:</div>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="member in team.members"
                                    :key="member.id"
                                    class="text-xs bg-gray-100 px-2 py-1 rounded-full"
                                >
                                    {{ member.display_name }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>

                <button
                    v-if="teams.length > 0"
                    @click="submit"
                    :disabled="form.processing || !form.team_id"
                    class="w-full mt-6 py-4 bg-blue-600 text-white text-xl font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    Join Team
                </button>
            </div>
        </div>
    </div>
</template>
