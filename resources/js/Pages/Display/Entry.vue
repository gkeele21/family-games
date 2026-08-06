<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import KeelerLogo from '@/Components/KeelerLogo.vue';

/**
 * Stable code-entry landing for the TV display. Add THIS page to an iPhone home
 * screen once; the standalone meta below makes it launch chromeless. Each game
 * night you tap the icon, type the current code, and it routes to the board — so
 * the icon never carries a stale game code.
 */

const RECENTS_KEY = 'keeler-display-recent';
const RECENTS_MAX = 5;

const code = ref('');
const recents = ref<string[]>([]);

// Codes are uppercase alphanumeric; keep the field clean as they type.
const onInput = (e: Event) => {
    const raw = (e.target as HTMLInputElement).value;
    code.value = raw.toUpperCase().replace(/[^A-Z0-9]/g, '');
};

const canOpen = computed(() => code.value.trim().length > 0);

const loadRecents = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(RECENTS_KEY) ?? '[]');
        if (Array.isArray(stored)) recents.value = stored.filter((c) => typeof c === 'string');
    } catch {
        recents.value = [];
    }
};

const remember = (value: string) => {
    try {
        const next = [value, ...recents.value.filter((c) => c !== value)].slice(0, RECENTS_MAX);
        localStorage.setItem(RECENTS_KEY, JSON.stringify(next));
    } catch {
        // localStorage unavailable (private mode) — non-fatal.
    }
};

const open = (value?: string) => {
    const target = (value ?? code.value).trim();
    if (!target) return;
    remember(target);
    router.visit(`/display/${target}`);
};

onMounted(loadRecents);
</script>

<template>
    <Head title="Display — Enter Code">
        <!-- Same standalone hints as the board, so launching this page from an
             iPhone home-screen icon opens without Safari's chrome. -->
        <meta head-key="amwac" name="apple-mobile-web-app-capable" content="yes" />
        <meta head-key="mwac" name="mobile-web-app-capable" content="yes" />
        <meta head-key="sbs" name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta head-key="awt" name="apple-mobile-web-app-title" content="Keeler Games" />
        <meta head-key="tc" name="theme-color" content="#404040" />
    </Head>

    <div
        class="keeler-app theme-green min-h-screen text-body flex items-center justify-center p-6"
        style="
            background:
                radial-gradient(60vw 40vw at 15% 8%, rgb(var(--color-success) / 0.1), transparent 60%),
                radial-gradient(50vw 40vw at 85% 20%, rgb(var(--color-info) / 0.1), transparent 60%),
                radial-gradient(60vw 50vw at 50% 108%, rgb(var(--color-warning) / 0.1), transparent 60%),
                rgb(var(--color-surface-inset));
        "
    >
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <KeelerLogo
                    variant="full"
                    class="mx-auto h-44 w-auto drop-shadow-[0_10px_30px_rgba(0,0,0,0.5)] sm:h-56"
                />
                <p class="text-muted mt-4">TV Display</p>
            </div>

            <form class="space-y-4" @submit.prevent="open()">
                <div>
                    <label for="code" class="block text-sm font-medium text-muted mb-2">
                        Enter game code
                    </label>
                    <input
                        id="code"
                        :value="code"
                        type="text"
                        inputmode="text"
                        autocapitalize="characters"
                        autocomplete="off"
                        autofocus
                        placeholder="ABC123"
                        class="block w-full rounded-lg border border-border bg-surface-inset text-body placeholder:text-muted transition-all focus:outline-none focus:border-transparent focus-glow text-center text-3xl font-mono font-bold tracking-[0.3em] py-4 uppercase"
                        @input="onInput"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="!canOpen"
                    class="w-full rounded-lg bg-primary py-4 text-lg font-bold text-body transition-colors hover:bg-primary-hover disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Open board
                </button>
            </form>

            <!-- Quick relaunch of codes used on this device -->
            <div v-if="recents.length" class="mt-8">
                <p class="text-sm text-subtle mb-3">Recent</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in recents"
                        :key="c"
                        type="button"
                        class="rounded-lg border border-border bg-surface-overlay px-4 py-2 font-mono font-bold text-body transition-colors hover:bg-surface-elevated"
                        @click="open(c)"
                    >
                        {{ c }}
                    </button>
                </div>
            </div>

            <p class="text-subtle text-xs text-center mt-10 leading-relaxed">
                Tip: add this page to your home screen for a chromeless launch,
                then cast or plug in and enter tonight's code.
            </p>
        </div>
    </div>
</template>
