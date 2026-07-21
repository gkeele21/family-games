<template>
    <Head title="Create Group" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Create New Group"
                subtitle="Set up your group and become a captain"
                :crumbs="[
                    { label: 'Home', href: route('propoff.home') },
                    { label: 'Groups', href: route('propoff.groups.index') },
                    { label: 'Create' }
                ]"
            />
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Info Banner -->
                <div class="bg-primary/10 border border-primary/30 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-primary mb-2">Become a Captain!</h3>
                    <p class="text-sm text-primary mb-2">
                        By creating a group, you'll become a captain with the ability to customize questions and manage your group.
                    </p>
                    <ul class="text-sm text-primary space-y-1 list-disc list-inside">
                        <li>Customize questions for your group</li>
                        <li>Set correct answers (if using captain grading)</li>
                        <li>Manage group members</li>
                        <li>Track your group's leaderboard</li>
                    </ul>
                </div>

                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <form @submit.prevent="submit" class="p-6">
                        <!-- Event Selection -->
                        <div class="mb-6">
                            <label for="event_id" class="block text-sm font-medium text-body mb-2">
                                Select Event <span class="text-danger">*</span>
                            </label>
                            <select
                                id="event_id"
                                v-model="form.event_id"
                                class="w-full bg-surface-elevated border-border text-body focus:border-primary focus:ring-primary/50 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Choose an event...</option>
                                <option
                                    v-for="event in events"
                                    :key="event.id"
                                    :value="event.id"
                                >
                                    {{ event.name }} - {{ formatDate(event.event_date) }}
                                </option>
                            </select>
                            <p v-if="form.errors.event_id" class="mt-1 text-sm text-danger">{{ form.errors.event_id }}</p>
                            <p class="mt-1 text-sm text-muted">Select the event this group will participate in</p>
                        </div>

                        <!-- Group Name -->
                        <div class="mb-6">
                            <label for="name" class="block text-sm font-medium text-body mb-2">
                                Group Name <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                id="name"
                                placeholder="My Awesome Group"
                                class="w-full bg-surface-elevated border-border text-body placeholder-muted focus:border-primary focus:ring-primary/50 rounded-md shadow-sm"
                                required
                                autofocus
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
                            <p class="mt-1 text-sm text-muted">Choose a unique name for your group</p>
                        </div>

                        <!-- Description -->
                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-body mb-2">
                                Description (Optional)
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="w-full bg-surface-elevated border-border text-body placeholder-muted focus:border-primary focus:ring-primary/50 rounded-md shadow-sm"
                                rows="3"
                                placeholder="Enter a description for your group"
                            ></textarea>
                            <p v-if="form.errors.description" class="mt-1 text-sm text-danger">{{ form.errors.description }}</p>
                        </div>

                        <!-- Grading Source -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-body mb-2">
                                Grading Source <span class="text-danger">*</span>
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-start p-4 border border-border rounded-lg cursor-pointer hover:bg-surface-elevated transition"
                                       :class="{ 'border-primary bg-primary/10': form.grading_source === 'captain' }">
                                    <input
                                        type="radio"
                                        v-model="form.grading_source"
                                        value="captain"
                                        class="mt-1 mr-3"
                                    />
                                    <div>
                                        <div class="font-semibold text-body">Captain Grading</div>
                                        <div class="text-sm text-muted">
                                            You set the correct answers and grade entries in real-time.
                                            Best for live events where you want immediate scoring.
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-start p-4 border border-border rounded-lg cursor-pointer hover:bg-surface-elevated transition"
                                       :class="{ 'border-primary bg-primary/10': form.grading_source === 'admin' }">
                                    <input
                                        type="radio"
                                        v-model="form.grading_source"
                                        value="admin"
                                        class="mt-1 mr-3"
                                    />
                                    <div>
                                        <div class="font-semibold text-body">Admin Grading</div>
                                        <div class="text-sm text-muted">
                                            The admin sets answers after the event ends. Your group will be graded
                                            based on admin answers. Best for events where grading happens later.
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <p v-if="form.errors.grading_source" class="mt-1 text-sm text-danger">{{ form.errors.grading_source }}</p>
                        </div>

                        <!-- Entry Cutoff -->
                        <div class="mb-6">
                            <label for="entry_cutoff" class="block text-sm font-medium text-body mb-2">
                                Entry Cutoff (Optional)
                            </label>
                            <input
                                v-model="form.entry_cutoff"
                                type="datetime-local"
                                id="entry_cutoff"
                                class="w-full bg-surface-elevated border-border text-body focus:border-primary focus:ring-primary/50 rounded-md shadow-sm"
                            />
                            <p v-if="form.errors.entry_cutoff" class="mt-1 text-sm text-danger">{{ form.errors.entry_cutoff }}</p>
                            <p class="mt-1 text-sm text-muted">
                                Set a deadline for when members can no longer submit or edit entries. Leave blank to use the event's lock date.
                            </p>
                        </div>

                        <!-- Public/Private -->
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input
                                    type="checkbox"
                                    v-model="form.is_public"
                                    class="rounded border-border text-primary focus:ring-primary/50"
                                />
                                <span class="ml-2 text-sm text-body">Make this group public (others can find and join)</span>
                            </label>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                            <Link
                                :href="route('propoff.groups.index')"
                                class="px-4 py-2 border border-border rounded-md text-sm font-medium text-body hover:bg-surface-elevated"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary/80 disabled:opacity-50"
                            >
                                <span v-if="form.processing">Creating...</span>
                                <span v-else>Create Group & Become Captain</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Base/PageHeader.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    selectedEventId: {
        type: [Number, String],
        default: null,
    },
});

const form = useForm({
    name: '',
    description: '',
    event_id: props.selectedEventId || '',
    grading_source: 'captain',
    entry_cutoff: '',
    is_public: false,
});

const submit = () => {
    form.post(route('propoff.groups.store'));
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>
