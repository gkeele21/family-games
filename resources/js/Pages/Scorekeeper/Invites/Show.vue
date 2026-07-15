<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    invite: {
        token: string;
        household_name: string;
        inviter_name: string;
        email: string;
        role: string;
        expires_at: string;
    };
}>();

const page = usePage();

const currentUser = computed(() => page.props.auth?.user ?? null);
const emailMatches = computed(
    () =>
        currentUser.value &&
        currentUser.value.email?.toLowerCase() ===
            props.invite.email.toLowerCase(),
);

const form = useForm({});
const accept = () => {
    form.post(route('scorekeeper.invites.accept', props.invite.token));
};
</script>

<template>
    <Head title="Household invitation" />

    <GuestLayout>
        <div class="space-y-4 text-gray-700">
            <h1 class="text-lg font-semibold text-gray-900">
                You're invited
            </h1>
            <p>
                <strong>{{ invite.inviter_name }}</strong> invited you to join
                <strong>{{ invite.household_name }}</strong> as
                {{ invite.role }}.
            </p>

            <!-- Signed in with the invited email -->
            <template v-if="emailMatches">
                <PrimaryButton
                    class="w-full justify-center"
                    :disabled="form.processing"
                    @click="accept"
                >
                    Accept invitation
                </PrimaryButton>
            </template>

            <!-- Signed in as a different account -->
            <template v-else-if="currentUser">
                <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                    This invitation was sent to
                    <strong>{{ invite.email }}</strong>, but you're signed in as
                    {{ currentUser.email }}. Sign in with the invited email to
                    accept.
                </p>
            </template>

            <!-- Not signed in -->
            <template v-else>
                <p class="text-sm text-gray-500">
                    Sign in (or create an account) with
                    <strong>{{ invite.email }}</strong> to accept, then reopen
                    this link.
                </p>
                <div class="flex gap-3">
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
                        >Log in</Link
                    >
                    <Link
                        :href="route('register')"
                        class="text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
                        >Register</Link
                    >
                </div>
            </template>
        </div>
    </GuestLayout>
</template>
