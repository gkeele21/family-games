<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps<{
    gameSession: {
        id: number;
        name: string;
        invite_code: string;
    };
    auth: {
        user: {
            id: number;
            name: string;
        } | null;
    };
}>();

const mode = ref<'choose' | 'login' | 'guest'>(props.auth.user ? 'choose' : 'choose');

const guestForm = useForm({
    guest_name: '',
});

const loginForm = useForm({
    email: '',
    password: '',
});

const submitGuest = () => {
    guestForm.post(route('player.join.session', props.gameSession.id));
};

const submitLogin = () => {
    loginForm.post(route('player.join.login', props.gameSession.id), {
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};

const continueAsUser = () => {
    guestForm.post(route('player.join.session', props.gameSession.id));
};

const isAuthenticated = computed(() => !!props.auth.user);
</script>

<template>
    <Head title="Join Game" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-purple-900">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
            <!-- Game Info Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ gameSession.name }}</h1>
                <p class="text-gray-500 mt-1">Game Code: <span class="font-mono font-bold">{{ gameSession.invite_code }}</span></p>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <!-- Already Logged In -->
                <template v-if="isAuthenticated">
                    <div class="text-center">
                        <p class="text-gray-700 mb-4">
                            Joining as <span class="font-semibold">{{ auth.user?.name }}</span>
                        </p>
                        <button
                            @click="continueAsUser"
                            :disabled="guestForm.processing"
                            class="w-full py-4 bg-blue-600 text-white text-xl font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
                        >
                            Join Game
                        </button>
                        <button
                            @click="mode = 'guest'"
                            class="mt-3 text-gray-500 hover:text-gray-700 text-sm"
                        >
                            Join as someone else instead
                        </button>
                    </div>

                    <!-- Guest name form for authenticated user wanting different name -->
                    <div v-if="mode === 'guest'" class="mt-6 pt-6 border-t border-gray-200">
                        <form @submit.prevent="submitGuest">
                            <div class="mb-4">
                                <InputLabel for="guest_name" value="Your Name" />
                                <TextInput
                                    id="guest_name"
                                    v-model="guestForm.guest_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Enter your name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="guestForm.errors.guest_name" />
                            </div>
                            <button
                                type="submit"
                                :disabled="guestForm.processing || !guestForm.guest_name.trim()"
                                class="w-full py-3 bg-gray-600 text-white font-bold rounded-lg hover:bg-gray-700 disabled:opacity-50 transition"
                            >
                                Join as Guest
                            </button>
                        </form>
                    </div>
                </template>

                <!-- Not Logged In - Show Options -->
                <template v-else>
                    <!-- Choice Screen -->
                    <div v-if="mode === 'choose'" class="space-y-4">
                        <h2 class="text-lg font-semibold text-center text-gray-700 mb-4">How would you like to join?</h2>

                        <button
                            @click="mode = 'login'"
                            class="w-full py-4 bg-blue-600 text-white text-lg font-bold rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                            Log In
                        </button>

                        <button
                            @click="mode = 'guest'"
                            class="w-full py-4 bg-gray-100 text-gray-700 text-lg font-bold rounded-lg hover:bg-gray-200 transition border border-gray-300"
                        >
                            Continue as Guest
                        </button>
                    </div>

                    <!-- Login Form -->
                    <div v-else-if="mode === 'login'">
                        <button
                            @click="mode = 'choose'"
                            class="mb-4 text-gray-500 hover:text-gray-700 text-sm flex items-center gap-1"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                            Back
                        </button>

                        <form @submit.prevent="submitLogin">
                            <div class="mb-4">
                                <InputLabel for="email" value="Email" />
                                <TextInput
                                    id="email"
                                    v-model="loginForm.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    required
                                    autofocus
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="loginForm.errors.email" />
                            </div>

                            <div class="mb-4">
                                <InputLabel for="password" value="Password" />
                                <TextInput
                                    id="password"
                                    v-model="loginForm.password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    required
                                    autocomplete="current-password"
                                />
                                <InputError class="mt-2" :message="loginForm.errors.password" />
                            </div>

                            <button
                                type="submit"
                                :disabled="loginForm.processing"
                                class="w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
                            >
                                Log In & Join
                            </button>

                            <div class="mt-4 text-center">
                                <Link
                                    :href="route('register')"
                                    class="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Don't have an account? Register
                                </Link>
                            </div>
                        </form>
                    </div>

                    <!-- Guest Form -->
                    <div v-else-if="mode === 'guest'">
                        <button
                            @click="mode = 'choose'"
                            class="mb-4 text-gray-500 hover:text-gray-700 text-sm flex items-center gap-1"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                            Back
                        </button>

                        <form @submit.prevent="submitGuest">
                            <div class="mb-4">
                                <InputLabel for="guest_name" value="Your Name" />
                                <TextInput
                                    id="guest_name"
                                    v-model="guestForm.guest_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Enter your name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="guestForm.errors.guest_name" />
                            </div>

                            <button
                                type="submit"
                                :disabled="guestForm.processing || !guestForm.guest_name.trim()"
                                class="w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
                            >
                                Join Game
                            </button>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
