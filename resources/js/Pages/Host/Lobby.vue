<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface TeamMember {
    id: number;
    user_id: number | null;
    guest_name: string | null;
    is_captain: boolean;
    display_name: string;
}

interface Team {
    id: number;
    name: string;
    color: string;
    display_order: number;
    members: TeamMember[];
}

interface Friend {
    id: number;
    name: string;
    first_name: string;
    nickname: string | null;
}

interface WaitingPlayer {
    id: number;
    user_id: number | null;
    guest_name: string | null;
    display_name: string;
    user?: {
        id: number;
        name: string;
        first_name: string;
    };
}

interface GameSession {
    id: number;
    name: string | null;
    status: string;
    invite_code: string;
    game_type: {
        name: string;
        slug: string;
    };
    teams: Team[];
}

interface Props {
    gameSession: GameSession;
    config: Record<string, any>;
    friends: Friend[];
    waitingPlayers: WaitingPlayer[];
}

const props = defineProps<Props>();

const teamColors = [
    '#EF4444', // Red
    '#3B82F6', // Blue
    '#10B981', // Green
    '#F59E0B', // Amber
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#06B6D4', // Cyan
    '#F97316', // Orange
];

const form = useForm({
    name: '',
    color: teamColors[props.gameSession.teams.length % teamColors.length],
});

const addTeam = () => {
    form.post(route('games.teams.add', props.gameSession.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.color = teamColors[(props.gameSession.teams.length + 1) % teamColors.length];
        },
    });
};

const removeTeam = (team: Team) => {
    if (confirm(`Remove team "${team.name}"?`)) {
        router.delete(route('games.teams.remove', [props.gameSession.id, team.id]), {
            preserveScroll: true,
        });
    }
};

// Team member management
const activeTeamId = ref<number | null>(null);
const showAddMemberModal = ref(false);
const addMemberType = ref<'guest' | 'friend' | 'waiting'>('guest');
const guestNameInput = ref('');
const selectedFriendId = ref<number | null>(null);
const selectedWaitingPlayerId = ref<number | null>(null);

const memberForm = useForm({
    type: 'guest' as 'guest' | 'friend' | 'session_player',
    guest_name: '',
    user_id: null as number | null,
    session_player_id: null as number | null,
});

const openAddMember = (teamId: number) => {
    activeTeamId.value = teamId;
    showAddMemberModal.value = true;
    addMemberType.value = 'guest';
    guestNameInput.value = '';
    selectedFriendId.value = null;
    selectedWaitingPlayerId.value = null;
};

const closeAddMemberModal = () => {
    showAddMemberModal.value = false;
    activeTeamId.value = null;
};

const addTeamMember = () => {
    if (!activeTeamId.value) return;

    if (addMemberType.value === 'guest') {
        memberForm.type = 'guest';
        memberForm.guest_name = guestNameInput.value;
        memberForm.user_id = null;
        memberForm.session_player_id = null;
    } else if (addMemberType.value === 'friend') {
        memberForm.type = 'friend';
        memberForm.guest_name = '';
        memberForm.user_id = selectedFriendId.value;
        memberForm.session_player_id = null;
    } else if (addMemberType.value === 'waiting') {
        memberForm.type = 'session_player';
        memberForm.guest_name = '';
        memberForm.user_id = null;
        memberForm.session_player_id = selectedWaitingPlayerId.value;
    }

    memberForm.post(route('games.teams.members.add', [props.gameSession.id, activeTeamId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddMemberModal();
            memberForm.reset();
        },
    });
};

const removeTeamMember = (team: Team, member: TeamMember) => {
    if (confirm(`Remove "${member.display_name}" from ${team.name}?`)) {
        router.delete(route('games.teams.members.remove', [props.gameSession.id, team.id, member.id]), {
            preserveScroll: true,
        });
    }
};

// Check if a friend is already on a team
const isFriendOnTeam = (friendId: number): boolean => {
    return props.gameSession.teams.some(team =>
        team.members?.some(member => member.user_id === friendId)
    );
};

// Available friends (not already on a team)
const availableFriends = computed(() => {
    return props.friends.filter(friend => !isFriendOnTeam(friend.id));
});

const startGame = () => {
    router.post(route('games.start', props.gameSession.id));
};

const cancelGame = () => {
    if (confirm('Are you sure you want to cancel this game? This cannot be undone.')) {
        router.delete(route('games.destroy', props.gameSession.id));
    }
};

// Drag and drop for team reordering
const draggedTeamId = ref<number | null>(null);
const dragOverTeamId = ref<number | null>(null);

const onDragStart = (event: DragEvent, teamId: number) => {
    draggedTeamId.value = teamId;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', teamId.toString());
    }
};

const onDragOver = (event: DragEvent, teamId: number) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverTeamId.value = teamId;
};

const onDragLeave = () => {
    dragOverTeamId.value = null;
};

const onDrop = (event: DragEvent, targetTeamId: number) => {
    event.preventDefault();
    dragOverTeamId.value = null;

    if (draggedTeamId.value === null || draggedTeamId.value === targetTeamId) {
        draggedTeamId.value = null;
        return;
    }

    // Reorder teams locally
    const teams = [...props.gameSession.teams];
    const draggedIndex = teams.findIndex(t => t.id === draggedTeamId.value);
    const targetIndex = teams.findIndex(t => t.id === targetTeamId);

    if (draggedIndex === -1 || targetIndex === -1) {
        draggedTeamId.value = null;
        return;
    }

    // Move the dragged team to the target position
    const [draggedTeam] = teams.splice(draggedIndex, 1);
    teams.splice(targetIndex, 0, draggedTeam);

    // Get the new order of team IDs
    const teamIds = teams.map(t => t.id);

    // Send the new order to the backend
    router.post(route('games.teams.reorder', props.gameSession.id), {
        team_ids: teamIds,
    }, {
        preserveScroll: true,
    });

    draggedTeamId.value = null;
};

const onDragEnd = () => {
    draggedTeamId.value = null;
    dragOverTeamId.value = null;
};

const copyInviteCode = () => {
    navigator.clipboard.writeText(props.gameSession.invite_code);
    codeCopied.value = true;
    setTimeout(() => codeCopied.value = false, 2000);
};

const joinUrl = computed(() => {
    const baseUrl = window.location.origin;
    return `${baseUrl}/play?code=${props.gameSession.invite_code}`;
});

const copyJoinUrl = () => {
    navigator.clipboard.writeText(joinUrl.value);
    urlCopied.value = true;
    setTimeout(() => urlCopied.value = false, 2000);
};

const codeCopied = ref(false);
const urlCopied = ref(false);
const displayUrlCopied = ref(false);

const displayUrl = computed(() => {
    const baseUrl = window.location.origin;
    return `${baseUrl}/display/${props.gameSession.invite_code}`;
});

const copyDisplayUrl = () => {
    navigator.clipboard.writeText(displayUrl.value);
    displayUrlCopied.value = true;
    setTimeout(() => displayUrlCopied.value = false, 2000);
};

// Settings management
const showSettingsModal = ref(false);

// Create a copy of all settings for the form
const settingsForm = useForm({
    settings: { ...props.config },
});

// Track team size mode: 'unlimited', 'individual', or 'fixed'
const getInitialTeamSizeMode = () => {
    const size = props.config.team_size ?? 0;
    if (size === 0) return 'unlimited';
    if (size === 1) return 'individual';
    return 'fixed';
};
const teamSizeMode = ref<'unlimited' | 'individual' | 'fixed'>(getInitialTeamSizeMode());
const customTeamSize = ref(props.config.team_size > 1 ? props.config.team_size : 2);

const setTeamSizeMode = (mode: 'unlimited' | 'individual' | 'fixed') => {
    teamSizeMode.value = mode;
    if (mode === 'unlimited') {
        settingsForm.settings.team_size = 0;
    } else if (mode === 'individual') {
        settingsForm.settings.team_size = 1;
    } else {
        settingsForm.settings.team_size = customTeamSize.value;
    }
};

const updateCustomTeamSize = () => {
    if (customTeamSize.value >= 2 && teamSizeMode.value === 'fixed') {
        settingsForm.settings.team_size = customTeamSize.value;
    }
};

const isIndividualPlay = computed(() => settingsForm.settings.team_size === 1);

// Human-readable team size label
const teamSizeLabel = computed(() => {
    const size = settingsForm.settings.team_size;
    if (size === undefined || size === null || size === 0) return 'Unlimited';
    if (size === 1) return 'Individual Play';
    return `${size} per team`;
});

// Game type slug for conditional settings
const gameSlug = computed(() => props.gameSession.game_type.slug);

// Save settings and close modal
const saveSettings = () => {
    settingsForm.patch(route('games.settings.update', props.gameSession.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false;
        },
    });
};

const openSettingsModal = () => {
    // Reset form to current config values
    settingsForm.settings = { ...props.config };
    const size = props.config.team_size ?? 0;
    if (size === 0) teamSizeMode.value = 'unlimited';
    else if (size === 1) teamSizeMode.value = 'individual';
    else {
        teamSizeMode.value = 'fixed';
        customTeamSize.value = size;
    }
    showSettingsModal.value = true;
};
</script>

<template>
    <Head :title="`Lobby - ${gameSession.game_type.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Game Lobby - {{ gameSession.game_type.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Invite Code & Link -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Invite Players</h3>

                    <!-- Invite Code -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-2">Game Code</label>
                        <div class="flex items-center gap-3">
                            <div class="text-4xl font-mono font-bold tracking-widest bg-gray-100 px-6 py-3 rounded-lg">
                                {{ gameSession.invite_code }}
                            </div>
                            <button
                                @click="copyInviteCode"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                {{ codeCopied ? 'Copied!' : 'Copy Code' }}
                            </button>
                        </div>
                    </div>

                    <!-- Join URL -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-2">Or share this link</label>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-gray-100 px-4 py-3 rounded-lg font-mono text-sm text-gray-700 truncate">
                                {{ joinUrl }}
                            </div>
                            <button
                                @click="copyJoinUrl"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex-shrink-0"
                            >
                                {{ urlCopied ? 'Copied!' : 'Copy Link' }}
                            </button>
                        </div>
                    </div>

                    <!-- Display URL for TV/Projector -->
                    <div class="border-t pt-4 mt-4">
                        <label class="block text-sm font-medium text-gray-500 mb-2">
                            Display on TV/Projector
                            <span class="text-gray-400 font-normal ml-1">(no controls, just the game view)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-purple-50 px-4 py-3 rounded-lg font-mono text-sm text-purple-700 truncate">
                                {{ displayUrl }}
                            </div>
                            <button
                                @click="copyDisplayUrl"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex-shrink-0"
                            >
                                {{ displayUrlCopied ? 'Copied!' : 'Copy Display Link' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Game Settings Summary Card -->
                <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Game Settings</h3>

                            <!-- Player Settings -->
                            <div class="mb-3">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Player Settings</h4>
                                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                    <div>
                                        <span class="text-gray-500">Team Size:</span>
                                        <span class="font-medium text-gray-700 ml-1">{{ teamSizeLabel }}</span>
                                    </div>
                                    <div v-if="!isIndividualPlay">
                                        <span class="text-gray-500">Team Selection:</span>
                                        <span class="font-medium text-gray-700 ml-1">
                                            {{ settingsForm.settings.allow_team_selection ? 'Players choose' : 'Host assigns' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Game-specific Settings -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">{{ gameSession.game_type.name }} Settings</h4>
                                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                    <!-- Family Feud Settings -->
                                    <template v-if="gameSlug === 'family-feud'">
                                        <div>
                                            <span class="text-gray-500">Rounds:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.rounds_per_game }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Strikes:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.max_strikes }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Face-Off:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.face_off_mode }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Steal Mode:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.steal_mode === 'one_guess' ? 'One guess' : 'Timed' }}</span>
                                        </div>
                                        <div v-if="settingsForm.settings.steal_mode === 'timed'">
                                            <span class="text-gray-500">Steal Timer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.steal_timer_seconds }}s</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Fast Money:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.fast_money_enabled ? 'On' : 'Off' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Play or Pass:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.play_or_pass_enabled ? 'On' : 'Off' }}</span>
                                        </div>
                                    </template>

                                    <!-- America Says Settings -->
                                    <template v-else-if="gameSlug === 'america-says'">
                                        <div>
                                            <span class="text-gray-500">Questions:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.questions_per_game }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Answers/Question:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.answers_per_question }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Control Timer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.control_timer_seconds }}s</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Steal Timer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.steal_timer_seconds }}s</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Steal Points:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.steal_points_percentage }}%</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Points/Answer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.points_per_answer }}</span>
                                        </div>
                                    </template>

                                    <!-- Oodles Settings -->
                                    <template v-else-if="gameSlug === 'oodles'">
                                        <div>
                                            <span class="text-gray-500">Cards:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.cards_per_game }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Questions/Card:</span>
                                            <span class="font-medium text-gray-700 ml-1">
                                                <template v-if="settingsForm.settings.questions_per_card_mode === 'fixed'">
                                                    {{ settingsForm.settings.fixed_questions_per_card }}
                                                </template>
                                                <template v-else>
                                                    {{ settingsForm.settings.min_questions_per_card }}-{{ settingsForm.settings.max_questions_per_card }}
                                                </template>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Timer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.control_timer_seconds }}s</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">All Play Timer:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.all_play_timer_seconds }}s</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Letter Reuse:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.allow_letter_reuse ? 'Yes' : 'No' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Points:</span>
                                            <span class="font-medium text-gray-700 ml-1">{{ settingsForm.settings.points_per_answer }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Multi-Team:</span>
                                            <span class="font-medium text-gray-700 ml-1">
                                                {{ settingsForm.settings.multi_team_scoring === 'split' ? 'Split points' : 'Full points each' }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="openSettingsModal"
                            class="px-4 py-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg font-medium transition-colors flex-shrink-0"
                        >
                            Edit Settings
                        </button>
                    </div>
                </div>

                <!-- Waiting Players -->
                <div v-if="waitingPlayers.length > 0" class="bg-yellow-50 border border-yellow-200 shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-yellow-800">
                        Players Waiting to Join ({{ waitingPlayers.length }})
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="player in waitingPlayers"
                            :key="player.id"
                            class="bg-white px-3 py-2 rounded-full border border-yellow-300 text-sm"
                        >
                            {{ player.display_name }}
                        </div>
                    </div>
                    <p class="text-yellow-700 text-sm mt-3">
                        Add these players to a team using the "Add Member" button below.
                    </p>
                </div>

                <!-- Teams -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Teams</h3>

                    <!-- Add Team Form -->
                    <form @submit.prevent="addTeam" class="flex gap-4 mb-6">
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Team name"
                            class="flex-1 rounded-lg border-gray-300"
                            required
                        />
                        <input
                            v-model="form.color"
                            type="color"
                            class="w-14 h-10 rounded-lg cursor-pointer"
                        />
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                        >
                            Add Team
                        </button>
                    </form>

                    <!-- Team List -->
                    <div class="space-y-4">
                        <div
                            v-for="team in gameSession.teams"
                            :key="team.id"
                            class="rounded-lg border-2 transition-all duration-200"
                            :class="{
                                'opacity-50': draggedTeamId === team.id,
                                'ring-2 ring-blue-400 ring-offset-2': dragOverTeamId === team.id && draggedTeamId !== team.id,
                            }"
                            :style="{ borderColor: team.color }"
                            draggable="true"
                            @dragstart="onDragStart($event, team.id)"
                            @dragover="onDragOver($event, team.id)"
                            @dragleave="onDragLeave"
                            @drop="onDrop($event, team.id)"
                            @dragend="onDragEnd"
                        >
                            <!-- Team Header -->
                            <div
                                class="flex items-center justify-between p-4 cursor-grab active:cursor-grabbing"
                                :style="{ backgroundColor: team.color + '20' }"
                            >
                                <div class="flex items-center gap-3">
                                    <!-- Drag Handle -->
                                    <div class="text-gray-400 hover:text-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                        </svg>
                                    </div>
                                    <div
                                        class="w-6 h-6 rounded-full"
                                        :style="{ backgroundColor: team.color }"
                                    ></div>
                                    <span class="font-semibold text-lg">{{ team.name }}</span>
                                    <span class="text-gray-500 text-sm">
                                        ({{ team.members?.length || 0 }} members)
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="openAddMember(team.id)"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                                    >
                                        + Add Member
                                    </button>
                                    <button
                                        @click="removeTeam(team)"
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        Remove Team
                                    </button>
                                </div>
                            </div>

                            <!-- Team Members -->
                            <div v-if="team.members && team.members.length > 0" class="p-4 bg-white">
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="member in team.members"
                                        :key="member.id"
                                        class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-full"
                                    >
                                        <span>{{ member.display_name }}</span>
                                        <button
                                            @click="removeTeamMember(team, member)"
                                            class="text-gray-400 hover:text-red-600 text-lg leading-none"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-4 bg-white text-gray-400 text-sm">
                                No members yet. Click "Add Member" to add players.
                            </div>
                        </div>

                        <div v-if="gameSession.teams.length === 0" class="text-gray-500 text-center py-8">
                            No teams yet. Add at least one team to start the game.
                        </div>
                    </div>
                </div>

                <!-- Start Game / Cancel -->
                <div class="flex justify-center gap-4">
                    <button
                        @click="cancelGame"
                        class="px-6 py-4 text-gray-600 hover:text-gray-800 font-medium"
                    >
                        Cancel Game
                    </button>
                    <button
                        @click="startGame"
                        :disabled="gameSession.teams.length < 1"
                        class="px-8 py-4 bg-green-600 text-white text-xl font-bold rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Start Game
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Member Modal -->
        <div
            v-if="showAddMemberModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeAddMemberModal"
        >
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Add Team Member</h3>

                <!-- Member Type Tabs -->
                <div class="flex border-b mb-4">
                    <button
                        @click="addMemberType = 'guest'"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                            addMemberType === 'guest'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        Guest
                    </button>
                    <button
                        @click="addMemberType = 'friend'"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                            addMemberType === 'friend'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        Friends
                    </button>
                    <button
                        v-if="waitingPlayers.length > 0"
                        @click="addMemberType = 'waiting'"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                            addMemberType === 'waiting'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        Joined Players ({{ waitingPlayers.length }})
                    </button>
                </div>

                <!-- Guest Form -->
                <div v-if="addMemberType === 'guest'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Guest Name
                        </label>
                        <input
                            v-model="guestNameInput"
                            type="text"
                            placeholder="Enter guest name"
                            class="w-full rounded-lg border-gray-300"
                        />
                    </div>
                </div>

                <!-- Friends List -->
                <div v-else-if="addMemberType === 'friend'" class="space-y-2">
                    <div v-if="availableFriends.length === 0" class="text-gray-500 text-center py-4">
                        <p>No friends available.</p>
                        <p class="text-sm">Add friends from your profile to see them here.</p>
                    </div>
                    <div
                        v-for="friend in availableFriends"
                        :key="friend.id"
                        @click="selectedFriendId = friend.id"
                        :class="[
                            'p-3 rounded-lg border cursor-pointer transition-colors',
                            selectedFriendId === friend.id
                                ? 'border-blue-600 bg-blue-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]"
                    >
                        <div class="font-medium">
                            {{ friend.nickname || friend.name }}
                        </div>
                        <div v-if="friend.nickname" class="text-sm text-gray-500">
                            {{ friend.name }}
                        </div>
                    </div>
                </div>

                <!-- Waiting Players List -->
                <div v-else-if="addMemberType === 'waiting'" class="space-y-2">
                    <div
                        v-for="player in waitingPlayers"
                        :key="player.id"
                        @click="selectedWaitingPlayerId = player.id"
                        :class="[
                            'p-3 rounded-lg border cursor-pointer transition-colors',
                            selectedWaitingPlayerId === player.id
                                ? 'border-blue-600 bg-blue-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]"
                    >
                        <div class="font-medium">{{ player.display_name }}</div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <button
                        @click="closeAddMemberModal"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                        Cancel
                    </button>
                    <button
                        @click="addTeamMember"
                        :disabled="
                            memberForm.processing ||
                            (addMemberType === 'guest' && !guestNameInput) ||
                            (addMemberType === 'friend' && !selectedFriendId) ||
                            (addMemberType === 'waiting' && !selectedWaitingPlayerId)
                        "
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        Add Member
                    </button>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div
            v-if="showSettingsModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showSettingsModal = false"
        >
            <div class="bg-white rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b sticky top-0 bg-white">
                    <h3 class="text-xl font-semibold">Edit Game Settings</h3>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Player Settings Section -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Player Settings</h4>

                        <!-- Team Size -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Team Size
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="modal_team_size_mode"
                                        :checked="teamSizeMode === 'unlimited'"
                                        @change="setTeamSizeMode('unlimited')"
                                        class="text-blue-600 focus:ring-blue-500"
                                    />
                                    <div>
                                        <span class="font-medium text-gray-700">Unlimited</span>
                                        <p class="text-sm text-gray-500">You assign players to teams manually</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="modal_team_size_mode"
                                        :checked="teamSizeMode === 'individual'"
                                        @change="setTeamSizeMode('individual')"
                                        class="text-blue-600 focus:ring-blue-500"
                                    />
                                    <div>
                                        <span class="font-medium text-gray-700">Individual Play</span>
                                        <p class="text-sm text-gray-500">Each player becomes their own team</p>
                                    </div>
                                </label>

                                <div class="flex items-start gap-3">
                                    <input
                                        type="radio"
                                        name="modal_team_size_mode"
                                        :checked="teamSizeMode === 'fixed'"
                                        @change="setTeamSizeMode('fixed')"
                                        class="mt-1 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-700">Fixed size:</span>
                                            <input
                                                v-model.number="customTeamSize"
                                                type="number"
                                                min="2"
                                                max="20"
                                                @input="updateCustomTeamSize"
                                                @focus="setTeamSizeMode('fixed')"
                                                class="w-20 rounded-lg border-gray-300 text-center"
                                            />
                                            <span class="text-gray-700">players per team</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Allow Team Selection -->
                        <div v-if="!isIndividualPlay" class="flex items-center gap-3">
                            <input
                                id="modal_allow_team_selection"
                                v-model="settingsForm.settings.allow_team_selection"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <div>
                                <label for="modal_allow_team_selection" class="text-sm font-medium text-gray-700">
                                    Allow players to pick their team
                                </label>
                                <p class="text-sm text-gray-500">
                                    When enabled, players can choose which team to join
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Game Settings Section -->
                    <div class="border-t pt-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">{{ gameSession.game_type.name }} Settings</h4>

                        <!-- Family Feud Settings -->
                        <div v-if="gameSlug === 'family-feud'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Strikes Before Steal
                                </label>
                                <select
                                    v-model="settingsForm.settings.max_strikes"
                                    class="w-full rounded-lg border-gray-300"
                                >
                                    <option :value="2">2 Strikes</option>
                                    <option :value="3">3 Strikes</option>
                                    <option :value="4">4 Strikes</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    id="modal_fast_money"
                                    v-model="settingsForm.settings.fast_money_enabled"
                                    type="checkbox"
                                    class="rounded border-gray-300"
                                />
                                <label for="modal_fast_money" class="text-sm font-medium text-gray-700">
                                    Enable Fast Money Round
                                </label>
                            </div>
                        </div>

                        <!-- America Says Settings -->
                        <div v-else-if="gameSlug === 'america-says'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Control Timer (seconds)
                                </label>
                                <input
                                    v-model.number="settingsForm.settings.control_timer_seconds"
                                    type="number"
                                    min="15"
                                    max="120"
                                    class="w-full rounded-lg border-gray-300"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Answers Per Question
                                </label>
                                <select
                                    v-model="settingsForm.settings.answers_per_question"
                                    class="w-full rounded-lg border-gray-300"
                                >
                                    <option :value="5">5 Answers</option>
                                    <option :value="6">6 Answers</option>
                                    <option :value="7">7 Answers</option>
                                    <option :value="8">8 Answers</option>
                                </select>
                            </div>
                        </div>

                        <!-- Oodles Settings -->
                        <div v-else-if="gameSlug === 'oodles'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Cards Per Game
                                </label>
                                <input
                                    v-model.number="settingsForm.settings.cards_per_game"
                                    type="number"
                                    min="1"
                                    max="26"
                                    class="w-full rounded-lg border-gray-300"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Questions Per Card Mode
                                </label>
                                <select
                                    v-model="settingsForm.settings.questions_per_card_mode"
                                    class="w-full rounded-lg border-gray-300"
                                >
                                    <option value="random">Random (within min/max range)</option>
                                    <option value="fixed">Fixed number per card</option>
                                </select>
                            </div>

                            <div v-if="settingsForm.settings.questions_per_card_mode === 'fixed'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Fixed Questions Per Card
                                </label>
                                <input
                                    v-model.number="settingsForm.settings.fixed_questions_per_card"
                                    type="number"
                                    min="1"
                                    max="10"
                                    class="w-full rounded-lg border-gray-300"
                                />
                            </div>

                            <div v-else class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Min Questions Per Card
                                    </label>
                                    <input
                                        v-model.number="settingsForm.settings.min_questions_per_card"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-full rounded-lg border-gray-300"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Max Questions Per Card
                                    </label>
                                    <input
                                        v-model.number="settingsForm.settings.max_questions_per_card"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-full rounded-lg border-gray-300"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Timer Duration (seconds)
                                    </label>
                                    <input
                                        v-model.number="settingsForm.settings.control_timer_seconds"
                                        type="number"
                                        min="5"
                                        max="120"
                                        class="w-full rounded-lg border-gray-300"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        All Play Timer (seconds)
                                    </label>
                                    <input
                                        v-model.number="settingsForm.settings.all_play_timer_seconds"
                                        type="number"
                                        min="5"
                                        max="60"
                                        class="w-full rounded-lg border-gray-300"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    id="modal_allow_letter_reuse"
                                    v-model="settingsForm.settings.allow_letter_reuse"
                                    type="checkbox"
                                    class="rounded border-gray-300"
                                />
                                <label for="modal_allow_letter_reuse" class="text-sm font-medium text-gray-700">
                                    Allow Letter Reuse
                                </label>
                                <span class="text-sm text-gray-500">
                                    (same letter can appear on multiple cards)
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Points Per Answer
                                </label>
                                <input
                                    v-model.number="settingsForm.settings.points_per_answer"
                                    type="number"
                                    min="1"
                                    max="1000"
                                    class="w-full rounded-lg border-gray-300"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Multi-Team Scoring
                                </label>
                                <p class="text-sm text-gray-500 mb-2">
                                    When multiple teams answer correctly in All Play
                                </p>
                                <select
                                    v-model="settingsForm.settings.multi_team_scoring"
                                    class="w-full rounded-lg border-gray-300"
                                >
                                    <option value="full">Full points to each team</option>
                                    <option value="split">Split points among teams</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3 sticky bottom-0">
                    <button
                        @click="showSettingsModal = false"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveSettings"
                        :disabled="settingsForm.processing"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        Save Settings
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
