<template>
    <Head title="Edit Group" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Edit Group"
                :subtitle="`Edit ${group.name}`"
                :crumbs="[
                    { label: 'Home', href: route('propoff.home') },
                    { label: 'Groups', href: route('propoff.groups.index') },
                    { label: group.name, href: route('propoff.groups.questions', group.id) },
                    { label: 'Edit' }
                ]"
            />
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Group Name -->
                        <TextField
                            v-model="form.name"
                            label="Group Name"
                            placeholder="My Awesome Group"
                            :error="form.errors.name"
                            required
                        />

                        <!-- Description -->
                        <TextArea
                            v-model="form.description"
                            label="Description (Optional)"
                            placeholder="Enter a description for your group"
                            :rows="3"
                            :error="form.errors.description"
                        />

                        <!-- Grading Source (conditionally editable) -->
                        <div v-if="canChangeGradingSource" class="space-y-3">
                            <FormLabel required>Grading Source</FormLabel>
                            <Radio
                                v-model="form.grading_source"
                                name="grading_source"
                                value="captain"
                                label="Captain Grading"
                                description="You set the correct answers and grade entries in real-time."
                            />
                            <Radio
                                v-model="form.grading_source"
                                name="grading_source"
                                value="admin"
                                label="Admin Grading"
                                description="The admin sets answers after the event ends. Your group will be graded based on admin answers."
                            />
                            <p v-if="form.errors.grading_source" class="text-sm text-danger">{{ form.errors.grading_source }}</p>
                        </div>

                        <!-- Entry Cutoff -->
                        <DatePicker
                            v-model="form.entry_cutoff"
                            type="datetime"
                            label="Entry Cutoff (Optional)"
                            :error="form.errors.entry_cutoff"
                            hint="Set a deadline for when members can no longer submit or edit entries. Leave blank to use the event's lock date."
                        />

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                            <Link :href="route('propoff.groups.questions', group.id)">
                                <Button variant="outline">Cancel</Button>
                            </Link>
                            <Button
                                type="submit"
                                variant="primary"
                                :loading="form.processing"
                            >
                                Save
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Base/PageHeader.vue';
import Card from '@/Components/Base/Card.vue';
import Button from '@/Components/Base/Button.vue';
import TextField from '@/Components/Form/TextField.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import Radio from '@/Components/Form/Radio.vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
    canChangeGradingSource: {
        type: Boolean,
        default: false,
    },
});

// Format datetime for datetime-local input (YYYY-MM-DDTHH:MM)
const formatDatetimeLocal = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
    name: props.group.name,
    description: props.group.description || '',
    grading_source: props.group.grading_source || 'captain',
    entry_cutoff: formatDatetimeLocal(props.group.entry_cutoff || props.group.event?.lock_date),
});

const submit = () => {
    form.patch(route('propoff.groups.update', props.group.id));
};
</script>
