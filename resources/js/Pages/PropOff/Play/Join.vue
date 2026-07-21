<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/PropOffGuestLayout.vue';
import Button from '@/Components/Base/Button.vue';
import TextField from '@/Components/Form/TextField.vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    group: Object,
    step: {
        type: String,
        default: 'name',
    },
    verifyEntry: Object,
});

const page = usePage();

// Track if user rejected a verification (to show hint)
const showNameTakenHint = ref(false);
const rejectedName = ref('');

// Local override for step (when user clicks "No, choose different name")
const localStep = ref(null);

// Get flash data for step navigation
const currentStep = computed(() => {
    // Local override takes priority
    if (localStep.value) {
        return localStep.value;
    }
    if (page.props.flash?.step) {
        return page.props.flash.step;
    }
    return props.step;
});

const flashVerifyEntry = computed(() => page.props.flash?.verifyEntry || props.verifyEntry);

// Form for name step
const nameForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// Form for verify step
const verifyForm = useForm({
    name: '',
    verified: false,
});

// Submit name
const submitName = () => {
    // Clear the hint when submitting a new name
    if (nameForm.name !== rejectedName.value) {
        showNameTakenHint.value = false;
    }
    // Clear local step override when submitting
    localStep.value = null;
    nameForm.post(route('propoff.play.join.process', { code: props.group.code }));
};

// Verify and continue as existing user
const verifyAndContinue = () => {
    verifyForm.name = flashVerifyEntry.value?.name || '';
    verifyForm.verified = true;
    verifyForm.post(route('propoff.play.join.process', { code: props.group.code }));
};

// Reject verification - go back to name input with hint
const chooseNewName = () => {
    rejectedName.value = flashVerifyEntry.value?.name || '';
    showNameTakenHint.value = true;
    nameForm.name = '';
    // Use local override to go back to name step
    localStep.value = 'name';
};

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Enter ${group.name}`" />

    <GuestLayout>
        <div class="max-w-md mx-auto">
            <!-- Step 1: Name Input -->
            <div v-if="currentStep === 'name'" class="bg-surface border border-border rounded-2xl p-5 sm:p-8">
                <h1 class="text-2xl font-bold text-body mb-2">Enter {{ group.name }}</h1>
                <p class="text-muted mb-6">Enter your name to continue</p>

                <!-- Hint when name was taken -->
                <div v-if="showNameTakenHint" class="mb-4 p-3 bg-warning/10 border border-warning/30 rounded-lg">
                    <p class="text-sm text-warning">
                        "{{ rejectedName }}" is already taken. Please choose a different name or add your last initial (e.g., "{{ rejectedName.split(' ')[0] }} T.").
                    </p>
                </div>

                <form @submit.prevent="submitName" class="space-y-4">
                    <TextField
                        v-model="nameForm.name"
                        label="What's your name?"
                        placeholder="Enter your name"
                        :error="nameForm.errors.name"
                        autofocus
                        required
                    />

                    <!-- Optional account creation -->
                    <div class="pt-4 border-t border-border">
                        <p class="text-sm text-muted mb-4">
                            Want to create an account? Add your email and password to login easily next time.
                        </p>

                        <div class="space-y-4">
                            <TextField
                                v-model="nameForm.email"
                                type="email"
                                label="Email (Optional)"
                                placeholder="your.email@example.com"
                                :error="nameForm.errors.email"
                            />

                            <template v-if="nameForm.email">
                                <TextField
                                    v-model="nameForm.password"
                                    type="password"
                                    label="Password (Optional)"
                                    placeholder="Create a password"
                                    :error="nameForm.errors.password"
                                    hint="At least 8 characters"
                                />

                                <TextField
                                    v-if="nameForm.password"
                                    v-model="nameForm.password_confirmation"
                                    type="password"
                                    label="Confirm Password"
                                    placeholder="Confirm your password"
                                    :error="nameForm.errors.password_confirmation"
                                />
                            </template>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        size="lg"
                        class="w-full mt-6"
                        :loading="nameForm.processing"
                    >
                        Continue
                        <Icon name="arrow-right" class="ml-2" size="sm" />
                    </Button>
                </form>

                <div class="mt-6 pt-6 border-t border-border text-center text-sm text-muted">
                    Already have an account?
                    <Link :href="route('login')" class="text-primary hover:underline">Log in</Link>
                </div>
            </div>

            <!-- Step 2: Verify -->
            <div v-else-if="currentStep === 'verify'" class="bg-surface border border-border rounded-2xl p-5 sm:p-8">
                <h1 class="text-2xl font-bold text-body mb-2">Is this you?</h1>
                <p class="text-muted mb-6">
                    We found an existing entry for "{{ flashVerifyEntry?.name }}"
                </p>

                <div class="bg-surface-elevated border border-border rounded-xl p-5 mb-6">
                    <div class="font-semibold text-body mb-1">{{ flashVerifyEntry?.name }}</div>
                    <div class="text-muted">
                        {{ flashVerifyEntry?.answered }} of {{ flashVerifyEntry?.total }} questions answered
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <Button
                        variant="primary"
                        class="flex-1"
                        :loading="verifyForm.processing"
                        @click="verifyAndContinue"
                    >
                        Yes, that's me
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1"
                        @click="chooseNewName"
                    >
                        No, different name
                    </Button>
                </div>
            </div>

            <!-- Event Info -->
            <div v-if="group.event" class="mt-4 text-center text-sm text-muted">
                {{ group.event.name }}
                <span v-if="group.event.event_date"> • {{ formatDate(group.event.event_date) }}</span>
            </div>
        </div>
    </GuestLayout>
</template>
