<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps<{
    initialCode?: string;
}>();

const form = useForm({
    invite_code: props.initialCode?.toUpperCase() || '',
});

const submit = () => {
    form.post(route('player.join.code'));
};

// Auto-submit if a valid code was provided via URL
onMounted(() => {
    if (form.invite_code.length === 6) {
        submit();
    }
});
</script>

<template>
    <Head title="Join Game" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-purple-900">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
            <h1 class="text-3xl font-bold text-center mb-8">Join Game</h1>

            <form @submit.prevent="submit">
                <div class="mb-6">
                    <label for="invite_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter Game Code
                    </label>
                    <input
                        id="invite_code"
                        v-model="form.invite_code"
                        type="text"
                        class="w-full text-center text-3xl font-mono tracking-widest uppercase rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        placeholder="ABC123"
                        maxlength="6"
                        required
                        @input="form.invite_code = form.invite_code.toUpperCase()"
                    />
                    <p v-if="form.errors.invite_code" class="mt-2 text-sm text-red-600">
                        {{ form.errors.invite_code }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || form.invite_code.length !== 6"
                    class="w-full py-4 bg-blue-600 text-white text-xl font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    Join Game
                </button>
            </form>

            <div class="mt-8 text-center text-gray-500">
                <p>Ask your host for the game code</p>
            </div>
        </div>
    </div>
</template>
