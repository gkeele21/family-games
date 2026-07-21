<script setup lang="ts">
import Modal from '@/Components/Base/Modal.vue';
import Button from '@/Components/Base/Button.vue';
import TextField from '@/Components/Form/TextField.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const confirmingUserDeletion = ref(false);

const form = useForm({
    password: '',
});

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-semibold text-body">Delete Account</h2>
            <p class="mt-1 text-sm text-muted">
                Once your account is deleted, all of its resources and data will be permanently deleted. Before
                deleting your account, please download any data or information that you wish to retain.
            </p>
        </header>

        <Button variant="danger" @click="confirmingUserDeletion = true">Delete Account</Button>

        <Modal :show="confirmingUserDeletion" @close="closeModal" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-body">Are you sure you want to delete your account?</h2>
                <p class="mt-1 text-sm text-muted">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Please
                    enter your password to confirm you would like to permanently delete your account.
                </p>

                <div class="mt-6">
                    <TextField
                        type="password"
                        v-model="form.password"
                        :error="form.errors.password"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="outline" @click="closeModal">Cancel</Button>
                    <Button variant="danger" :loading="form.processing" @click="deleteUser">Delete Account</Button>
                </div>
            </div>
        </Modal>
    </section>
</template>
