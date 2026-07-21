<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import StatCard from '@/Components/PropOff/Domain/StatCard.vue';
import Badge from '@/Components/Base/Badge.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    adminStats: Object,
});

// Build secondary stat strings
const eventsSubtitle = () => {
    const parts = [];
    if (props.adminStats.open_events > 0) {
        parts.push(`${props.adminStats.open_events} open`);
    }
    if (props.adminStats.draft_events > 0) {
        parts.push(`${props.adminStats.draft_events} draft`);
    }
    return parts.length > 0 ? parts.join(' · ') : 'No active events';
};

const usersSubtitle = () => {
    return `${props.adminStats.admin_users} admin${props.adminStats.admin_users !== 1 ? 's' : ''}`;
};

const groupsSubtitle = () => {
    return `${props.adminStats.total_group_members} total members`;
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="Admin Dashboard">
                <template #metadata>
                    <Badge variant="danger" size="sm">Admin</Badge>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Unified Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                        title="Events"
                        :count="adminStats.total_events"
                        :subtitle="eventsSubtitle()"
                        :href="route('propoff.admin.events.index')"
                        border-color="primary"
                    />

                    <StatCard
                        title="Question Templates"
                        :count="adminStats.total_templates"
                        subtitle="Reusable templates"
                        :href="route('propoff.admin.question-templates.index')"
                        border-color="warning"
                    />

                    <StatCard
                        title="Users"
                        :count="adminStats.total_users"
                        :subtitle="usersSubtitle()"
                        :href="route('propoff.admin.users.index')"
                        border-color="gray-dark"
                    />

                    <StatCard
                        title="Groups"
                        :count="adminStats.total_groups"
                        :subtitle="groupsSubtitle()"
                        :href="route('propoff.admin.groups.index')"
                        border-color="success"
                    />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
