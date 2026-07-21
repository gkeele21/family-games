<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/Components/Base/Button.vue';
import Card from '@/Components/Base/Card.vue';
import Icon from '@/Components/Base/Icon.vue';
import TextField from '@/Components/Form/TextField.vue';
import Select from '@/Components/Form/Select.vue';
import Radio from '@/Components/Form/Radio.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';

const props = defineProps({
    group: {
        type: Object,
        default: null,
    },
    events: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => !!props.group);

const form = useForm({
    name: props.group?.name || '',
    code: props.group?.code || '',
    event_id: '',
    grading_source: 'admin',
});

const submit = () => {
    if (isEditing.value) {
        form.patch(route('propoff.admin.groups.update', props.group.id));
    } else {
        form.post(route('propoff.admin.groups.store'));
    }
};

const eventOptions = computed(() => {
    return props.events.map(event => ({
        value: event.id,
        label: `${event.name} (${new Date(event.event_date).toLocaleDateString()})`,
    }));
});

const cancelRoute = computed(() => {
    if (isEditing.value) {
        return route('propoff.admin.groups.show', props.group.id);
    }
    return route('propoff.admin.groups.index');
});

const breadcrumbs = computed(() => {
    if (isEditing.value) {
        return [
            { label: 'Groups', href: route('propoff.admin.groups.index') },
            { label: props.group.name, href: route('propoff.admin.groups.show', props.group.id) },
            { label: 'Edit' },
        ];
    }
    return [
        { label: 'Groups', href: route('propoff.admin.groups.index') },
        { label: 'Create' },
    ];
});
</script>

<template>
    <Head :title="isEditing ? 'Edit Group' : 'Create Group'" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="isEditing ? 'Edit Group' : 'Create New Group'"
                :subtitle="isEditing ? undefined : 'Set up a new group'"
                :crumbs="breadcrumbs"
            >
                <template v-if="isEditing" #metadata>
                    <span class="font-medium text-body">{{ group.name }}</span>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Name -->
                        <TextField
                            v-model="form.name"
                            label="Group Name"
                            :error="form.errors.name"
                            :hint="isEditing ? 'The display name for this group' : 'Choose a descriptive name for your group (e.g., &quot;Friends&quot;, &quot;Family&quot;, &quot;Office League&quot;)'"
                            required
                            autofocus
                        />

                        <!-- Code (Edit only) -->
                        <TextField
                            v-if="isEditing"
                            v-model="form.code"
                            label="Group Code"
                            :error="form.errors.code"
                            hint="Members use this code to join the group. Only uppercase letters, numbers, and hyphens allowed."
                            class="uppercase"
                            required
                            maxlength="50"
                            pattern="[A-Z0-9-]+"
                        />

                        <!-- Event (Create only) -->
                        <Select
                            v-if="!isEditing"
                            v-model="form.event_id"
                            label="Event"
                            :options="eventOptions"
                            :error="form.errors.event_id"
                            placeholder="Select an event..."
                            hint="Select the event this group will participate in"
                            required
                        />

                        <!-- Grading Source (Create only) -->
                        <div v-if="!isEditing">
                            <label class="block text-sm font-semibold text-muted mb-3">Grading Mode</label>
                            <div class="space-y-3">
                                <Radio
                                    v-model="form.grading_source"
                                    name="grading_source"
                                    value="admin"
                                    label="Admin Grading"
                                    description="Use admin-set event answers for grading"
                                />
                                <Radio
                                    v-model="form.grading_source"
                                    name="grading_source"
                                    value="captain"
                                    label="Captain Grading"
                                    description="Captain sets correct answers for their group"
                                />
                            </div>
                            <p v-if="form.errors.grading_source" class="text-danger text-sm mt-2">
                                {{ form.errors.grading_source }}
                            </p>
                        </div>

                        <!-- Info Box (Create only) -->
                        <div v-if="!isEditing" class="bg-info/10 border border-info/30 rounded-lg p-4">
                            <h4 class="font-semibold text-info mb-2 flex items-center gap-2">
                                <Icon name="circle-info" size="sm" />
                                What happens next?
                            </h4>
                            <ul class="text-sm text-info/80 space-y-1 ml-6 list-disc">
                                <li>A unique group code will be automatically generated</li>
                                <li>You can add members manually or share the group code</li>
                                <li>Members can join using the group code</li>
                                <li>You can generate event invitations for this group</li>
                            </ul>
                        </div>

                        <!-- Warning Box (Edit only) -->
                        <div v-if="isEditing" class="bg-warning/10 border border-warning/30 rounded-lg p-4">
                            <h4 class="font-semibold text-warning mb-2 flex items-center gap-2">
                                <Icon name="triangle-exclamation" size="sm" />
                                Important
                            </h4>
                            <ul class="text-sm text-warning/80 space-y-1 ml-6 list-disc">
                                <li>Changing the group code will invalidate the old code</li>
                                <li>Members will need the new code to join</li>
                                <li>Existing members will not be affected</li>
                            </ul>
                        </div>

                        <!-- Group Info (Edit only) -->
                        <div v-if="isEditing" class="bg-surface-inset rounded-lg p-4">
                            <h4 class="text-sm font-medium text-body mb-2">Group Information</h4>
                            <dl class="text-sm text-muted space-y-1">
                                <div class="flex justify-between">
                                    <dt>Created:</dt>
                                    <dd class="text-body">{{ new Date(group.created_at).toLocaleDateString() }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-between pt-4 border-t border-border">
                            <Link
                                :href="cancelRoute"
                                class="text-muted hover:text-body transition-colors"
                            >
                                Cancel
                            </Link>
                            <Button
                                variant="primary"
                                type="submit"
                                :loading="form.processing"
                            >
                                <Icon :name="isEditing ? 'check' : 'users'" class="mr-2" size="sm" />
                                {{ isEditing ? 'Update Group' : 'Create Group' }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
