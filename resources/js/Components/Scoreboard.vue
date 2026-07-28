<script setup lang="ts">
import { ref } from 'vue';

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
    editable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showMembers: false,
    editable: false,
});

const emit = defineEmits<{
    (e: 'update-score', teamId: number, newScore: number): void;
}>();

const editingTeamId = ref<number | null>(null);
const editScore = ref<string>('');

const hasControl = (teamId: number): boolean => {
    return props.controllingTeamIds?.includes(teamId) ?? false;
};

const isPlayerTeam = (teamId: number): boolean => {
    return props.playerTeamId === teamId;
};

const startEditing = (team: Team) => {
    if (!props.editable) return;
    editingTeamId.value = team.id;
    editScore.value = String(team.total_score);
};

const saveScore = () => {
    if (editingTeamId.value !== null) {
        const newScore = parseInt(editScore.value, 10);
        if (!isNaN(newScore) && newScore >= 0) {
            emit('update-score', editingTeamId.value, newScore);
        }
        cancelEditing();
    }
};

const cancelEditing = () => {
    editingTeamId.value = null;
    editScore.value = '';
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        saveScore();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
};
</script>

<template>
    <div class="rounded-lg border border-border bg-surface p-4">
        <h3 class="mb-4 text-center text-lg font-bold text-body">Scoreboard</h3>
        <div class="space-y-3">
            <div
                v-for="team in teams"
                :key="team.id"
                class="rounded-lg border transition-all duration-300"
                :class="{
                    'ring-2 ring-white/70 ring-offset-2 ring-offset-surface': activeTeamId === team.id,
                    'ring-2 ring-success ring-offset-2 ring-offset-surface': !activeTeamId && isPlayerTeam(team.id),
                    'opacity-60': activeTeamId && activeTeamId !== team.id,
                }"
                :style="{ backgroundColor: team.color + '22', borderColor: team.color + '80' }"
            >
                <!-- Team Header -->
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-4 w-4 rounded-full"
                            :style="{ backgroundColor: team.color }"
                        ></div>
                        <span class="font-semibold text-body">{{ team.name }}</span>
                        <span
                            v-if="isPlayerTeam(team.id)"
                            class="rounded-full bg-success px-2 py-0.5 text-xs font-bold text-white"
                        >
                            YOUR TEAM
                        </span>
                        <span
                            v-if="hasControl(team.id)"
                            class="rounded-full bg-warning px-2 py-0.5 text-xs font-bold text-black"
                        >
                            CONTROL
                        </span>
                    </div>
                    <!-- Editable Score -->
                    <div v-if="editable && editingTeamId === team.id" class="flex items-center gap-2">
                        <input
                            v-model="editScore"
                            type="number"
                            min="0"
                            class="w-20 rounded border border-primary bg-surface-inset px-2 py-1 text-center text-xl font-bold text-body focus:outline-none focus:ring-2 focus:ring-primary"
                            @keydown="handleKeydown"
                            @blur="saveScore"
                            autofocus
                        />
                    </div>
                    <span
                        v-else
                        class="text-2xl font-bold text-body"
                        :class="{ 'cursor-pointer transition-colors hover:text-primary': editable }"
                        :title="editable ? 'Click to edit score' : undefined"
                        @click="startEditing(team)"
                    >
                        {{ team.total_score }}
                    </span>
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
