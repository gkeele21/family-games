<script setup lang="ts">
import { ref } from 'vue';
import KeelerLogo from '@/Components/KeelerLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const { theme } = useTheme();
const showingNavigationDropdown = ref(false);
const showingUserMenu = ref(false);

const user = usePage().props.auth.user as { name: string; email: string };

const navItems = [
    { label: 'Dashboard', route: 'dashboard', active: 'dashboard' },
    { label: 'Trivia', route: 'games.index', active: 'games.*' },
    { label: 'Scorekeeper', route: 'scorekeeper.home', active: 'scorekeeper.*' },
    { label: 'PropOff', route: 'propoff.home', active: 'propoff.*' },
];

// Delay so a click on a menu item lands before the menu closes.
const closeUserMenuSoon = () => {
    window.setTimeout(() => (showingUserMenu.value = false), 150);
};

const initials = user.name
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
</script>

<template>
    <div class="keeler-app" :class="`theme-${theme}`">
        <div class="min-h-screen bg-surface-inset">
            <nav class="sticky top-0 z-30 border-b border-border bg-surface-header/85 backdrop-blur-md">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center gap-8">
                            <Link href="/" class="flex shrink-0 items-center">
                                <KeelerLogo variant="wordmark" class="h-10 w-auto" />
                            </Link>

                            <div class="hidden items-center gap-1 sm:flex">
                                <Link
                                    v-for="item in navItems"
                                    :key="item.route"
                                    :href="route(item.route)"
                                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                                    :class="
                                        route().current(item.active)
                                            ? 'text-primary'
                                            : 'text-muted hover:bg-white/5 hover:text-body'
                                    "
                                >
                                    {{ item.label }}
                                </Link>
                            </div>
                        </div>

                        <!-- User menu -->
                        <div class="hidden items-center sm:flex">
                            <div class="relative">
                                <button
                                    type="button"
                                    class="flex items-center gap-3 rounded-full border border-border py-1 pl-4 pr-1 text-sm font-semibold text-body transition hover:border-border-strong"
                                    @click="showingUserMenu = !showingUserMenu"
                                    @blur="closeUserMenuSoon"
                                >
                                    {{ user.name }}
                                    <span
                                        class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-info to-primary text-xs font-extrabold text-white"
                                    >
                                        {{ initials }}
                                    </span>
                                </button>

                                <div
                                    v-show="showingUserMenu"
                                    class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-border bg-surface-elevated shadow-2xl"
                                >
                                    <Link
                                        :href="route('profile.edit')"
                                        class="block px-4 py-2.5 text-sm text-body transition hover:bg-white/5"
                                    >
                                        Profile
                                    </Link>
                                    <Link
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        class="block w-full px-4 py-2.5 text-left text-sm text-body transition hover:bg-white/5"
                                    >
                                        Log Out
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-2 text-muted transition hover:bg-white/5 hover:text-body"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 px-4 pb-3 pt-2">
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            class="block rounded-lg px-3 py-2 text-base font-semibold transition"
                            :class="
                                route().current(item.active)
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-muted hover:bg-white/5 hover:text-body'
                            "
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                    <div class="border-t border-border px-4 py-4">
                        <div class="text-base font-semibold text-body">{{ user.name }}</div>
                        <div class="text-sm text-muted">{{ user.email }}</div>
                        <div class="mt-3 space-y-1">
                            <Link
                                :href="route('profile.edit')"
                                class="block rounded-lg px-3 py-2 text-base font-medium text-muted transition hover:bg-white/5 hover:text-body"
                            >
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="block w-full rounded-lg px-3 py-2 text-left text-base font-medium text-muted transition hover:bg-white/5 hover:text-body"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="border-b border-border bg-surface-header/60">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
