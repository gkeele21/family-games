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
    /** Host: clicking a team row hands control to that team. */
    selectable?: boolean;
    /** Host: show the pencil that opens the score-edit modal. */
    editable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showMembers: false,
    selectable: false,
    editable: false,
});

const emit = defineEmits<{
    (e: 'select-team', teamId: number): void;
    (e: 'edit-scores'): void;
}>();

const hasControl = (teamId: number): boolean => {
    return props.controllingTeamIds?.includes(teamId) ?? false;
};

const isPlayerTeam = (teamId: number): boolean => {
    return props.playerTeamId === teamId;
};

const onRowClick = (teamId: number) => {
    if (props.selectable) emit('select-team', teamId);
};
</script>

<template>
    <div class="rounded-lg border border-border bg-surface p-4">
        <div class="relative mb-4 flex items-center justify-center">
            <h3 class="text-lg font-bold text-body">Scoreboard</h3>
            <button
                v-if="editable"
                type="button"
                class="absolute right-0 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-warning transition-colors hover:bg-warning/10"
                title="Edit scores"
                @click="emit('edit-scores')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div
                v-for="team in teams"
                :key="team.id"
                class="rounded-lg border border-border bg-surface-inset transition-all duration-300"
                :class="[
                    {
                        'ring-2 ring-white shadow-[0_0_22px_2px_rgba(255,255,255,0.6)]': hasControl(team.id),
                        'ring-2 ring-white/70 ring-offset-2 ring-offset-surface': !hasControl(team.id) && activeTeamId === team.id,
                        'ring-2 ring-success ring-offset-2 ring-offset-surface': !hasControl(team.id) && !activeTeamId && isPlayerTeam(team.id),
                    },
                    selectable ? 'cursor-pointer hover-glow' : '',
                ]"
                @click="onRowClick(team.id)"
            >
                <!-- Team Header -->
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold" :style="{ color: team.color }">{{ team.name }}</span>
                        <span
                            v-if="isPlayerTeam(team.id)"
                            class="rounded-full bg-success px-2 py-0.5 text-xs font-bold text-white"
                        >
                            YOUR TEAM
                        </span>
                    </div>
                    <span class="text-2xl font-bold text-body">{{ team.total_score }}</span>
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
                            class="rounded-full bg-black/30 px-2 py-1 text-xs text-body/80"
                        >
                            {{ member.display_name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
