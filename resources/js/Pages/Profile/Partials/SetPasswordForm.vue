<script setup>
import Button from '@/Components/Base/Button.vue';
import TextField from '@/Components/Form/TextField.vue';
import Icon from '@/Components/Base/Icon.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);

const form = useForm({
    password: '',
    password_confirmation: '',
});

const setPassword = () => {
    form.post(route('password.set'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-body">Set Password</h2>

            <p class="mt-1 text-sm text-muted">
                Create a password to login with your email and password instead of using magic links.
            </p>
        </header>

        <!-- Success message -->
        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <div v-if="form.recentlySuccessful" class="mt-4 p-4 bg-success/10 border border-success/30 rounded-lg">
                <p class="text-sm text-success flex items-center gap-2">
                    <Icon name="check-circle" size="sm" />
                    Password set successfully! You can now login with your email and password.
                </p>
            </div>
        </Transition>

        <form @submit.prevent="setPassword" class="mt-6 space-y-6">
            <TextField
                id="password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                label="Password"
                :error="form.errors.password"
                autocomplete="new-password"
                hint="At least 8 characters"
            />

            <TextField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                label="Confirm Password"
                :error="form.errors.password_confirmation"
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    <Icon name="lock" size="sm" class="mr-2" />
                    Set Password
                </Button>
            </div>
        </form>
    </section>
</template>
