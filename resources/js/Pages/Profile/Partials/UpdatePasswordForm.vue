<script setup lang="ts">
import TextField from '@/Components/Form/TextField.vue';
import Button from '@/Components/Base/Button.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) form.reset('password', 'password_confirmation');
            if (form.errors.current_password) form.reset('current_password');
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-body">Update Password</h2>
            <p class="mt-1 text-sm text-muted">
                Ensure your account is using a long, random password to stay secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
            <TextField
                label="Current password"
                type="password"
                v-model="form.current_password"
                :error="form.errors.current_password"
            />
            <TextField
                label="New password"
                type="password"
                v-model="form.password"
                :error="form.errors.password"
            />
            <TextField
                label="Confirm password"
                type="password"
                v-model="form.password_confirmation"
                :error="form.errors.password_confirmation"
            />

            <div class="flex items-center gap-4">
                <Button type="submit" variant="primary" :loading="form.processing">Save</Button>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-muted">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
