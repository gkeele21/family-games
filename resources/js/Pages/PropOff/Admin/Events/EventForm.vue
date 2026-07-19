<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import TextField from '@/Components/PropOff/Form/TextField.vue';
import Select from '@/Components/PropOff/Form/Select.vue';
import DatePicker from '@/Components/PropOff/Form/DatePicker.vue';
import Confirm from '@/Components/PropOff/Feedback/Confirm.vue';

const eventTypeOptions = [
    { value: 'GameQuiz', label: 'Game Quiz' },
    { value: 'AmericaSays', label: 'America Says' },
];

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'locked', label: 'Locked' },
    { value: 'completed', label: 'Completed' },
];

const props = defineProps({
    event: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => !!props.event);

const form = useForm({
    name: props.event?.name || '',
    description: props.event?.description || '',
    event_date: props.event?.event_date?.substring(0, 16) || '',
    lock_date: props.event?.lock_date?.substring(0, 16) || '',
    status: props.event?.status || 'draft',
    category: props.event?.category || '',
    event_type: props.event?.event_type || 'GameQuiz',
});

const showDeleteConfirm = ref(false);

const submit = () => {
    if (isEditing.value) {
        form.patch(route('propoff.admin.events.update', props.event.id));
    } else {
        form.post(route('propoff.admin.events.store'));
    }
};

const deleteEvent = () => {
    router.delete(route('propoff.admin.events.destroy', props.event.id));
};

const cancelRoute = computed(() => {
    if (isEditing.value) {
        return route('propoff.admin.events.show', props.event.id);
    }
    return route('propoff.admin.events.index');
});

const breadcrumbs = computed(() => {
    if (isEditing.value) {
        return [
            { text: 'Events', href: route('propoff.admin.events.index') },
            { text: props.event.name, href: route('propoff.admin.events.show', props.event.id) },
            { text: 'Edit' },
        ];
    }
    return [
        { text: 'Events', href: route('propoff.admin.events.index') },
        { text: 'Create' },
    ];
});
</script>

<template>
    <Head :title="isEditing ? `Edit ${event.name}` : 'Create Event'" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="isEditing ? 'Edit Event' : 'Create Event'"
                :crumbs="breadcrumbs"
            >
                <template #actions>
                    <Link :href="cancelRoute">
                        <Button variant="outline" size="sm">
                            Cancel
                        </Button>
                    </Link>
                    <Button
                        v-if="isEditing"
                        variant="danger"
                        size="sm"
                        @click="showDeleteConfirm = true"
                    >
                        Delete
                    </Button>
                    <Button variant="primary" size="sm" @click="submit" :disabled="form.processing">
                        Save
                    </Button>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Name -->
                        <TextField
                            v-model="form.name"
                            label="Event Name"
                            :error="form.errors.name"
                            required
                        />

                        <!-- Description -->
                        <TextField
                            v-model="form.description"
                            label="Description"
                            :error="form.errors.description"
                            multiline
                            :rows="4"
                        />

                        <!-- Event Date -->
                        <DatePicker
                            v-model="form.event_date"
                            label="Event Date"
                            :error="form.errors.event_date"
                            hint="When will this event take place?"
                            enable-time
                            required
                        />

                        <!-- Lock Date -->
                        <DatePicker
                            v-model="form.lock_date"
                            label="Lock Date (Optional)"
                            :error="form.errors.lock_date"
                            hint="Entries cannot be changed after this date"
                            enable-time
                        />

                        <!-- Event Type -->
                        <Select
                            v-model="form.event_type"
                            label="Event Type"
                            :options="eventTypeOptions"
                            :error="form.errors.event_type"
                            hint="Choose the type of game for this event"
                            required
                        />

                        <!-- Category -->
                        <div>
                            <TextField
                                v-model="form.category"
                                label="Category (Optional)"
                                :error="form.errors.category"
                            />
                            <p class="mt-1 text-sm text-muted">
                                <span v-if="form.event_type === 'AmericaSays'">
                                    For America Says: Use "Christmas", "Halloween", or "Sports" for themed styling
                                </span>
                                <span v-else>
                                    Category for organizing events (e.g., Football, Basketball)
                                </span>
                            </p>
                        </div>

                        <!-- Status -->
                        <Select
                            v-model="form.status"
                            label="Status"
                            :options="statusOptions"
                            :error="form.errors.status"
                            hint="Set the current status of the event"
                        />

                        <!-- Status Descriptions (only show on create) -->
                        <div v-if="!isEditing" class="bg-surface-elevated rounded-lg p-4">
                            <h4 class="text-sm font-medium text-body mb-2">Status Guide:</h4>
                            <ul class="text-sm text-muted space-y-1">
                                <li><strong>Draft:</strong> Event is being created, not visible to users</li>
                                <li><strong>Open:</strong> Event is active and accepting entries</li>
                                <li><strong>Locked:</strong> Event is closed for new entries</li>
                                <li><strong>Completed:</strong> Event is finished and results are final</li>
                            </ul>
                        </div>
                    </form>
                </div>

                <!-- Next Steps Card (only show on create) -->
                <div v-if="!isEditing" class="mt-6 bg-primary/10 border border-primary/30 rounded-lg p-4">
                    <h4 class="font-medium text-primary mb-2">Next Steps</h4>
                    <p class="text-sm text-primary">
                        After creating the event, you'll be able to:
                    </p>
                    <ul class="mt-2 text-sm text-primary list-disc list-inside space-y-1">
                        <li>Add questions to your event</li>
                        <li>Set group-specific correct answers</li>
                        <li>Manage event settings and status</li>
                        <li>View entries and statistics</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <Confirm
            v-if="isEditing"
            :show="showDeleteConfirm"
            title="Delete Event?"
            message="This will permanently delete this event and all associated questions and entries. This action cannot be undone."
            confirm-text="Delete"
            variant="danger"
            @confirm="deleteEvent"
            @close="showDeleteConfirm = false"
        />
    </AuthenticatedLayout>
</template>
