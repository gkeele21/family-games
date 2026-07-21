<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import Button from '@/Components/Base/Button.vue';
import Badge from '@/Components/Base/Badge.vue';
import Card from '@/Components/Base/Card.vue';
import Icon from '@/Components/Base/Icon.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import Toast from '@/Components/Feedback/Toast.vue';
import StatTile from '@/Components/Base/StatTile.vue';

const props = defineProps({
    group: Object,
    stats: Object,
    myEntry: Object,
    gameStatus: Object,
    leaderboard: Object,
    isCaptain: Boolean,
    isGuest: Boolean,
});

const page = usePage();
const isAdminOrManager = computed(() => ['admin', 'manager'].includes(page.props.auth?.user?.role));

// Toast state
const showToast = ref(false);
const toastMessage = ref('');

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Format time helper (with day of week)
const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

// Get ordinal suffix
const getOrdinal = (n) => {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

// Entry action button config
const entryButtonConfig = computed(() => {
    const entry = props.myEntry;
    const isLocked = props.gameStatus?.is_locked;

    if (!entry || entry.status === 'not_started') {
        if (isLocked) {
            return { label: "You didn't submit", icon: null, disabled: true, route: null, variant: 'primary' };
        }
        return { label: 'Start Playing', icon: 'play', disabled: false, route: 'propoff.play.game', variant: 'primary' };
    }

    if (entry.status === 'in_progress') {
        if (isLocked) {
            // Still allow viewing results if they have any answers
            if (entry.answered_count > 0) {
                return { label: 'View My Answers', icon: 'eye', disabled: false, route: 'propoff.play.game', variant: 'accent' };
            }
            return { label: "You didn't submit", icon: null, disabled: true, route: null, variant: 'primary' };
        }
        return { label: 'Continue Playing', icon: 'play', disabled: false, route: 'propoff.play.game', variant: 'primary' };
    }

    if (entry.status === 'submitted') {
        if (isLocked) {
            return { label: 'View My Answers', icon: 'eye', disabled: false, route: 'propoff.play.game', variant: 'accent' };
        }
        // Before locked, they can still view/edit their picks
        return { label: 'View Picks', icon: 'eye', disabled: false, route: 'propoff.play.game', variant: 'secondary' };
    }

    return { label: 'View Picks', icon: 'eye', disabled: false, route: 'propoff.play.game', variant: 'secondary' };
});

// Copy join code to clipboard
const copyJoinCode = () => {
    const url = window.location.origin + '/play/' + props.group.code;
    navigator.clipboard.writeText(url).then(() => {
        showToastMessage('Link copied to clipboard!');
    });
};

// Toast helper
const showToastMessage = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Lock toggle
const isLocking = ref(false);
const toggleLock = () => {
    isLocking.value = true;
    router.post(route('propoff.groups.toggle-lock', props.group.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToastMessage(props.gameStatus?.is_locked ? 'Group unlocked' : 'Group locked');
        },
        onFinish: () => {
            isLocking.value = false;
        },
    });
};
</script>

<template>
    <Head :title="group.name" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="group.name">
                <template v-if="isCaptain || isAdminOrManager" #titleSuffix>
                    <Badge v-if="isCaptain" variant="warning-soft">Captain</Badge>
                    <Badge :variant="group.grading_source === 'captain' ? 'primary-soft' : 'info-soft'">
                        {{ group.grading_source === 'captain' ? 'Captain Graded' : 'Admin Graded' }}
                    </Badge>
                    <Link :href="route('propoff.groups.edit', group.id)" class="text-primary hover:text-primary-hover text-sm font-medium">
                        Edit
                    </Link>
                </template>
                <template #metadata>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span v-if="group.event">{{ group.event.name }}</span>
                        <span v-if="group.event?.event_date">•</span>
                        <span v-if="group.event?.event_date">{{ formatDate(group.event.event_date) }}</span>
                    </div>
                </template>
                <template v-if="isCaptain" #actions>
                    <Link :href="route('propoff.groups.questions', group.id)">
                        <Button variant="primary" size="sm" icon="list-check">
                            Questions
                        </Button>
                    </Link>
                    <Link :href="route('propoff.groups.members.index', group.id)">
                        <Button variant="secondary" size="sm" icon="users">
                            Members
                        </Button>
                    </Link>
                    <Button variant="accent" size="sm" icon="share-nodes" @click="router.visit(route('propoff.groups.invitation', group.id))">
                        Invite
                    </Button>
                </template>
            </PageHeader>
        </template>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
            <!-- Stats Row (4 tiles) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatTile
                    :value="stats.total_questions"
                    label="Questions"
                    color="primary"
                    :href="isCaptain ? route('propoff.groups.questions', group.id) : null"
                />
                <StatTile
                    :value="stats.graded_questions"
                    label="Scored"
                    color="warning"
                    :href="isCaptain ? route('propoff.groups.questions', group.id) : null"
                />
                <StatTile :value="stats.total_points" label="Max Possible" color="neutral" />
                <StatTile
                    :value="stats.total_members"
                    label="Members"
                    color="info"
                    :href="isCaptain ? route('propoff.groups.members.index', group.id) : null"
                />
            </div>

            <!-- Two Column Layout -->
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <!-- My Entry Card -->
                <Card>
                    <template #header>
                        <span class="font-semibold">My Entry</span>
                    </template>

                    <div>
                        <!-- Entry Status -->
                        <div class="flex items-center gap-2 mb-3">
                            <Icon
                                v-if="myEntry?.status === 'submitted'"
                                name="circle-check"
                                class="text-success"
                            />
                            <Icon
                                v-else-if="myEntry?.status === 'in_progress'"
                                name="clock"
                                class="text-warning"
                            />
                            <Icon
                                v-else
                                name="circle"
                                class="text-muted"
                            />
                            <span class="text-body">
                                <template v-if="myEntry?.status === 'submitted'">Submitted</template>
                                <template v-else-if="myEntry?.status === 'in_progress'">
                                    {{ myEntry.answered_count }} of {{ myEntry.total_questions }} answered
                                </template>
                                <template v-else>Not started yet</template>
                            </span>
                        </div>

                        <!-- Score and Rank (if submitted) -->
                        <template v-if="myEntry?.status === 'submitted'">
                            <div class="text-xl sm:text-2xl font-bold text-success mb-1">{{ myEntry.score }} <span class="hidden sm:inline">points</span><span class="sm:hidden">pts</span></div>
                            <div v-if="myEntry.rank" class="text-muted text-sm sm:text-base mb-4">
                                {{ getOrdinal(myEntry.rank) }} of {{ myEntry.total_participants }}
                            </div>
                        </template>

                        <!-- Action Button -->
                        <div class="mt-4">
                            <Link
                                v-if="entryButtonConfig.route"
                                :href="route(entryButtonConfig.route, { code: group.code })"
                            >
                                <Button :variant="entryButtonConfig.variant" :disabled="entryButtonConfig.disabled">
                                    <Icon v-if="entryButtonConfig.icon" :name="entryButtonConfig.icon" class="mr-2" size="sm" />
                                    {{ entryButtonConfig.label }}
                                </Button>
                            </Link>
                            <span v-else class="text-muted italic">{{ entryButtonConfig.label }}</span>
                        </div>

                        <!-- Guest upsell -->
                        <div v-if="isGuest && myEntry?.status === 'submitted' && gameStatus?.is_locked" class="mt-4 pt-4 border-t border-border">
                            <div class="text-sm text-muted">
                                Want to save your results?<br>
                                <Link :href="route('register')" class="text-primary hover:underline">Create an account</Link>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Game Status Card -->
                <Card>
                    <template #header>
                        <span class="font-semibold">Game Status</span>
                    </template>

                    <div class="space-y-3">
                        <!-- Event Start -->
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 border-b border-border gap-1">
                            <span class="text-muted text-sm sm:text-base">Event Start</span>
                            <span class="font-semibold text-body text-sm sm:text-base">
                                {{ gameStatus?.event_start ? formatDateTime(gameStatus.event_start) : 'Not set' }}
                            </span>
                        </div>
                        <!-- Entry Cutoff with Lock/Unlock for captains -->
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 border-b border-border gap-2">
                            <span class="text-muted text-sm sm:text-base">Entry Cutoff</span>
                            <div class="flex items-center gap-2 sm:gap-3">
                                <span
                                    class="font-semibold text-sm sm:text-base"
                                    :class="gameStatus?.is_locked ? 'text-danger' : 'text-body'"
                                >
                                    {{ gameStatus?.close_time ? formatDateTime(gameStatus.close_time) : 'Not set' }}
                                </span>
                                <Button
                                    v-if="isCaptain"
                                    :variant="gameStatus?.is_locked ? 'primary' : 'danger'"
                                    size="sm"
                                    :loading="isLocking"
                                    @click="toggleLock"
                                >
                                    <Icon :name="gameStatus?.is_locked ? 'lock-open' : 'lock'" class="sm:mr-1" size="sm" />
                                    <span class="hidden sm:inline">{{ gameStatus?.is_locked ? 'Unlock' : 'Lock Now' }}</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Leaderboard Preview -->
            <Card>
                <template #header>
                    <div class="flex justify-between items-center w-full">
                        <span class="font-semibold">Leaderboard</span>
                        <Link :href="route('propoff.play.leaderboard', { code: group.code })">
                            <Button variant="ghost" size="sm">Full View</Button>
                        </Link>
                    </div>
                </template>

                <div>
                    <!-- Top 5 -->
                    <div
                        v-for="entry in leaderboard.top5"
                        :key="entry.user_id"
                        class="flex items-center py-3 border-b border-border last:border-b-0"
                        :class="{ 'bg-surface-elevated -mx-5 px-5 rounded-lg': entry.user_id === leaderboard.currentUserId }"
                    >
                        <div class="w-10 sm:w-12 font-bold text-base sm:text-lg text-primary mr-2 sm:mr-3 flex-shrink-0">
                            {{ getOrdinal(entry.rank) }}
                        </div>
                        <div class="flex-1 min-w-0 truncate">
                            {{ entry.name }}
                            <Badge v-if="entry.user_id === leaderboard.currentUserId" variant="primary-soft" size="sm" class="ml-1 sm:ml-2">You</Badge>
                        </div>
                        <div class="font-semibold text-sm sm:text-base flex-shrink-0 ml-2">{{ entry.score }} <span class="hidden sm:inline">points</span><span class="sm:hidden">pts</span></div>
                    </div>

                    <!-- User's row if not in top 5 -->
                    <template v-if="leaderboard.userRow">
                        <div class="py-2 text-center text-muted text-sm">...</div>
                        <div class="flex items-center py-3 bg-surface-elevated -mx-5 px-5 rounded-lg">
                            <div class="w-10 sm:w-12 font-bold text-base sm:text-lg text-primary mr-2 sm:mr-3 flex-shrink-0">
                                {{ getOrdinal(leaderboard.userRow.rank) }}
                            </div>
                            <div class="flex-1 min-w-0 truncate">
                                {{ leaderboard.userRow.name }}
                                <Badge variant="primary-soft" size="sm" class="ml-1 sm:ml-2">You</Badge>
                            </div>
                            <div class="font-semibold text-sm sm:text-base flex-shrink-0 ml-2">{{ leaderboard.userRow.score }} <span class="hidden sm:inline">points</span><span class="sm:hidden">pts</span></div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div v-if="leaderboard.top5.length === 0" class="text-center py-8 text-muted">
                        <Icon name="trophy" size="2x" class="mb-2 text-subtle" />
                        <p>No entries yet</p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Toast Notification -->
        <Toast
            :show="showToast"
            :message="toastMessage"
            @close="showToast = false"
        />
    </AuthenticatedLayout>
</template>
