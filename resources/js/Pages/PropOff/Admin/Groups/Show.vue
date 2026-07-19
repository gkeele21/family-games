<template>
    <Head title="Group Details" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="group.name"
                :crumbs="[
                    { label: 'Groups', href: route('propoff.admin.groups.index') },
                    { label: group.name }
                ]"
            >
                <template #metadata>
                    <span>{{ stats.total_members }} members</span>
                    <span class="text-subtle mx-2">•</span>
                    <span>Code: <span class="font-mono font-bold">{{ group.code }}</span></span>
                    <span class="text-subtle mx-2">•</span>
                    <Link :href="route('propoff.admin.groups.edit', group.id)" class="text-primary hover:text-primary-hover transition-colors">
                        Edit
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Group Information -->
                <Card>
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold text-body">{{ group.name }}</h3>
                        <p class="text-muted mt-1">Join Code: <span class="font-mono font-bold text-body">{{ group.code }}</span></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                        <div class="bg-surface-inset p-4 rounded-lg border-t-2 border-primary">
                            <div class="text-sm text-muted">Members</div>
                            <div class="text-2xl font-bold text-primary">{{ stats.total_members }}</div>
                        </div>
                        <div class="bg-surface-inset p-4 rounded-lg border-t-2 border-info">
                            <div class="text-sm text-muted">Total Entries</div>
                            <div class="text-2xl font-bold text-info">{{ stats.total_entries }}</div>
                        </div>
                        <div class="bg-surface-inset p-4 rounded-lg border-t-2 border-warning">
                            <div class="text-sm text-muted">Events Played</div>
                            <div class="text-2xl font-bold text-warning">{{ stats.total_events_played }}</div>
                        </div>
                        <div class="bg-surface-inset p-4 rounded-lg border-t-2 border-success">
                            <div class="text-sm text-muted">Avg Score</div>
                            <div class="text-2xl font-bold text-success">{{ Math.round(stats.average_score) }}%</div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-border">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-muted">Created By</dt>
                                <dd class="mt-1 text-sm text-body">{{ group.created_by?.name || 'Unknown' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted">Created</dt>
                                <dd class="mt-1 text-sm text-body">{{ formatDate(group.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <!-- Members -->
                <Card :body-padding="false">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-body">Members</h3>
                            <Button variant="primary" size="sm" @click="showAddMember = true">
                                <Icon name="user-plus" class="mr-2" size="sm" />
                                Add Member
                            </Button>
                        </div>
                    </template>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-surface-header">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Joined</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-surface divide-y divide-border">
                                <tr v-for="member in (members || [])" :key="member.id" class="hover:bg-surface-overlay transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-body">{{ member.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-muted">{{ member.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge :variant="member.pivot?.is_captain ? 'warning-soft' : 'default'" size="sm">
                                            {{ member.pivot?.is_captain ? 'Captain' : 'Member' }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ formatDate(member.pivot?.joined_at || member.pivot?.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button
                                            @click="confirmRemoveMember(member)"
                                            class="text-danger hover:text-danger/80 transition-colors"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!members || members.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-muted">
                                        <Icon name="users" size="2x" class="text-subtle mb-2" />
                                        <p>No members yet</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- Recent Activity -->
                <Card :body-padding="false">
                    <template #header>
                        <h3 class="text-lg font-semibold text-body">Recent Entries</h3>
                    </template>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-surface-header">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Event</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-surface divide-y divide-border">
                                <tr v-for="entry in (recentEntries || [])" :key="entry.id" class="hover:bg-surface-overlay transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-body">{{ entry.user?.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-body">{{ entry.event?.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-body">{{ Math.round(entry.percentage || 0) }}%</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge :variant="entry.answered_count > 0 ? 'success-soft' : 'warning-soft'" size="sm">
                                            {{ entry.answered_count > 0 ? `${entry.answered_count} answers` : 'No answers' }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ formatDate(entry.submitted_at || entry.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!recentEntries || recentEntries.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-muted">
                                        <Icon name="clipboard-list" size="2x" class="text-subtle mb-2" />
                                        <p>No entries yet</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Add Member Modal -->
        <Modal :show="showAddMember" @close="showAddMember = false" maxWidth="sm">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-body mb-4">Add Member to Group</h3>

                <form @submit.prevent="addMember">
                    <TextField
                        v-model="addMemberForm.email"
                        label="User Email"
                        type="email"
                        :error="addMemberForm.errors.email"
                        placeholder="Enter user's email address"
                        required
                        autofocus
                    />

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-border">
                        <Button type="button" variant="outline" class="!border-primary !text-primary hover:!bg-primary/10" @click="showAddMember = false">
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" :loading="addMemberForm.processing">
                            <Icon name="user-plus" class="mr-2" size="sm" />
                            Add Member
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Remove Member Confirmation -->
        <Confirm
            :show="showRemoveConfirm"
            title="Remove Member?"
            :message="`Are you sure you want to remove '${memberToRemove?.name}' from this group?`"
            variant="danger"
            icon="user-minus"
            @confirm="removeMember"
            @close="showRemoveConfirm = false"
        />
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Card from '@/Components/PropOff/Base/Card.vue';
import Badge from '@/Components/PropOff/Base/Badge.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';
import Modal from '@/Components/PropOff/Base/Modal.vue';
import TextField from '@/Components/PropOff/Form/TextField.vue';
import Confirm from '@/Components/PropOff/Feedback/Confirm.vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';

const props = defineProps({
    group: Object,
    stats: {
        type: Object,
        default: () => ({ total_members: 0, total_entries: 0, total_events_played: 0, average_score: 0 }),
    },
    recentEntries: {
        type: Array,
        default: () => [],
    },
});

// Computed to get members from group.users
const members = computed(() => props.group?.users || []);

const showAddMember = ref(false);
const showRemoveConfirm = ref(false);
const memberToRemove = ref(null);

const addMemberForm = useForm({
    email: '',
});

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const addMember = () => {
    addMemberForm.post(route('propoff.admin.groups.addUser', props.group.id), {
        onSuccess: () => {
            showAddMember.value = false;
            addMemberForm.reset();
        },
    });
};

const confirmRemoveMember = (member) => {
    memberToRemove.value = member;
    showRemoveConfirm.value = true;
};

const removeMember = () => {
    if (memberToRemove.value) {
        router.delete(route('propoff.admin.groups.remove-user', { group: props.group.id, user: memberToRemove.value.id }), {
            onSuccess: () => {
                showRemoveConfirm.value = false;
                memberToRemove.value = null;
            },
        });
    }
};
</script>
