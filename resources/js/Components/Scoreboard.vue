<script setup lang="ts">
interface TeamMember {
    id: number;
    display_name: string;
}

interface Team {
    id: number;
    name: string;
    color: string;
    total_score: number;
    members?: TeamMember[];
}

interface Props {
    teams: Team[];
    activeTeamId?: number | null;
    controllingTeamIds?: number[];
    playerTeamId?: number | null;
    showMembers?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showMembers: false,
});

const hasControl = (teamId: number): boolean => {
    return props.controllingTeamIds?.includes(teamId) ?? false;
};

const isPlayerTeam = (teamId: number): boolean => {
    return props.playerTeamId === teamId;
};
</script>

<template>
    <div class="bg-gray-900 rounded-lg p-4">
        <h3 class="text-white text-lg font-bold mb-4 text-center">Scoreboard</h3>
        <div class="space-y-3">
            <div
                v-for="team in teams"
                :key="team.id"
                class="rounded-lg transition-all duration-300"
                :class="{
                    'ring-2 ring-yellow-400 ring-offset-2 ring-offset-gray-900': activeTeamId === team.id,
                    'ring-2 ring-green-400 ring-offset-2 ring-offset-gray-900': !activeTeamId && isPlayerTeam(team.id),
                    'opacity-60': activeTeamId && activeTeamId !== team.id,
                }"
                :style="{ backgroundColor: team.color + '40' }"
            >
                <!-- Team Header -->
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-4 h-4 rounded-full"
                            :style="{ backgroundColor: team.color }"
                        ></div>
                        <span class="text-white font-semibold">{{ team.name }}</span>
                        <span
                            v-if="isPlayerTeam(team.id)"
                            class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full font-bold"
                        >
                            YOUR TEAM
                        </span>
                        <span
                            v-if="hasControl(team.id)"
                            class="text-xs bg-yellow-500 text-black px-2 py-0.5 rounded-full font-bold"
                        >
                            CONTROL
                        </span>
                    </div>
                    <span class="text-2xl font-bold text-white">{{ team.total_score }}</span>
                </div>

                <!-- Team Members -->
                <div
                    v-if="showMembers && team.members && team.members.length > 0"
                    class="px-3 pb-3"
                >
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="member in team.members"
                            :key="member.id"
                            class="text-xs bg-black/30 text-white/80 px-2 py-1 rounded-full"
                        >
                            {{ member.display_name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
