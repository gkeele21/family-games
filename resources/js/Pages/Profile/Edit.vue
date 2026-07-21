<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Base/Card.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import SetPasswordForm from './Partials/SetPasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import AppearanceForm from './Partials/AppearanceForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    hasPassword?: boolean;
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-body">Profile</h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Card>
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </Card>

                <Card>
                    <UpdatePasswordForm v-if="hasPassword !== false" class="max-w-xl" />
                    <SetPasswordForm v-else class="max-w-xl" />
                </Card>

                <Card>
                    <AppearanceForm class="max-w-xl" />
                </Card>

                <Card border-color="danger">
                    <DeleteUserForm class="max-w-xl" />
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
