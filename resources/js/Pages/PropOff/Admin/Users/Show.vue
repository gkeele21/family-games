<template>
    <Head :title="`User: ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="user.name"
                :crumbs="[
                    { label: 'Users', href: route('propoff.admin.users.index') },
                    { label: user.name }
                ]"
            >
                <template #metadata>
                    <Badge :variant="user.role === 'admin' ? 'danger-soft' : user.role === 'manager' ? 'warning-soft' : 'default'" size="sm">
                        {{ user.role }}
                    </Badge>
                    <span class="text-muted mx-2">•</span>
                    <span class="text-muted">{{ user.email }}</span>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- User Information -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-body mb-4">User Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted">Name</label>
                                <p class="text-body">{{ user.name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted">Email</label>
                                <p class="text-body">{{ user.email }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted">Role</label>
                                <div class="mt-1">
                                    <Badge :variant="user.role === 'admin' ? 'danger-soft' : user.role === 'manager' ? 'warning-soft' : 'default'" size="sm">
                                        {{ user.role }}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted">Member Since</label>
                                <p class="text-body">{{ new Date(user.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-border flex gap-2">
                            <Button variant="primary" @click="showRoleModal = true">
                                Change Role
                            </Button>
                            <Button variant="danger" @click="confirmDelete">
                                Delete User
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                        <div class="p-6">
                            <div class="flex items-center">
                                <Icon name="trophy" size="2x" class="text-warning mr-3" />
                                <div>
                                    <p class="text-sm text-muted">Total Entries</p>
                                    <p class="text-2xl font-bold text-body">{{ stats?.total_entries || 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                        <div class="p-6">
                            <div class="flex items-center">
                                <Icon name="users" size="2x" class="text-warning mr-3" />
                                <div>
                                    <p class="text-sm text-muted">Groups</p>
                                    <p class="text-2xl font-bold text-body">{{ stats?.groups_joined || 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                        <div class="p-6">
                            <div class="flex items-center">
                                <Icon name="chart-simple" size="2x" class="text-success mr-3" />
                                <div>
                                    <p class="text-sm text-muted">Average Score</p>
                                    <p class="text-2xl font-bold text-body">{{ Number(stats?.average_score || 0).toFixed(1) }}%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                        <div class="p-6">
                            <div class="flex items-center">
                                <Icon name="star" size="2x" class="text-success mr-3" />
                                <div>
                                    <p class="text-sm text-muted">Best Score</p>
                                    <p class="text-2xl font-bold text-body">{{ Number(stats?.best_score || 0).toFixed(1) }}%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Entries -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-body mb-4">Recent Entries</h3>
                        <div v-if="!recentEntries || recentEntries.length === 0" class="text-center py-6 text-muted">
                            No entries yet
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-surface-elevated text-left text-xs font-medium text-subtle uppercase tracking-wider">Event</th>
                                        <th class="px-6 py-3 bg-surface-elevated text-left text-xs font-medium text-subtle uppercase tracking-wider">Group</th>
                                        <th class="px-6 py-3 bg-surface-elevated text-left text-xs font-medium text-subtle uppercase tracking-wider">Score</th>
                                        <th class="px-6 py-3 bg-surface-elevated text-left text-xs font-medium text-subtle uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 bg-surface-elevated text-left text-xs font-medium text-subtle uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-surface divide-y divide-border">
                                    <tr v-for="entry in recentEntries" :key="entry.id" class="hover:bg-surface-elevated">
                                        <td class="px-6 py-4 whitespace-nowrap text-body">{{ entry.event?.name || 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-body">{{ entry.group?.name || 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-body">
                                            <span class="font-semibold">{{ entry.total_score }}</span> / {{ entry.possible_points }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Badge :variant="entry.answered_count > 0 ? 'success-soft' : 'warning-soft'" size="sm">
                                                {{ entry.answered_count > 0 ? `${entry.answered_count} answers` : 'No answers' }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                            {{ new Date(entry.submitted_at || entry.updated_at).toLocaleDateString() }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Positions -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-body mb-4">Top Leaderboard Positions</h3>
                        <div v-if="!leaderboardPositions || leaderboardPositions.length === 0" class="text-center py-6 text-muted">
                            No leaderboard entries yet
                        </div>
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="position in leaderboardPositions" :key="position.id" class="border border-border rounded-lg p-4 bg-surface-elevated">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-semibold text-body">{{ position.event?.name || 'Unknown Event' }}</h4>
                                    <span class="text-lg font-bold text-success">#{{ position.rank }}</span>
                                </div>
                                <p class="text-sm text-muted">Group: {{ position.group?.name || 'N/A' }}</p>
                                <p class="text-sm text-body mt-2">
                                    Score: <span class="font-semibold">{{ position.percentage?.toFixed(1) || 0 }}%</span>
                                    ({{ position.total_score }}/{{ position.possible_points }})
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Change Modal -->
        <Modal :show="showRoleModal" max-width="md" @close="showRoleModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-body mb-4">Change User Role</h3>
                <form @submit.prevent="updateRole">
                    <Select
                        v-model="roleForm.role"
                        label="Select Role"
                        :options="roleOptions"
                        required
                    />
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" @click="showRoleModal = false">
                            Cancel
                        </Button>
                        <Button variant="primary" type="submit" :loading="roleForm.processing">
                            Update Role
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Badge from '@/Components/PropOff/Base/Badge.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';
import Modal from '@/Components/PropOff/Base/Modal.vue';
import Select from '@/Components/PropOff/Form/Select.vue';

const props = defineProps({
    user: Object,
    stats: Object,
    recentEntries: Array,
    leaderboardPositions: Array,
});

const showRoleModal = ref(false);
const roleForm = useForm({
    role: props.user.role,
});

const roleOptions = [
    { value: 'user', label: 'User' },
    { value: 'admin', label: 'Admin' },
    { value: 'manager', label: 'Manager' },
];

const updateRole = () => {
    roleForm.post(route('propoff.admin.users.updateRole', props.user.id), {
        onSuccess: () => {
            showRoleModal.value = false;
        },
    });
};

const confirmDelete = () => {
    if (confirm(`Are you sure you want to delete ${props.user.name}? This action cannot be undone.`)) {
        router.delete(route('propoff.admin.users.destroy', props.user.id));
    }
};
</script>
