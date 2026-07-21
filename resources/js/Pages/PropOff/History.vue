<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import Card from '@/Components/Base/Card.vue';
import Icon from '@/Components/Base/Icon.vue';
import Select from '@/Components/Form/Select.vue';

const props = defineProps({
    stats: Object,
    years: Array,
    entries: Object,
    currentYear: Number,
});

// Get ordinal suffix
const getOrdinal = (n) => {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Year options for select
const yearOptions = computed(() =>
    props.years.map(year => ({ value: year, label: String(year) }))
);

// Selected year (converted to work with Select component)
const selectedYear = computed({
    get: () => props.currentYear || '',
    set: (value) => filterByYear(value || null),
});

// Filter by year
const filterByYear = (year) => {
    router.get(route('propoff.history'), year ? { year } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Format avg from first
const avgFromFirstDisplay = computed(() => {
    const value = props.stats.avg_from_first;
    if (value === 0) return '0 pts';
    if (value > 0) return '+' + value + ' pts';
    return value + ' pts';
});
</script>

<template>
    <Head title="History" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="History" subtitle="Your past events and results" />
        </template>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-6 py-8">
            <!-- Year Filter -->
            <div class="bg-surface shadow-sm sm:rounded-lg mb-6 border border-border">
                <div class="p-6">
                    <div class="max-w-xs">
                        <Select
                            v-model="selectedYear"
                            :options="yearOptions"
                            placeholder="All Years"
                            allow-empty
                            empty-label="All Years"
                        />
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-surface-inset border border-border border-t-4 border-t-success rounded-lg p-5 text-center">
                    <div class="text-3xl font-bold text-success mb-1">{{ stats.events_played }}</div>
                    <div class="text-xs text-muted uppercase tracking-wider">Events Played</div>
                </div>
                <div class="bg-surface-inset border border-border border-t-4 border-t-warning rounded-lg p-5 text-center">
                    <div class="flex items-center justify-center gap-0 text-2xl font-bold text-warning mb-1">
                        <span class="min-w-[36px] text-center">{{ stats.podium_finishes.first }}</span>
                        <span class="text-subtle mx-2">|</span>
                        <span class="min-w-[36px] text-center">{{ stats.podium_finishes.second }}</span>
                        <span class="text-subtle mx-2">|</span>
                        <span class="min-w-[36px] text-center">{{ stats.podium_finishes.third }}</span>
                    </div>
                    <div class="flex items-center justify-center gap-0 text-xs text-muted">
                        <span class="min-w-[36px] text-center">1st</span>
                        <span class="text-transparent mx-2">|</span>
                        <span class="min-w-[36px] text-center">2nd</span>
                        <span class="text-transparent mx-2">|</span>
                        <span class="min-w-[36px] text-center">3rd</span>
                    </div>
                </div>
                <div class="bg-surface-inset border border-border border-t-4 border-t-primary rounded-lg p-5 text-center">
                    <div class="text-3xl font-bold text-primary mb-1">{{ avgFromFirstDisplay }}</div>
                    <div class="text-xs text-muted uppercase tracking-wider">Avg from 1st</div>
                </div>
                <div class="bg-surface-inset border border-border border-t-4 border-t-info rounded-lg p-5 text-center">
                    <div class="text-3xl font-bold text-info mb-1">{{ stats.avg_rank || '-' }}</div>
                    <div class="text-xs text-muted uppercase tracking-wider">Avg Rank</div>
                </div>
            </div>

            <!-- History List -->
            <Card>
                <div class="divide-y divide-border -m-5">
                    <Link
                        v-for="entry in entries.data"
                        :key="entry.id"
                        :href="route('propoff.play.results', { code: entry.group_code })"
                        class="flex items-center justify-between p-4 hover:bg-surface-elevated transition-colors"
                    >
                        <div>
                            <div class="font-semibold text-body mb-0.5">{{ entry.event_name }}</div>
                            <div class="text-sm text-muted mb-0.5">{{ entry.group_name }}</div>
                            <div class="text-xs text-subtle">
                                {{ formatDate(entry.event_date) }} • {{ entry.player_count }} players
                            </div>
                        </div>
                        <div class="text-right">
                            <div
                                v-if="entry.rank"
                                class="inline-flex items-center justify-center min-w-[36px] h-7 px-2 rounded-md font-bold text-sm bg-surface-inset text-body mb-1"
                            >
                                {{ getOrdinal(entry.rank) }}
                            </div>
                            <div class="text-sm text-body">{{ entry.score }} pts</div>
                        </div>
                    </Link>

                    <!-- Empty State -->
                    <div v-if="entries.data.length === 0" class="p-8 text-center">
                        <Icon name="history" size="3x" class="text-subtle mb-4" />
                        <p class="text-muted">No completed events yet</p>
                        <p class="text-sm text-subtle mt-2">Your past results will appear here</p>
                    </div>
                </div>
            </Card>

            <!-- Pagination -->
            <div v-if="entries.links && entries.links.length > 3" class="mt-6 flex justify-center gap-2">
                <Link
                    v-for="link in entries.links"
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
