<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface GameType {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    default_config: Record<string, any>;
}

interface Props {
    gameTypes: GameType[];
}

const props = defineProps<Props>();

const selectedGameType = ref<GameType | null>(null);

const form = useForm({
    game_type_id: null as number | null,
    name: '',
    settings: {} as Record<string, any>,
});

const selectGameType = (gameType: GameType) => {
    selectedGameType.value = gameType;
    form.game_type_id = gameType.id;
    form.settings = { ...gameType.default_config };

    // Initialize team size mode based on default config
    const size = gameType.default_config.team_size ?? 0;
    if (size === 0) teamSizeMode.value = 'unlimited';
    else if (size === 1) teamSizeMode.value = 'individual';
    else {
        teamSizeMode.value = 'fixed';
        customTeamSize.value = size;
    }

    // Apply fallback defaults for Oodles settings
    if (gameType.slug === 'oodles') {
        form.settings.points_mode = form.settings.points_mode ?? 'fixed';
        form.settings.points_per_answer = form.settings.points_per_answer ?? 100;
        form.settings.multi_team_scoring = form.settings.multi_team_scoring ?? 'full';
    }
};

// Team size mode tracking
const teamSizeMode = ref<'unlimited' | 'individual' | 'fixed'>('unlimited');
const customTeamSize = ref(2);

const setTeamSizeMode = (mode: 'unlimited' | 'individual' | 'fixed') => {
    teamSizeMode.value = mode;
    if (mode === 'unlimited') {
        form.settings.team_size = 0;
    } else if (mode === 'individual') {
        form.settings.team_size = 1;
    } else {
        form.settings.team_size = customTeamSize.value;
    }
};

const updateCustomTeamSize = () => {
    if (customTeamSize.value >= 2) {
        form.settings.team_size = customTeamSize.value;
    }
};

const submit = () => {
    form.post(route('games.store'));
};

const getGameTypeIcon = (slug: string) => {
    switch (slug) {
        case 'family-feud':
            return '&#128170;'; // flexed bicep
        case 'america-says':
            return '&#127479;&#127480;'; // US flag
        case 'oodles':
            return '&#127922;'; // dice
        default:
            return '&#127918;'; // video game
    }
};

const getGameTypeColor = (slug: string) => {
    switch (slug) {
        case 'family-feud':
            return 'from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600';
        case 'america-says':
            return 'from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600';
        case 'oodles':
            return 'from-green-500 to-teal-500 hover:from-green-600 hover:to-teal-600';
        default:
            return 'from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700';
    }
};
</script>

<template>
    <Head title="Create New Game" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('games.index')" class="text-gray-500 hover:text-gray-700">
                    &larr; Back
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Create New Game
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Step 1: Select Game Type -->
                <div v-if="!selectedGameType" class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Choose a Game Type</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button
                            v-for="gameType in gameTypes"
                            :key="gameType.id"
                            @click="selectGameType(gameType)"
                            class="p-6 rounded-xl text-white text-center transition-all transform hover:scale-105 bg-gradient-to-br"
                            :class="getGameTypeColor(gameType.slug)"
                        >
                            <div class="text-5xl mb-3" v-html="getGameTypeIcon(gameType.slug)"></div>
                            <h4 class="text-xl font-bold mb-2">{{ gameType.name }}</h4>
                            <p class="text-sm opacity-90">{{ gameType.description }}</p>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Configure Game -->
                <div v-else class="space-y-6">
                    <!-- Selected Game Type Header -->
                    <div
                        class="bg-gradient-to-br p-6 rounded-lg text-white"
                        :class="getGameTypeColor(selectedGameType.slug)"
                    >
                        <div class="flex items-center gap-4">
                            <span class="text-4xl" v-html="getGameTypeIcon(selectedGameType.slug)"></span>
                            <div>
                                <h3 class="text-2xl font-bold">{{ selectedGameType.name }}</h3>
                                <p class="opacity-90">{{ selectedGameType.description }}</p>
                            </div>
                            <button
                                @click="selectedGameType = null"
                                class="ml-auto text-white/80 hover:text-white"
                            >
                                Change
                            </button>
                        </div>
                    </div>

                    <!-- Game Settings Form -->
                    <form @submit.prevent="submit" class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="space-y-6">
                            <!-- Session Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Session Name (Optional)
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., Family Game Night"
                                />
                                <p class="text-sm text-gray-500 mt-1">
                                    Give your game session a memorable name
                                </p>
                            </div>

                            <!-- Player Settings -->
                            <div class="border-t pt-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Player Settings</h4>

                                <div class="space-y-4">
                                    <!-- Team Size -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            Team Size
                                        </label>
                                        <div class="space-y-3">
                                            <!-- Unlimited -->
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="team_size_mode"
                                                    :checked="teamSizeMode === 'unlimited'"
                                                    @change="setTeamSizeMode('unlimited')"
                                                    class="text-blue-600 focus:ring-blue-500"
                                                />
                                                <div>
                                                    <span class="font-medium text-gray-700">Unlimited</span>
                                                    <p class="text-sm text-gray-500">You assign players to teams manually</p>
                                                </div>
                                            </label>

                                            <!-- Individual Play -->
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="team_size_mode"
                                                    :checked="teamSizeMode === 'individual'"
                                                    @change="setTeamSizeMode('individual')"
                                                    class="text-blue-600 focus:ring-blue-500"
                                                />
                                                <div>
                                                    <span class="font-medium text-gray-700">Individual Play</span>
                                                    <p class="text-sm text-gray-500">Each player becomes their own team when they join</p>
                                                </div>
                                            </label>

                                            <!-- Fixed Size -->
                                            <div class="flex items-start gap-3">
                                                <input
                                                    type="radio"
                                                    name="team_size_mode"
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
                                                            :disabled="teamSizeMode !== 'fixed'"
                                                            @change="updateCustomTeamSize"
                                                            @focus="setTeamSizeMode('fixed')"
                                                            class="w-20 rounded-lg border-gray-300 text-center disabled:opacity-50"
                                                        />
                                                        <span class="text-gray-700">players per team</span>
                                                    </div>
                                                    <p class="text-sm text-gray-500 mt-1">Teams will have a maximum of this many players</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Allow Team Selection (hidden for individual play) -->
                                    <div v-if="teamSizeMode !== 'individual'" class="flex items-center gap-3 pt-2">
                                        <input
                                            id="allow_team_selection"
                                            v-model="form.settings.allow_team_selection"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <div>
                                            <label for="allow_team_selection" class="text-sm font-medium text-gray-700">
                                                Allow players to pick their team
                                            </label>
                                            <p class="text-sm text-gray-500">
                                                When enabled, players can choose which team to join. Otherwise, you assign them.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Game-specific settings -->
                            <div class="border-t pt-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Game Settings</h4>

                                <!-- Family Feud Settings -->
                                <div v-if="selectedGameType.slug === 'family-feud'" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Strikes Before Steal
                                        </label>
                                        <select
                                            v-model="form.settings.max_strikes"
                                            class="w-full rounded-lg border-gray-300"
                                        >
                                            <option :value="2">2 Strikes</option>
                                            <option :value="3">3 Strikes</option>
                                            <option :value="4">4 Strikes</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="face_off_mode"
                                            v-model="form.settings.face_off_mode"
                                            type="checkbox"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="face_off_mode" class="text-sm font-medium text-gray-700">
                                            Enable Face-Off Mode
                                        </label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="fast_money_enabled"
                                            v-model="form.settings.fast_money_enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="fast_money_enabled" class="text-sm font-medium text-gray-700">
                                            Enable Fast Money Round
                                        </label>
                                    </div>
                                </div>

                                <!-- America Says Settings -->
                                <div v-if="selectedGameType.slug === 'america-says'" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Answers Per Question
                                        </label>
                                        <select
                                            v-model="form.settings.answers_per_question"
                                            class="w-full rounded-lg border-gray-300"
                                        >
                                            <option :value="5">5 Answers</option>
                                            <option :value="6">6 Answers</option>
                                            <option :value="7">7 Answers</option>
                                            <option :value="8">8 Answers</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Gameplay Mode
                                        </label>
                                        <select
                                            v-model="form.settings.gameplay_mode"
                                            class="w-full rounded-lg border-gray-300"
                                        >
                                            <option value="host_reveal">Host Reveals Answers</option>
                                            <option value="team_buzzer">Teams Buzz In</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ form.settings.gameplay_mode === 'host_reveal'
                                                ? 'Host manually reveals correct answers'
                                                : 'Teams buzz in to give their answers' }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Control Timer (seconds)
                                            </label>
                                            <input
                                                v-model.number="form.settings.control_timer_seconds"
                                                type="number"
                                                min="10"
                                                max="120"
                                                class="w-full rounded-lg border-gray-300"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">Time for the controlling team</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Steal Timer (seconds)
                                            </label>
                                            <input
                                                v-model.number="form.settings.steal_timer_seconds"
                                                type="number"
                                                min="5"
                                                max="60"
                                                class="w-full rounded-lg border-gray-300"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">Time for steal round</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Points Per Answer
                                            </label>
                                            <input
                                                v-model.number="form.settings.points_per_answer"
                                                type="number"
                                                min="1"
                                                max="1000"
                                                class="w-full rounded-lg border-gray-300"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Steal Points (%)
                                            </label>
                                            <input
                                                v-model.number="form.settings.steal_points_percentage"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-full rounded-lg border-gray-300"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">% of points during steal round</p>
                                        </div>
                                    </div>

                                    <!-- Winning Condition -->
                                    <div class="border-t pt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            Winning Condition
                                        </label>
                                        <div class="space-y-3">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="winning_condition"
                                                    value="most_points_after_questions"
                                                    v-model="form.settings.winning_condition"
                                                    class="mt-1 text-blue-600 focus:ring-blue-500"
                                                />
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700">Most points after</span>
                                                        <input
                                                            v-model.number="form.settings.questions_per_game"
                                                            type="number"
                                                            min="1"
                                                            max="50"
                                                            class="w-20 rounded-lg border-gray-300 text-center"
                                                            @focus="form.settings.winning_condition = 'most_points_after_questions'"
                                                        />
                                                        <span class="text-gray-700">questions</span>
                                                    </div>
                                                </div>
                                            </label>

                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="winning_condition"
                                                    value="first_to_points"
                                                    v-model="form.settings.winning_condition"
                                                    class="mt-1 text-blue-600 focus:ring-blue-500"
                                                />
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700">First team to</span>
                                                        <input
                                                            v-model.number="form.settings.winning_condition_options.first_to_points"
                                                            type="number"
                                                            min="100"
                                                            max="10000"
                                                            step="100"
                                                            placeholder="1000"
                                                            class="w-24 rounded-lg border-gray-300 text-center"
                                                            @focus="form.settings.winning_condition = 'first_to_points'"
                                                        />
                                                        <span class="text-gray-700">points</span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Oodles Settings -->
                                <div v-if="selectedGameType.slug === 'oodles'" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Cards Per Game
                                        </label>
                                        <input
                                            v-model.number="form.settings.cards_per_game"
                                            type="number"
                                            min="1"
                                            max="26"
                                            class="w-full rounded-lg border-gray-300"
                                        />
                                        <p class="text-sm text-gray-500 mt-1">
                                            How many cards to play through
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Questions Per Card Mode
                                        </label>
                                        <select
                                            v-model="form.settings.questions_per_card_mode"
                                            class="w-full rounded-lg border-gray-300"
                                        >
                                            <option value="random">Random (within min/max range)</option>
                                            <option value="fixed">Fixed number per card</option>
                                        </select>
                                    </div>

                                    <div v-if="form.settings.questions_per_card_mode === 'fixed'">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Fixed Questions Per Card
                                        </label>
                                        <input
                                            v-model.number="form.settings.fixed_questions_per_card"
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
                                                v-model.number="form.settings.min_questions_per_card"
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
                                                v-model.number="form.settings.max_questions_per_card"
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
                                                v-model.number="form.settings.control_timer_seconds"
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
                                                v-model.number="form.settings.all_play_timer_seconds"
                                                type="number"
                                                min="5"
                                                max="60"
                                                class="w-full rounded-lg border-gray-300"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input
                                            id="allow_letter_reuse"
                                            v-model="form.settings.allow_letter_reuse"
                                            type="checkbox"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="allow_letter_reuse" class="text-sm font-medium text-gray-700">
                                            Allow Letter Reuse
                                        </label>
                                        <span class="text-sm text-gray-500">
                                            (same letter can appear on multiple cards)
                                        </span>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            Points Mode
                                        </label>
                                        <div class="space-y-3">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="points_mode"
                                                    value="database"
                                                    v-model="form.settings.points_mode"
                                                    class="mt-1 text-blue-600 focus:ring-blue-500"
                                                />
                                                <div>
                                                    <span class="font-medium text-gray-700">Use database values</span>
                                                    <p class="text-sm text-gray-500">Each question uses its own point value (100-300 based on difficulty)</p>
                                                </div>
                                            </label>

                                            <div class="flex items-start gap-3">
                                                <input
                                                    type="radio"
                                                    name="points_mode"
                                                    value="fixed"
                                                    v-model="form.settings.points_mode"
                                                    class="mt-1 text-blue-600 focus:ring-blue-500"
                                                />
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700">Fixed points:</span>
                                                        <input
                                                            v-model.number="form.settings.points_per_answer"
                                                            type="number"
                                                            min="1"
                                                            max="1000"
                                                            class="w-24 rounded-lg border-gray-300 text-center"
                                                            @focus="form.settings.points_mode = 'fixed'"
                                                        />
                                                        <span class="text-gray-700">per answer</span>
                                                    </div>
                                                    <p class="text-sm text-gray-500 mt-1">All questions worth the same amount</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Multi-Team Scoring
                                        </label>
                                        <p class="text-sm text-gray-500 mb-2">
                                            When multiple teams answer correctly in All Play
                                        </p>
                                        <select
                                            v-model="form.settings.multi_team_scoring"
                                            class="w-full rounded-lg border-gray-300"
                                        >
                                            <option value="full">Full points to each team</option>
                                            <option value="split">Split points among teams</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="mt-8 flex justify-end gap-4">
                            <Link
                                :href="route('games.index')"
                                class="px-6 py-3 text-gray-700 hover:text-gray-900"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
                            >
                                Create Game
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
