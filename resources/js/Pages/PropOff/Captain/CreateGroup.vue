<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import Button from '@/Components/Base/Button.vue';
import TextField from '@/Components/Form/TextField.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import Radio from '@/Components/Form/Radio.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import Card from '@/Components/Base/Card.vue';
import Badge from '@/Components/Base/Badge.vue';
import Icon from '@/Components/Base/Icon.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    invitation: {
        type: Object,
        required: true,
    },
    isGuest: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    name: '',
    description: '',
    grading_source: 'captain',
    entry_cutoff: '',
    captain_name: '',
    captain_email: '',
    captain_password: '',
    captain_password_confirmation: '',
});

const submit = () => {
    form.post(route('propoff.captain.groups.store', [props.event.id, props.invitation.token]));
};

</script>

<template>
    <Head title="Create Group" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="`Create Group for ${event.name}`"
            />
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <!-- Invitation Info Banner -->
                <div class="bg-info/10 border border-info/30 rounded-lg p-6 mb-6">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-info/20 rounded-full flex items-center justify-center">
                            <Icon name="trophy" class="text-info" size="lg" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-info mb-2">{{ event.name }}</h3>
                            <p class="text-sm text-body mb-3 max-w-3xl">
                                You've been invited to play PropOff and to create your own group. This makes you the captain of your group for this event. That means you can add/remove questions, set the correct answers (if you choose that option), send an invite for others to join your group, and promote anyone else to be a captain.
                            </p>
                            <div class="flex flex-wrap gap-3 text-sm">
                                <Badge v-if="invitation.max_uses" variant="info-soft">
                                    <Icon name="users" size="xs" class="mr-1" />
                                    {{ invitation.times_used }} / {{ invitation.max_uses }} uses
                                </Badge>
                                <Badge v-if="invitation.expires_at" variant="warning-soft">
                                    <Icon name="clock" size="xs" class="mr-1" />
                                    Expires: {{ new Date(invitation.expires_at).toLocaleString() }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create Group Form -->
                <Card>
                    <form @submit.prevent="submit">
                        <!-- Two column layout on desktop -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Left Column: Captain Info (guests) or Group Info -->
                            <div>
                                <!-- Captain Info (for guests only) -->
                                <div v-if="isGuest" class="mb-8">
                                    <h3 class="text-lg font-semibold text-body mb-4 flex items-center gap-2">
                                        <Icon name="user" class="text-primary" />
                                        Your Information
                                    </h3>

                                    <div class="space-y-4">
                                        <TextField
                                            id="captain_name"
                                            v-model="form.captain_name"
                                            type="text"
                                            label="Your Name"
                                            :error="form.errors.captain_name"
                                            required
                                            autofocus
                                            placeholder="Enter your name"
                                        />

                                        <TextField
                                            id="captain_email"
                                            v-model="form.captain_email"
                                            type="email"
                                            label="Your Email (Optional)"
                                            :error="form.errors.captain_email"
                                            placeholder="your.email@example.com"
                                        />

                                        <!-- Password fields - shown when email is provided -->
                                        <template v-if="form.captain_email">
                                            <TextField
                                                id="captain_password"
                                                v-model="form.captain_password"
                                                type="password"
                                                label="Password (Optional)"
                                                :error="form.errors.captain_password"
                                                placeholder="Create a password"
                                                hint="Add a password to login easily next time"
                                            />

                                            <TextField
                                                v-if="form.captain_password"
                                                id="captain_password_confirmation"
                                                v-model="form.captain_password_confirmation"
                                                type="password"
                                                label="Confirm Password"
                                                :error="form.errors.captain_password_confirmation"
                                                placeholder="Confirm your password"
                                            />
                                        </template>

                                        <div class="p-4 bg-primary/10 border border-primary/30 rounded-lg">
                                            <p class="text-sm text-primary font-medium mb-2 flex items-center gap-2">
                                                <Icon name="circle-info" size="sm" />
                                                Why create an account?
                                            </p>
                                            <ul class="text-sm text-primary/80 space-y-1 ml-6 list-disc">
                                                <li>Login easily with your email and password</li>
                                                <li>Track your captain history across multiple events</li>
                                                <li>View all your past entries and groups in one place</li>
                                                <li>Skip it if this is a one-time event</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Group Info -->
                                <div>
                                    <h3 class="text-lg font-semibold text-body mb-4 flex items-center gap-2">
                                        <Icon name="users" class="text-primary" />
                                        Group Information
                                    </h3>

                                    <div class="space-y-4">
                                        <TextField
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            label="Group Name"
                                            :error="form.errors.name"
                                            required
                                            :autofocus="!isGuest"
                                            placeholder="Enter your group name"
                                        />

                                        <TextArea
                                            id="description"
                                            v-model="form.description"
                                            label="Description (Optional)"
                                            :error="form.errors.description"
                                            :rows="3"
                                            placeholder="Enter a description for your group"
                                        />

                                        <DatePicker
                                            v-model="form.entry_cutoff"
                                            type="datetime"
                                            label="Entry Cutoff (Optional)"
                                            :error="form.errors.entry_cutoff"
                                            placeholder="MM/DD/YYYY HH:MM AM/PM"
                                            hint="Set a deadline for when members can no longer submit entries. You can skip this for now and manually lock entries later from your group hub."
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Grading Source -->
                            <div>
                                <h3 class="text-lg font-semibold text-body mb-4 flex items-center gap-2">
                                    <Icon name="clipboard-check" class="text-primary" />
                                    Grading Source
                                </h3>
                                <p class="text-sm text-muted mb-4">
                                    Choose how answers will be graded for your group.
                                </p>

                                <div class="space-y-3">
                                    <Radio
                                        v-model="form.grading_source"
                                        name="grading_source"
                                        value="captain"
                                        label="Captain Sets Answers"
                                        description="You set the correct answers and grade entries in real-time. Best for live events where you want immediate scoring."
                                    />
                                    <Radio
                                        v-model="form.grading_source"
                                        name="grading_source"
                                        value="admin"
                                        label="Use Admin Answers"
                                        description="The website admin sets answers after the event ends. Your group will be graded based on the admin's answers."
                                    />
                                </div>
                                <p v-if="form.errors.grading_source" class="mt-2 text-sm text-danger">
                                    {{ form.errors.grading_source }}
                                </p>

                                <!-- Tips Card -->
                                <div class="mt-6 p-4 bg-surface-elevated border border-border rounded-lg">
                                    <h4 class="text-sm font-semibold text-body mb-2 flex items-center gap-2">
                                        <Icon name="lightbulb" class="text-warning" size="sm" />
                                        Tips for Captains
                                    </h4>
                                    <ul class="text-sm text-muted space-y-2">
                                        <li class="flex items-start gap-2">
                                            <Icon name="check" class="text-success mt-0.5" size="xs" />
                                            Share your group's invite link to add members
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <Icon name="check" class="text-success mt-0.5" size="xs" />
                                            You can add custom questions for your group
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <Icon name="check" class="text-success mt-0.5" size="xs" />
                                            Promote other members to captain if needed
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end pt-6 mt-6 border-t border-border">
                            <Button
                                type="submit"
                                variant="primary"
                                size="lg"
                                :loading="form.processing"
                            >
                                <Icon name="users" class="mr-2" size="sm" />
                                Create Group & Become Captain
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
