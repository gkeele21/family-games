<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface HouseholdRow {
    id: number;
    name: string;
    role: string;
    is_owner: boolean;
    members_count: number;
    players_count: number;
    scored_games_count: number;
}

defineProps<{
    households: HouseholdRow[];
}>();

const page = usePage();

// Create
const createForm = useForm({ name: '' });
const create = () => {
    createForm.post(route('scorekeeper.households.store'), {
        onSuccess: () => createForm.reset('name'),
    });
};

// Inline rename (one at a time)
const renamingId = ref<number | null>(null);
const renameForm = useForm({ name: '' });
const startRename = (h: HouseholdRow) => {
    renamingId.value = h.id;
    renameForm.name = h.name;
    renameForm.clearErrors();
};
const rename = () => {
    if (renamingId.value === null) return;
    renameForm.patch(
        route('scorekeeper.households.update', renamingId.value),
        {
            preserveScroll: true,
            onSuccess: () => (renamingId.value = null),
        },
    );
};

const destroy = (h: HouseholdRow) => {
    if (
        !confirm(
            `Delete "${h.name}"? Its players, templates, and all ${h.scored_games_count} game(s) will be permanently deleted.`,
        )
    )
        return;
    router.delete(route('scorekeeper.households.destroy', h.id), {
        preserveScroll: true,
    });
};

const leave = (h: HouseholdRow) => {
    if (!confirm(`Leave "${h.name}"? You'll lose access to its games.`)) return;
    router.delete(route('scorekeeper.households.leave', h.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Manage households" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-[#0b5d3b]">
                Manage households
            </h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Household cards -->
                <div
                    v-for="h in households"
                    :key="h.id"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="flex flex-wrap items-center gap-3 px-6 py-4">
                        <template v-if="renamingId !== h.id">
                            <span class="text-lg font-semibold text-gray-900">{{
                                h.name
                            }}</span>
                            <span
                                class="rounded-full bg-[#f7ecca] px-2 py-0.5 text-xs font-medium text-[#6b5407]"
                                >{{ h.is_owner ? 'owner' : h.role }}</span
                            >
                            <span class="text-sm text-gray-500">
                                {{ h.members_count }} member(s) ·
                                {{ h.players_count }} player(s) ·
                                {{ h.scored_games_count }} game(s)
                            </span>
                            <span class="ml-auto flex items-center gap-2">
                                <SecondaryButton
                                    v-if="h.is_owner"
                                    @click="startRename(h)"
                                    >Rename</SecondaryButton
                                >
                                <DangerButton
                                    v-if="h.is_owner"
                                    @click="destroy(h)"
                                    >Delete</DangerButton
                                >
                                <DangerButton v-else @click="leave(h)"
                                    >Leave</DangerButton
                                >
                            </span>
                        </template>
                        <form
                            v-else
                            class="flex w-full items-end gap-3"
                            @submit.prevent="rename"
                        >
                            <div class="flex-1">
                                <InputLabel
                                    :for="`rename-${h.id}`"
                                    value="Name"
                                />
                                <TextInput
                                    :id="`rename-${h.id}`"
                                    v-model="renameForm.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="renameForm.errors.name"
                                />
                            </div>
                            <PrimaryButton :disabled="renameForm.processing"
                                >Save</PrimaryButton
                            >
                            <SecondaryButton
                                type="button"
                                @click="renamingId = null"
                                >Cancel</SecondaryButton
                            >
                        </form>
                    </div>
                    <div
                        class="flex flex-wrap gap-4 border-t bg-gray-50/60 px-6 py-3 text-sm font-medium"
                    >
                        <Link
                            :href="
                                route('scorekeeper.households.games.index', h.id)
                            "
                            class="text-[#0b5d3b] hover:text-[#084a2f]"
                            >Games</Link
                        >
                        <Link
                            :href="
                                route(
                                    'scorekeeper.households.templates.index',
                                    h.id,
                                )
                            "
                            class="text-[#0b5d3b] hover:text-[#084a2f]"
                            >Game templates</Link
                        >
                        <Link
                            :href="route('scorekeeper.households.people', h.id)"
                            class="text-[#0b5d3b] hover:text-[#084a2f]"
                            >People</Link
                        >
                    </div>
                </div>

                <!-- Create -->
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-[#0b5d3b]">
                        New household
                    </h3>
                    <form class="flex items-end gap-3" @submit.prevent="create">
                        <div class="flex-1">
                            <InputLabel for="name" value="Name" />
                            <TextInput
                                id="name"
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Friday crew"
                            />
                            <InputError
                                class="mt-1"
                                :message="createForm.errors.name"
                            />
                        </div>
                        <PrimaryButton :disabled="createForm.processing">
                            Create
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
