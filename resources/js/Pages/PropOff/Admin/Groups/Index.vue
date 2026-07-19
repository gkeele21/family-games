<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Card from '@/Components/PropOff/Base/Card.vue';
import Badge from '@/Components/PropOff/Base/Badge.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';
import TextField from '@/Components/PropOff/Form/TextField.vue';
import DataTable from '@/Components/PropOff/Base/DataTable.vue';
import Confirm from '@/Components/PropOff/Feedback/Confirm.vue';

const props = defineProps({
    groups: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const selectedGroups = ref([]);
const showDeleteConfirm = ref(false);
const groupToDelete = ref(null);
const showBulkDeleteConfirm = ref(false);

const filterGroups = () => {
    router.get(route('propoff.admin.groups.index'), {
        search: search.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const confirmDelete = (group) => {
    groupToDelete.value = group;
    showDeleteConfirm.value = true;
};

const deleteGroup = () => {
    if (groupToDelete.value) {
        router.delete(route('propoff.admin.groups.destroy', groupToDelete.value.id), {
            onSuccess: () => {
                showDeleteConfirm.value = false;
                groupToDelete.value = null;
            },
        });
    }
};

const confirmBulkDelete = () => {
    if (selectedGroups.value.length === 0) return;
    showBulkDeleteConfirm.value = true;
};

const bulkDelete = () => {
    router.post(route('propoff.admin.groups.bulkDelete'), {
        group_ids: selectedGroups.value
    }, {
        onSuccess: () => {
            selectedGroups.value = [];
            showBulkDeleteConfirm.value = false;
        }
    });
};

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedGroups.value = props.groups.data.map(g => g.id);
    } else {
        selectedGroups.value = [];
    }
};

const exportCSV = () => {
    window.location.href = route('propoff.admin.groups.exportCSV');
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};
</script>

<template>
    <Head title="Manage Groups" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="Groups">
                <template #actions>
                    <Button
                        v-if="selectedGroups.length > 0"
                        variant="danger"
                        size="sm"
                        @click="confirmBulkDelete"
                    >
                        <Icon name="trash" class="mr-2" size="sm" />
                        Delete Selected ({{ selectedGroups.length }})
                    </Button>
                    <Button
                        variant="muted"
                        size="sm"
                        @click="exportCSV"
                    >
                        <Icon name="file-arrow-down" class="mr-2" size="sm" />
                        Export CSV
                    </Button>
                    <Link :href="route('propoff.admin.groups.create')">
                        <Button variant="primary" size="sm">
                            <Icon name="plus" class="mr-2" size="sm" />
                            Create Group
                        </Button>
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search -->
                <Card class="mb-6">
                    <TextField
                        v-model="search"
                        @input="filterGroups"
                        label="Search"
                        icon="magnifying-glass"
                        placeholder="Search by name or code..."
                    />
                </Card>

                <!-- Groups Table -->
                <Card :body-padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-surface-header">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input
                                            type="checkbox"
                                            @change="toggleSelectAll"
                                            :checked="selectedGroups.length === groups.data.length && groups.data.length > 0"
                                            class="rounded border-border text-primary bg-surface-inset focus:ring-primary focus:ring-offset-0"
                                        />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">
                                        Code
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">
                                        Members
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">
                                        Entries
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider">
                                        Created
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-surface divide-y divide-border">
                                <tr v-for="group in groups.data" :key="group.id" class="hover:bg-surface-overlay transition-colors">
                                    <td class="px-6 py-4">
                                        <input
                                            v-model="selectedGroups"
                                            :value="group.id"
                                            type="checkbox"
                                            class="rounded border-border text-primary bg-surface-inset focus:ring-primary focus:ring-offset-0"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Link
                                            :href="route('propoff.admin.groups.show', group.id)"
                                            class="text-sm font-medium text-body hover:text-primary transition-colors"
                                        >
                                            {{ group.name }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge variant="default" size="sm">
                                            {{ group.code }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        <div class="flex items-center gap-1">
                                            <Icon name="users" size="sm" class="text-subtle" />
                                            {{ group.users_count }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ group.entries_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ formatDate(group.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                        <Link
                                            :href="route('propoff.admin.groups.show', group.id)"
                                            class="text-primary hover:text-primary-hover transition-colors"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            :href="route('propoff.admin.groups.edit', group.id)"
                                            class="text-primary hover:text-primary-hover transition-colors"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="confirmDelete(group)"
                                            class="text-danger hover:text-danger/80 transition-colors"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="groups.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-muted">
                                        <Icon name="users" size="2x" class="text-subtle mb-2" />
                                        <p>No groups found</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="groups.links.length > 3" class="px-4 py-3 border-t border-border">
                        <div class="flex justify-center">
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <component
                                    v-for="(link, index) in groups.links"
                                    :key="index"
                                    :is="link.url ? Link : 'span'"
                                    :href="link.url"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors"
                                    :class="{
                                        'bg-primary/10 border-primary text-primary': link.active,
                                        'bg-surface border-border text-body hover:bg-surface-overlay': !link.active && link.url,
                                        'bg-surface-inset border-border text-subtle cursor-not-allowed': !link.url,
                                        'rounded-l-md': index === 0,
                                        'rounded-r-md': index === groups.links.length - 1,
                                    }"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <Confirm
            :show="showDeleteConfirm"
            title="Delete Group?"
            :message="`Are you sure you want to delete '${groupToDelete?.name}'? This action cannot be undone.`"
            variant="danger"
            icon="triangle-exclamation"
            @confirm="deleteGroup"
            @close="showDeleteConfirm = false"
        />

        <!-- Bulk Delete Confirmation -->
        <Confirm
            :show="showBulkDeleteConfirm"
            title="Delete Selected Groups?"
            :message="`Are you sure you want to delete ${selectedGroups.length} groups? This action cannot be undone.`"
            variant="danger"
            icon="triangle-exclamation"
            @confirm="bulkDelete"
            @close="showBulkDeleteConfirm = false"
        />
    </AuthenticatedLayout>
</template>
