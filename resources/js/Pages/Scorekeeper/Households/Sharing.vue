<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    household: {
        id: number;
        name: string;
        owner_user_id: number;
        members: Array<{
            id: number;
            name: string;
            email: string;
            pivot: { role: string };
        }>;
    };
    isOwner: boolean;
}>();

const page = usePage();

const editing = ref(false);
const nameForm = useForm({ name: props.household.name });
const inviteForm = useForm({ email: '', role: 'member' });

const rename = () => {
    nameForm.patch(route('scorekeeper.households.update', props.household.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
};

const invite = () => {
    inviteForm.post(
        route('scorekeeper.households.invites.store', props.household.id),
        {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset('email'),
        },
    );
};
</script>

<template>
    <Head title="Sharing" />

    <ScorekeeperLayout :household="household" tab="sharing">
        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Members + invite -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-medium text-[#0b5d3b]">
                            Sharing
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Invite family so everyone sees the same roster,
                            templates, and game history on their own device.
                        </p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="member in household.members"
                            :key="member.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ member.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ member.email }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                >{{ member.pivot.role }}</span
                            >
                        </li>
                    </ul>
                    <form
                        class="flex items-end gap-3 border-t px-6 py-4"
                        @submit.prevent="invite"
                    >
                        <div class="flex-1">
                            <InputLabel for="email" value="Invite by email" />
                            <TextInput
                                id="email"
                                v-model="inviteForm.email"
                                type="email"
                                class="mt-1 block w-full"
                                placeholder="family@example.com"
                            />
                            <InputError
                                class="mt-1"
                                :message="inviteForm.errors.email"
                            />
                        </div>
                        <div>
                            <InputLabel for="role" value="Role" />
                            <select
                                id="role"
                                v-model="inviteForm.role"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="member">Member</option>
                                <option value="guest">Guest</option>
                            </select>
                        </div>
                        <PrimaryButton :disabled="inviteForm.processing"
                            >Send invite</PrimaryButton
                        >
                    </form>
                </div>

                <!-- Household settings (owner only) -->
                <div
                    v-if="isOwner"
                    class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <div v-if="!editing" class="flex items-center justify-between">
                        <span class="text-sm text-gray-500"
                            >Household name: {{ household.name }}</span
                        >
                        <SecondaryButton @click="editing = true"
                            >Rename</SecondaryButton
                        >
                    </div>
                    <form
                        v-else
                        class="flex items-end gap-3"
                        @submit.prevent="rename"
                    >
                        <div class="flex-1">
                            <InputLabel for="hh-name" value="Name" />
                            <TextInput
                                id="hh-name"
                                v-model="nameForm.name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                class="mt-1"
                                :message="nameForm.errors.name"
                            />
                        </div>
                        <PrimaryButton :disabled="nameForm.processing"
                            >Save</PrimaryButton
                        >
                        <SecondaryButton type="button" @click="editing = false"
                            >Cancel</SecondaryButton
                        >
                    </form>
                </div>
            </div>
        </div>
    </ScorekeeperLayout>
</template>
