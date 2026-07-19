<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/PropOff/Base/Button.vue';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import { ref } from 'vue';

const joinCode = ref('');

const joinGroup = () => {
    if (joinCode.value) {
        router.visit(route('propoff.play.hub', { code: joinCode.value.toUpperCase() }));
    }
};
</script>

<template>
    <Head title="Join a Group" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Join a Group"
                subtitle="Enter your group code to get started"
                :crumbs="[
                    { label: 'Home', href: route('propoff.home') },
                    { label: 'Join' }
                ]"
            />
        </template>

        <div class="py-12">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-surface-elevated border border-border rounded-xl p-8 text-center">
                    <h2 class="text-2xl font-bold text-body mb-2">Have a Group Code?</h2>
                    <p class="text-muted mb-6">Enter your code below to join</p>

                    <form @submit.prevent="joinGroup" class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1">
                            <input
                                v-model="joinCode"
                                type="text"
                                placeholder="ABCD1234"
                                maxlength="8"
                                class="w-full px-4 py-3 bg-surface-inset border border-border rounded-lg text-body placeholder-muted text-center text-lg font-mono tracking-widest uppercase focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="primary"
                            size="lg"
                            :disabled="!joinCode"
                        >
                            Join
                        </Button>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
