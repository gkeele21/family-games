<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InvitePlayerControl from '@/Components/Scorekeeper/InvitePlayerControl.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    household: { id: number; name: string };
    players: Array<{ id: number; name: string; has_account: boolean }>;
}>();

const page = usePage();

const playerForm = useForm({ name: '' });

const addPlayer = () => {
    playerForm.post(
        route('scorekeeper.households.players.store', props.household.id),
        {
            preserveScroll: true,
            onSuccess: () => playerForm.reset('name'),
        },
    );
};

const removePlayer = (player: { id: number; name: string }) => {
    if (!confirm(`Remove player "${player.name}"?`)) return;
    router.delete(route('scorekeeper.players.destroy', player.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Players" />

    <ScorekeeperLayout :household="household" tab="players">
        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <h3
                        class="border-b px-6 py-4 text-lg font-medium text-[#0b5d3b]"
                    >
                        Player roster
                    </h3>
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="player in players"
                            :key="player.id"
                            class="flex items-center justify-between gap-3 px-6 py-3"
                        >
                            <span class="flex items-center gap-2 text-gray-900">
                                {{ player.name }}
                                <span
                                    v-if="player.has_account"
                                    class="rounded-full bg-[#d9f3e5] px-2 py-0.5 text-xs font-medium text-[#0b7a48]"
                                    title="This player has an account"
                                    >linked</span
                                >
                            </span>
                            <span class="flex items-center gap-3">
                                <InvitePlayerControl
                                    v-if="!player.has_account"
                                    :household-id="household.id"
                                    :player-id="player.id"
                                />
                                <DangerButton @click="removePlayer(player)"
                                    >Remove</DangerButton
                                >
                            </span>
                        </li>
                        <li
                            v-if="players.length === 0"
                            class="px-6 py-3 text-sm text-gray-500"
                        >
                            No players yet — add the people you play with (they
                            don't need accounts).
                        </li>
                    </ul>
                    <form
                        class="flex items-end gap-3 border-t px-6 py-4"
                        @submit.prevent="addPlayer"
                    >
                        <div class="flex-1">
                            <InputLabel for="player-name" value="Add player" />
                            <TextInput
                                id="player-name"
                                v-model="playerForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Grandma"
                            />
                            <InputError
                                class="mt-1"
                                :message="playerForm.errors.name"
                            />
                        </div>
                        <PrimaryButton :disabled="playerForm.processing"
                            >Add</PrimaryButton
                        >
                    </form>
                </div>
            </div>
        </div>
    </ScorekeeperLayout>
</template>
