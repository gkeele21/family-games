<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import KeelerLogo from '@/Components/KeelerLogo.vue';
import GameWordmark from '@/Components/GameWordmark.vue';
import TextField from '@/Components/Form/TextField.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import Button from '@/Components/Base/Button.vue';
import { useTheme } from '@/composables/useTheme';

const { theme } = useTheme();

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    openAuth?: 'login' | 'register' | null;
}>();

// Logged-in visitors see "Go to Dashboard" instead of the sign-in CTAs.
const user = computed(() => (usePage().props.auth as { user?: unknown } | undefined)?.user);

// ---- auth slider ----
const drawerOpen = ref(false);
const mode = ref<'login' | 'register'>('login');

const loginForm = useForm({ email: '', password: '', remember: false });
const registerForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const openAuth = (m: 'login' | 'register') => {
    mode.value = m;
    drawerOpen.value = true;
};
const closeAuth = () => (drawerOpen.value = false);

const submitLogin = () =>
    loginForm.post(route('login'), { onFinish: () => loginForm.reset('password') });
const submitRegister = () =>
    registerForm.post(route('register'), {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    });

// ---- join by code ----
const joinCode = ref('');
const join = () => {
    const code = joinCode.value.trim().toUpperCase();
    router.get(route('player.join'), code ? { code } : {});
};

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') closeAuth();
};
onMounted(() => {
    document.addEventListener('keydown', onKey);
    // Opened here when a protected route bounced a guest to the landing page.
    if (!user.value && props.openAuth) openAuth(props.openAuth);
});
onUnmounted(() => document.removeEventListener('keydown', onKey));

const games = [
    {
        first: 'SCORE',
        second: 'KEEPER',
        ac: 'rgb(var(--color-info))',
        acglow: 'rgb(var(--color-info) / 0.30)',
        desc: 'Build your own score pad for any card or board game — live standings players update from their phones.',
    },
    {
        first: 'PARTY',
        second: 'GAMES',
        ac: 'rgb(var(--color-warning))',
        acglow: 'rgb(var(--color-warning) / 0.30)',
        desc: 'Live game-show battles — Family Feud, America Says, Oodles and more. Host on the big screen.',
    },
    {
        first: 'PROP',
        second: 'OFF',
        ac: 'rgb(var(--color-success))',
        acglow: 'rgb(var(--color-success) / 0.30)',
        desc: 'Test your prediction skills in the ultimate prop betting challenge.',
    },
];
</script>

<template>
    <Head title="Keeler Games — Family Game Night" />

    <div class="keeler-app" :class="`theme-${theme}`">
        <div
            class="min-h-screen text-body"
            style="
                background:
                    radial-gradient(60vw 40vw at 15% 8%, rgb(var(--color-success) / 0.1), transparent 60%),
                    radial-gradient(50vw 40vw at 85% 20%, rgb(var(--color-info) / 0.1), transparent 60%),
                    radial-gradient(60vw 50vw at 50% 108%, rgb(var(--color-warning) / 0.1), transparent 60%),
                    rgb(var(--color-surface-inset));
            "
        >
            <div class="mx-auto max-w-[1600px] px-6 sm:px-10">
                <!-- Row 1: auth actions, top-right -->
                <div class="flex justify-end pt-6">
                    <div class="flex gap-3">
                        <template v-if="user">
                            <Button variant="primary" size="md" @click="router.visit(route('dashboard'))">
                                Go to Dashboard
                            </Button>
                        </template>
                        <template v-else>
                            <Button v-if="canRegister" variant="accent" size="md" @click="openAuth('register')">
                                Get Started Free
                            </Button>
                            <Button v-if="canLogin" variant="success" size="md" @click="openAuth('login')">
                                Sign In
                            </Button>
                        </template>
                    </div>
                </div>

                <!-- Row 2: logo + tagline, vertically centered, spaced across -->
                <header class="flex flex-col items-center gap-8 py-4 lg:flex-row lg:justify-center lg:gap-16">
                    <KeelerLogo
                        variant="full"
                        class="h-[200px] w-auto shrink-0 drop-shadow-[0_10px_30px_rgba(0,0,0,0.5)] lg:h-[300px]"
                    />
                    <div class="text-center">
                        <h1 class="text-[clamp(34px,4.6vw,58px)] font-extrabold leading-[1.03] tracking-tight lg:whitespace-nowrap">
                            Play <span class="text-subtle">&bull;</span> Score <span class="text-subtle">&bull;</span> <span class="text-primary">Win</span>
                        </h1>
                        <p class="mx-auto mt-4 max-w-2xl text-[clamp(15px,1.8vw,18px)] text-muted">
                            Host live party games, keep a running score for any card or board game, and go
                            head-to-head on prop-bet predictions — one home for family game night, on every
                            screen in the room.
                        </p>
                    </div>
                </header>

                <!-- Game cards -->
                <section class="grid grid-cols-1 gap-6 pb-2 pt-12 md:grid-cols-3">
                    <div
                        v-for="g in games"
                        :key="g.first"
                        class="game-card rounded-2xl border border-border bg-surface/70 p-7"
                        :style="{ '--ac': g.ac, '--acglow': g.acglow }"
                    >
                        <div class="mb-4">
                            <GameWordmark :first="g.first" :second="g.second" :color="g.ac" />
                        </div>
                        <p class="text-[15px] text-muted">{{ g.desc }}</p>
                    </div>
                </section>

                <!-- Game code -->
                <section class="flex justify-center py-12">
                    <div class="w-full max-w-2xl rounded-[18px] border border-border bg-surface/70 p-8 text-center">
                        <h2 class="text-[26px] font-extrabold">Have a game code?</h2>
                        <p class="mt-1.5 text-muted">Enter your code below to join</p>
                        <form class="mt-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="join">
                            <input
                                v-model="joinCode"
                                maxlength="6"
                                placeholder="GAME01"
                                aria-label="Game code"
                                class="focus-glow flex-1 rounded-xl border border-border bg-surface-inset px-5 py-4 text-lg font-bold uppercase tracking-[0.35em] text-body placeholder:tracking-[0.35em] placeholder:text-subtle focus:outline-none"
                            />
                            <Button type="submit" variant="secondary" size="lg">Go</Button>
                        </form>
                    </div>
                </section>

                <div class="pb-14 text-center text-sm text-subtle">
                    © 2026 Keeler Games · Family Games. Epic Nights.
                </div>
            </div>

            <!-- ===== Auth slider ===== -->
            <transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-300"
                leave-to-class="opacity-0"
            >
                <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm" @click="closeAuth"></div>
            </transition>

            <transition
                enter-active-class="transition-transform duration-300"
                enter-from-class="translate-x-full"
                leave-active-class="transition-transform duration-300"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="drawerOpen"
                    class="fixed right-0 top-0 z-50 flex h-screen w-full max-w-md flex-col overflow-y-auto border-l border-border bg-surface shadow-2xl"
                >
                    <div class="sticky top-0 flex items-center justify-between border-b border-border bg-surface/90 px-6 py-4 backdrop-blur">
                        <KeelerLogo variant="wordmark" class="h-9 w-auto" />
                        <button
                            class="grid h-9 w-9 place-items-center rounded-lg border border-border text-muted transition hover:border-border-strong hover:text-body"
                            aria-label="Close"
                            @click="closeAuth"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="px-6 py-8">
                        <div class="mb-6 flex gap-1.5 rounded-xl border border-border bg-surface-inset p-1.5">
                            <button
                                class="flex-1 rounded-lg py-2 text-sm font-bold transition"
                                :class="mode === 'login'
                                    ? 'bg-surface-elevated text-body ring-1 ring-primary shadow-[0_0_16px_rgb(var(--color-primary)/0.35)]'
                                    : 'text-muted hover:text-body'"
                                @click="mode = 'login'"
                            >
                                Sign in
                            </button>
                            <button
                                class="flex-1 rounded-lg py-2 text-sm font-bold transition"
                                :class="mode === 'register'
                                    ? 'bg-surface-elevated text-body ring-1 ring-primary shadow-[0_0_16px_rgb(var(--color-primary)/0.35)]'
                                    : 'text-muted hover:text-body'"
                                @click="mode = 'register'"
                            >
                                Register
                            </button>
                        </div>

                        <template v-if="mode === 'login'">
                            <h2 class="text-center text-xl font-bold text-body">Welcome back</h2>
                            <p class="mb-6 mt-1 text-center text-sm text-muted">
                                Sign in to host and manage your game nights.
                            </p>
                            <form class="space-y-4" @submit.prevent="submitLogin">
                                <TextField label="Email" type="email" v-model="loginForm.email" :error="loginForm.errors.email" required placeholder="you@example.com" />
                                <TextField label="Password" type="password" v-model="loginForm.password" :error="loginForm.errors.password" required placeholder="••••••••" />
                                <div class="flex items-center justify-between">
                                    <Checkbox v-model="loginForm.remember" label="Remember me" label-variant="muted" />
                                    <Link :href="route('password.request')" class="text-sm font-semibold text-primary hover:underline">
                                        Forgot password?
                                    </Link>
                                </div>
                                <Button type="submit" variant="primary" size="lg" class="w-full" :loading="loginForm.processing">
                                    Sign in
                                </Button>
                            </form>
                            <p class="mt-6 border-t border-border pt-4 text-center text-sm text-muted">
                                New here?
                                <button class="font-semibold text-primary hover:underline" @click="mode = 'register'">
                                    Create an account
                                </button>
                            </p>
                        </template>

                        <template v-else>
                            <h2 class="text-center text-xl font-bold text-body">Create your account</h2>
                            <p class="mb-6 mt-1 text-center text-sm text-muted">
                                Set up once — then host any game in the collection.
                            </p>
                            <form class="space-y-4" @submit.prevent="submitRegister">
                                <div class="grid grid-cols-2 gap-4">
                                    <TextField label="First name" v-model="registerForm.first_name" :error="registerForm.errors.first_name" required placeholder="First" />
                                    <TextField label="Last name" v-model="registerForm.last_name" :error="registerForm.errors.last_name" required placeholder="Last" />
                                </div>
                                <TextField label="Email" type="email" v-model="registerForm.email" :error="registerForm.errors.email" required placeholder="you@example.com" />
                                <TextField label="Password" type="password" v-model="registerForm.password" :error="registerForm.errors.password" required placeholder="Create a password" />
                                <TextField label="Confirm password" type="password" v-model="registerForm.password_confirmation" :error="registerForm.errors.password_confirmation" required placeholder="Re-enter password" />
                                <Button type="submit" variant="success" size="lg" class="w-full" :loading="registerForm.processing">
                                    Create account
                                </Button>
                            </form>
                            <p class="mt-6 border-t border-border pt-4 text-center text-sm text-muted">
                                Already have an account?
                                <button class="font-semibold text-primary hover:underline" @click="mode = 'login'">
                                    Sign in
                                </button>
                            </p>
                        </template>
                    </div>
                </aside>
            </transition>
        </div>
    </div>
</template>

<style scoped>
.game-card {
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}
.game-card:hover {
    transform: translateY(-3px);
    border-color: var(--ac);
    box-shadow:
        0 0 0 1px var(--ac),
        0 18px 44px rgba(0, 0, 0, 0.5),
        0 0 34px var(--acglow);
}
</style>
