<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/Base/Button.vue';
import Icon from '@/Components/Base/Icon.vue';
import TextField from '@/Components/Form/TextField.vue';
import Select from '@/Components/Form/Select.vue';

const roleFilterOptions = [
    { value: 'all', label: 'All Roles' },
    { value: 'manager', label: 'Manager' },
    { value: 'admin', label: 'Admin' },
    { value: 'user', label: 'User' },
];

const roleOptions = [
    { value: 'user', label: 'User' },
    { value: 'admin', label: 'Admin' },
    { value: 'manager', label: 'Manager' },
];

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || 'all');
const selectedUsers = ref([]);

const filterUsers = () => {
    router.get(route('propoff.admin.users.index'), {
        search: search.value,
        role: roleFilter.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const updateRole = (user, newRole) => {
    if (confirm(`Change ${user.name}'s role to ${newRole}?`)) {
        router.post(route('propoff.admin.users.updateRole', user.id), { role: newRole });
    }
};

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
        router.delete(route('propoff.admin.users.destroy', user.id));
    }
};

const bulkDelete = () => {
    if (selectedUsers.value.length === 0) {
        alert('Please select users to delete');
        return;
    }
    if (confirm(`Delete ${selectedUsers.value.length} users? This action cannot be undone.`)) {
        router.post(route('propoff.admin.users.bulkDelete'), {
            user_ids: selectedUsers.value
        }, {
            onSuccess: () => {
                selectedUsers.value = [];
            }
        });
    }
};

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedUsers.value = props.users.data.map(u => u.id);
    } else {
        selectedUsers.value = [];
    }
};

const exportCSV = () => {
    window.location.href = route('propoff.admin.users.exportCSV', {
        role: roleFilter.value
    });
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
    <Head title="Manage Users" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="Users">
                <template #actions>
                    <Button
                        v-if="selectedUsers.length > 0"
                        variant="danger"
                        size="sm"
                        icon="trash"
                        @click="bulkDelete"
                    >
                        Delete Selected ({{ selectedUsers.length }})
                    </Button>
                    <Button
                        variant="muted"
                        size="sm"
                        icon="file-arrow-down"
                        @click="exportCSV"
                    >
                        Export CSV
                    </Button>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-surface shadow-sm sm:rounded-lg mb-6 border border-border">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <TextField
                                    v-model="search"
                                    placeholder="Search by name or email..."
                                    icon-left="magnifying-glass"
                                    @input="filterUsers"
                                />
                            </div>
                            <Select
                                v-model="roleFilter"
                                :options="roleFilterOptions"
                                @change="filterUsers"
                            />
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-surface-elevated">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input
                                            type="checkbox"
                                            @change="toggleSelectAll"
                                            :checked="selectedUsers.length === users.data.length && users.data.length > 0"
                                            class="rounded border-border bg-surface-inset text-primary focus-glow-sm"
                                        />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Entries
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Groups
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-subtle uppercase tracking-wider">
                                        Joined
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-subtle uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-surface divide-y divide-border">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-surface-elevated">
                                    <td class="px-6 py-4">
                                        <input
                                            v-model="selectedUsers"
                                            :value="user.id"
                                            type="checkbox"
                                            class="rounded border-border bg-surface-inset text-primary focus-glow-sm"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Link
                                            :href="route('propoff.admin.users.show', user.id)"
                                            class="text-sm font-medium text-body hover:text-primary/80"
                                        >
                                            {{ user.name }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            v-if="user.role === 'guest'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-elevated text-muted"
                                        >
                                            Guest
                                        </span>
                                        <select
                                            v-else
                                            :value="user.role"
                                            @change="updateRole(user, $event.target.value)"
                                            class="text-sm bg-surface-elevated border border-border text-body rounded-md px-2 py-1 pr-8 focus:outline-none focus:border-primary cursor-pointer"
                                        >
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                            <option value="manager">Manager</option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ user.entries_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ user.groups_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            :href="route('propoff.admin.users.show', user.id)"
                                            class="text-primary hover:text-primary/80 mr-3"
                                        >
                                            View
                                        </Link>
                                        <button
                                            @click="deleteUser(user)"
                                            class="text-danger hover:text-danger/80"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-muted">
                                        No users found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="users.links.length > 3" class="bg-surface px-4 py-3 border-t border-border sm:px-6">
                        <div class="flex justify-center">
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <component
                                    v-for="(link, index) in users.links"
                                    :key="index"
                                    :is="link.url ? Link : 'span'"
                                    :href="link.url"
                                    class="relative inline-flex items-center px-4 py-2 border border-border text-sm font-medium"
                                    :class="{
                                        'bg-primary/10 border-primary text-primary': link.active,
                                        'bg-surface text-body hover:bg-surface-elevated': !link.active && link.url,
                                        'bg-surface-elevated text-muted cursor-not-allowed': !link.url,
                                        'rounded-l-md': index === 0,
                                        'rounded-r-md': index === users.links.length - 1,
                                    }"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
