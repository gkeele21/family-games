<script setup lang="ts">
import DangerButton from '@/Components/Scorekeeper/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/Scorekeeper/InputLabel.vue';
import InvitePlayerControl from '@/Components/Scorekeeper/InvitePlayerControl.vue';
import PrimaryButton from '@/Components/Scorekeeper/PrimaryButton.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import SecondaryButton from '@/Components/Scorekeeper/SecondaryButton.vue';
import TextInput from '@/Components/Scorekeeper/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    household: { id: number; name: string };
    people: Array<{
        player_id: number | null;
        name: string;
        has_account: boolean;
        email: string | null;
        role: string | null;
    }>;
    isOwner: boolean;
    suggestions: Array<{
        name: string;
        user_id: number | null;
        source: string;
    }>;
}>();

const page = usePage();

const playerForm = useForm({ name: '', user_id: null as number | null });

const addPlayer = () => {
    if (!playerForm.name.trim()) return;
    playerForm.post(
        route('scorekeeper.households.players.store', props.household.id),
        {
            preserveScroll: true,
            onSuccess: () => playerForm.reset('name', 'user_id'),
        },
    );
};

// Suggestions dropdown: people from your other households and friends
// (server-provided; typing filters them). Picking one adds them right away,
// carrying their account link when they have one.
const suggestionsOpen = ref(false);
const matchingSuggestions = computed(() => {
    const q = playerForm.name.trim().toLowerCase();
    return props.suggestions
        .filter((s) => !q || s.name.toLowerCase().includes(q))
        .slice(0, 6);
});
// Delay so a click on a suggestion lands before the list closes.
const closeSuggestionsSoon = () => {
    window.setTimeout(() => (suggestionsOpen.value = false), 150);
};
const pickSuggestion = (s: { name: string; user_id: number | null }) => {
    playerForm.name = s.name;
    playerForm.user_id = s.user_id;
    suggestionsOpen.value = false;
    addPlayer();
};
// A hand-typed name is never account-linked.
const onNameInput = () => {
    playerForm.user_id = null;
    suggestionsOpen.value = true;
};

const removePlayer = (person: { player_id: number | null; name: string }) => {
    if (person.player_id === null) return;
    if (!confirm(`Remove "${person.name}" from the roster?`)) return;
    router.delete(route('scorekeeper.players.destroy', person.player_id), {
        preserveScroll: true,
    });
};

const roleChipClass = (role: string) =>
    role === 'owner'
        ? 'bg-warning/15 text-warning'
        : role === 'guest'
          ? 'bg-surface-overlay text-muted'
          : 'bg-primary/15 text-primary';

// Invite someone who isn't on the roster (full-access invite).
const inviteForm = useForm({ email: '', role: 'member' });

const invite = () => {
    inviteForm.post(
        route('scorekeeper.households.invites.store', props.household.id),
        {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset('email'),
        },
    );
};

// Household rename (owner only).
const editing = ref(false);
const nameForm = useForm({ name: props.household.name });

const rename = () => {
    nameForm.patch(route('scorekeeper.households.update', props.household.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
};
</script>

<template>
    <Head title="People" />

    <ScorekeeperLayout :household="household" tab="people">
        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-primary/30 bg-primary/10 p-4 text-sm text-primary"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Which household this is + household-level actions -->
                <div class="overflow-hidden rounded-lg border border-border bg-surface">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
                    >
                        <div v-if="!editing">
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-subtle"
                            >
                                Household
                            </p>
                            <h3 class="text-lg font-medium text-body">
                                {{ household.name }}
                            </h3>
                        </div>
                        <form
                            v-else
                            class="flex flex-1 items-end gap-3"
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
                            <SecondaryButton
                                type="button"
                                @click="editing = false"
                                >Cancel</SecondaryButton
                            >
                        </form>
                        <span
                            v-if="!editing"
                            class="flex items-center gap-4"
                        >
                            <SecondaryButton
                                v-if="isOwner"
                                @click="editing = true"
                                >Rename</SecondaryButton
                            >
                            <Link
                                :href="route('scorekeeper.households.index')"
                                class="text-sm font-medium text-primary hover:text-primary-hover"
                                >Switch or manage households &rarr;</Link
                            >
                        </span>
                    </div>
                </div>

                <!-- Everyone: roster players + account members -->
                <div class="overflow-hidden rounded-lg border border-border bg-surface">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-medium text-body">
                            People
                        </h3>
                        <p class="mt-1 text-sm text-muted">
                            Everyone in this household — players you keep score
                            for (no account needed) and family with their own
                            login. Invite a player and they'll see their games
                            and scores on their own device.
                        </p>
                    </div>
                    <ul class="divide-y divide-border">
                        <li
                            v-for="person in people"
                            :key="`${person.player_id ?? 'u'}-${person.name}-${person.email}`"
                            class="flex items-center justify-between gap-3 px-6 py-3"
                        >
                            <div>
                                <span
                                    class="flex items-center gap-2 text-body"
                                >
                                    {{ person.name }}
                                    <span
                                        v-if="person.role"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="roleChipClass(person.role)"
                                        >{{ person.role }}</span
                                    >
                                    <span
                                        v-else-if="person.has_account"
                                        class="rounded-full bg-info/15 px-2 py-0.5 text-xs font-medium text-info"
                                        title="This player has an account"
                                        >linked</span
                                    >
                                    <span
                                        v-else
                                        class="rounded-full bg-surface-overlay px-2 py-0.5 text-xs font-medium text-subtle"
                                        >no account</span
                                    >
                                </span>
                                <p
                                    v-if="person.email"
                                    class="text-sm text-muted"
                                >
                                    {{ person.email }}
                                </p>
                            </div>
                            <span class="flex items-center gap-3">
                                <InvitePlayerControl
                                    v-if="
                                        !person.has_account &&
                                        person.player_id !== null
                                    "
                                    :household-id="household.id"
                                    :player-id="person.player_id"
                                />
                                <DangerButton
                                    v-if="person.player_id !== null"
                                    @click="removePlayer(person)"
                                    >Remove</DangerButton
                                >
                            </span>
                        </li>
                        <li
                            v-if="people.length === 0"
                            class="px-6 py-3 text-sm text-muted"
                        >
                            No people yet — add the people you play with (they
                            don't need accounts).
                        </li>
                    </ul>
                    <form
                        class="flex items-end gap-3 border-t px-6 py-4"
                        @submit.prevent="addPlayer"
                    >
                        <div class="relative flex-1">
                            <InputLabel for="player-name" value="Add player" />
                            <TextInput
                                id="player-name"
                                v-model="playerForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Grandma"
                                autocomplete="off"
                                @input="onNameInput"
                                @focus="suggestionsOpen = true"
                                @blur="closeSuggestionsSoon"
                            />
                            <ul
                                v-if="
                                    suggestionsOpen &&
                                    matchingSuggestions.length
                                "
                                class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-border bg-surface-elevated shadow-lg"
                            >
                                <li
                                    v-for="s in matchingSuggestions"
                                    :key="`${s.user_id ?? 'n'}-${s.name}-${s.source}`"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-inset"
                                        @click="pickSuggestion(s)"
                                    >
                                        <span class="text-body">{{
                                            s.name
                                        }}</span>
                                        <span class="text-xs text-subtle">{{
                                            s.source
                                        }}</span>
                                    </button>
                                </li>
                            </ul>
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

                <!-- Full-access invite for someone not on the roster -->
                <div class="overflow-hidden rounded-lg border border-border bg-surface">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-medium text-body">
                            Invite someone who doesn't play
                        </h3>
                        <p class="mt-1 text-sm text-muted">
                            Gives full access to this household — roster,
                            templates, and game history — without adding them
                            to the roster. To invite a player, use the Invite
                            button on their row above.
                        </p>
                    </div>
                    <form
                        class="flex items-end gap-3 px-6 py-4"
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
                                class="mt-1 rounded-md border-border-strong bg-surface-inset text-body shadow-sm focus:border-primary focus:ring-primary"
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

            </div>
        </div>
    </ScorekeeperLayout>
</template>
