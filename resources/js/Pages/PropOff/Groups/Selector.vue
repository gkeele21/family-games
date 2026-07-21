<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import Badge from '@/Components/Base/Badge.vue';
import Card from '@/Components/Base/Card.vue';
import Icon from '@/Components/Base/Icon.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';

const props = defineProps({
    groups: Array,
});

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Choose a Group" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="Choose a Group" subtitle="Select a group to view or play" />
        </template>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-6 py-8">
            <Card>
                <div class="divide-y divide-border -m-5">
                    <Link
                        v-for="group in groups"
                        :key="group.id"
                        :href="route('propoff.play.hub', { code: group.code })"
                        class="flex items-center justify-between p-4 hover:bg-surface-elevated transition-colors"
                    >
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-semibold text-body">{{ group.name }}</span>
                                <Badge v-if="group.is_captain" variant="warning-soft" size="sm">Captain</Badge>
                            </div>
                            <div class="text-sm text-muted mb-1">{{ group.event?.name }}</div>
                            <div class="text-xs text-subtle">
                                {{ group.members_count }} members •
                                {{ group.my_progress.status }}
                            </div>
                        </div>
                        <Icon name="chevron-right" class="text-muted" />
                    </Link>

                    <!-- Empty state -->
                    <div v-if="groups.length === 0" class="p-8 text-center">
                        <Icon name="users" size="3x" class="text-subtle mb-4" />
                        <p class="text-muted">No active groups found</p>
                        <p class="text-sm text-subtle mt-2">Join a group using a code to get started</p>
                    </div>
                </div>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
