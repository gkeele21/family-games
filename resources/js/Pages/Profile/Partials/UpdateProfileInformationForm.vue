<script setup lang="ts">
import TextField from '@/Components/Form/TextField.vue';
import Button from '@/Components/Base/Button.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-body">Profile Information</h2>
            <p class="mt-1 text-sm text-muted">
                Update your account's profile information and email address.
            </p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <TextField label="First name" v-model="form.first_name" :error="form.errors.first_name" required />
                <TextField label="Last name" v-model="form.last_name" :error="form.errors.last_name" required />
            </div>

            <TextField label="Email" type="email" v-model="form.email" :error="form.errors.email" required />

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm text-muted">
                Your email address is unverified.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="font-semibold text-primary hover:underline"
                >
                    Re-send the verification email.
                </Link>
                <p v-show="status === 'verification-link-sent'" class="mt-2 font-medium text-success">
                    A new verification link has been sent to your email address.
                </p>
            </div>

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
