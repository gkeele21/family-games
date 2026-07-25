<script setup lang="ts">
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    householdId: number;
    playerId: number;
}>();

const open = ref(false);
// Inline confirmation: the page-top flash can be scrolled out of view, so a
// successful send is confirmed right here next to the control.
const sentTo = ref<string | null>(null);
let sentTimer: ReturnType<typeof setTimeout> | null = null;
const form = useForm({
    email: '',
    player_id: props.playerId,
});

const send = () => {
    form.post(
        route('scorekeeper.households.invites.store', props.householdId),
        {
            preserveScroll: true,
            onSuccess: () => {
                sentTo.value = form.email;
                if (sentTimer) clearTimeout(sentTimer);
                sentTimer = setTimeout(() => (sentTo.value = null), 5000);
                open.value = false;
                form.reset('email');
            },
        },
    );
};
</script>

<template>
    <span v-if="!open" class="flex items-center gap-2">
        <button
            type="button"
            class="text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
            @click="open = true"
        >
            Invite
        </button>
        <span
            v-if="sentTo"
            class="text-xs font-medium text-green-700"
            >✓ Invite sent to {{ sentTo }}</span
        >
    </span>
    <form v-else class="flex items-center gap-2" @submit.prevent="send">
        <TextInput
            v-model="form.email"
            type="email"
            class="w-52 text-sm"
            placeholder="their@email.com"
            required
        />
        <SecondaryButton type="submit" :disabled="form.processing"
            >Send</SecondaryButton
        >
        <button
            type="button"
            class="text-sm text-gray-400 hover:text-gray-600"
            @click="open = false"
        >
            Cancel
        </button>
        <span v-if="form.errors.email" class="text-xs text-red-600">{{
            form.errors.email
        }}</span>
    </form>
</template>
