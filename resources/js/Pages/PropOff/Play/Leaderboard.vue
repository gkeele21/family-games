<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import Badge from '@/Components/PropOff/Base/Badge.vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Card from '@/Components/PropOff/Base/Card.vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';

const props = defineProps({
    group: Object,
    leaderboard: Object,
    userRow: Object,
});

// Sorting state
const sortColumn = ref('rank');
const sortDirection = ref('asc');

const toggleSort = (column) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        // Default directions: rank asc, others desc (highest first)
        sortDirection.value = column === 'rank' ? 'asc' : 'desc';
    }
};

const sortedLeaderboard = computed(() => {
    const data = [...props.leaderboard.data];
    const col = sortColumn.value;
    const dir = sortDirection.value;

    return data.sort((a, b) => {
        let aVal, bVal;

        switch (col) {
            case 'rank':
                aVal = a.rank;
                bVal = b.rank;
                break;
            case 'player':
                aVal = (a.user?.name || '').toLowerCase();
                bVal = (b.user?.name || '').toLowerCase();
                break;
            case 'answers':
                aVal = a.answered_count;
                bVal = b.answered_count;
                break;
            case 'points':
                aVal = a.total_score;
                bVal = b.total_score;
                break;
            case 'max':
                aVal = a.possible_points;
                bVal = b.possible_points;
                break;
            default:
                return 0;
        }

        if (aVal < bVal) return dir === 'asc' ? -1 : 1;
        if (aVal > bVal) return dir === 'asc' ? 1 : -1;
        return 0;
    });
});

// Get ordinal suffix
const getOrdinal = (n) => {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

const breadcrumbs = computed(() => [
    { label: 'Home', href: route('propoff.play.hub', { code: props.group.code }) },
    { label: 'Leaderboard' },
]);
</script>

<template>
    <Head :title="`Leaderboard - ${group.name}`" />

    <AuthenticatedLayout :group="group">
        <template #header>
            <PageHeader title="Leaderboard" :subtitle="group.event?.name" :crumbs="breadcrumbs">
                <template v-if="group.is_locked" #actions>
                    <Link :href="route('propoff.play.game', { code: group.code })">
                        <Button
                            variant="accent"
                            size="sm"
                            icon="eye"
                        >
                            View My Answers
                        </Button>
                    </Link>
                </template>
            </PageHeader>
        </template>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
            <Card>
                <div class="-m-5">
                    <!-- Column Headers -->
                    <div class="flex items-center py-3 px-4 sm:px-5 bg-surface-header border-b border-border text-xs font-medium text-subtle uppercase tracking-wider">
                        <button
                            class="w-12 sm:w-14 flex items-center gap-1 hover:text-body transition-colors cursor-pointer"
                            @click="toggleSort('rank')"
                        >
                            Rank
                            <Icon v-if="sortColumn === 'rank'" :name="sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" size="xs" />
                        </button>
                        <button
                            class="flex-1 min-w-0 flex items-center gap-1 hover:text-body transition-colors cursor-pointer text-left"
                            @click="toggleSort('player')"
                        >
                            Player
                            <Icon v-if="sortColumn === 'player'" :name="sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" size="xs" />
                        </button>
                        <button
                            v-if="!group.is_locked"
                            class="w-14 sm:w-20 flex items-center justify-end gap-1 hover:text-body transition-colors cursor-pointer"
                            @click="toggleSort('answers')"
                        >
                            <Icon v-if="sortColumn === 'answers'" :name="sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" size="xs" />
                            Answers
                        </button>
                        <button
                            class="w-14 sm:w-24 flex items-center justify-end gap-1 hover:text-body transition-colors cursor-pointer"
                            @click="toggleSort('points')"
                        >
                            <Icon v-if="sortColumn === 'points'" :name="sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" size="xs" />
                            Points
                        </button>
                        <button
                            class="w-12 sm:w-24 flex items-center justify-end gap-1 hover:text-body transition-colors cursor-pointer"
                            @click="toggleSort('max')"
                        >
                            <Icon v-if="sortColumn === 'max'" :name="sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" size="xs" />
                            Max
                        </button>
                    </div>

                    <!-- Leaderboard Rows -->
                    <div class="divide-y divide-border">
                        <div
                            v-for="entry in sortedLeaderboard"
                            :key="entry.id"
                            class="flex items-center py-3 px-4 sm:px-5"
                            :class="{ 'bg-surface-elevated': entry.user_id === userRow?.user_id }"
                        >
                            <div class="w-12 sm:w-14 font-bold text-lg sm:text-xl text-primary">
                                {{ getOrdinal(entry.rank) }}
                            </div>
                            <div class="flex-1 min-w-0 truncate">
                                {{ entry.user?.name }}
                                <Badge v-if="entry.user_id === userRow?.user_id" variant="primary-soft" size="sm" class="ml-1 sm:ml-2">You</Badge>
                            </div>
                            <div v-if="!group.is_locked" class="w-14 sm:w-20 text-right text-muted">{{ entry.answered_count }}</div>
                            <div class="w-14 sm:w-24 text-right font-semibold text-body">{{ entry.total_score }}</div>
                            <div class="w-12 sm:w-24 text-right text-muted">{{ entry.possible_points }}</div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="sortedLeaderboard.length === 0" class="p-8 text-center">
                        <Icon name="trophy" size="3x" class="text-subtle mb-4" />
                        <p class="text-muted">No entries yet</p>
                    </div>
                </div>
            </Card>

            <!-- Pagination -->
            <div v-if="leaderboard.links && leaderboard.links.length > 3" class="mt-6 flex justify-center gap-2">
                <Link
                    v-for="link in leaderboard.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="px-3 py-1 rounded text-sm"
                    :class="link.active
                        ? 'bg-primary text-white'
                        : 'bg-surface border border-border text-muted hover:border-primary'"
                    v-html="link.label"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
