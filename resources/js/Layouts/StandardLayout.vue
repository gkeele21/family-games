<script setup lang="ts">
/**
 * StandardLayout — the shared Keeler Games app shell (dark neon theme).
 * Modeled on the PropOff game layout: emblem + configurable context menu +
 * gear/profile over a grayish ground, with the orange accent-glow divider.
 *
 * Pages migrate onto this over time. The Dashboard uses it with no context
 * menu (games are reached from the hub tiles); each game module passes its
 * own `navItems` plus `backToDashboard` for a link home.
 */
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import KeelerLogo from '@/Components/KeelerLogo.vue';
import PreferencesModal from '@/Components/PropOff/Domain/PreferencesModal.vue';
import Modal from '@/Components/Base/Modal.vue';
import Button from '@/Components/Base/Button.vue';
import Icon from '@/Components/Base/Icon.vue';
import { useTheme } from '@/composables/useTheme';

interface NavItem {
    label: string;
    route: string;
    activeMatch?: string[];
}

withDefaults(
    defineProps<{
        /** Per-context menu; empty on the Dashboard. */
        navItems?: NavItem[];
        /** Show a "← Dashboard" link (used inside a game module). */
        backToDashboard?: boolean;
        /** Pin the page-header band below the top nav so it doesn't scroll away. */
        stickyHeader?: boolean;
    }>(),
    { navItems: () => [], backToDashboard: false, stickyHeader: false },
);

const { theme, bgMode } = useTheme();
const user = computed(() => usePage().props.auth.user as { name: string; email: string } | undefined);

const showPreferences = ref(false);
const showUserModal = ref(false);

const isActive = (item: NavItem) => {
    const matches = item.activeMatch ?? [item.route, `${item.route}.*`];
    return matches.some((pattern) => route().current(pattern));
};
</script>

<template>
    <!-- id="propoff-app" is the teleport anchor the shared UI kit targets
         (Modal, PreferencesModal, dropdowns, tooltips, toasts all
         `teleport to="#propoff-app"`). Theme tokens come from .keeler-app. -->
    <div id="propoff-app" class="keeler-app" :class="[`theme-${theme}`, `bg-mode-${bgMode}`]">
        <div class="min-h-screen bg-bg">
            <!-- Top bar: emblem · context menu · gear + profile, with the orange glow divider -->
            <nav
                class="sticky top-0 z-40 border-b border-warning/30 bg-surface shadow-[0_4px_16px_-2px_rgb(var(--color-warning)/0.35)]"
            >
                <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
                    <div class="flex h-[72px] items-center justify-between gap-6">
                        <!-- Left: emblem (+ optional back link) -->
                        <div class="flex shrink-0 items-center gap-4">
                            <Link :href="route('dashboard')" class="flex items-center">
                                <KeelerLogo variant="wordmark" class="h-11 w-auto sm:h-14" />
                            </Link>
                            <Link
                                v-if="backToDashboard"
                                :href="route('dashboard')"
                                class="hidden text-xs font-semibold text-muted transition hover:text-body sm:inline"
                            >
                                &larr; Dashboard
                            </Link>
                        </div>

                        <!-- Center: per-context menu (empty on the Dashboard) -->
                        <div v-if="navItems.length" class="hidden items-center gap-8 sm:flex">
                            <Link
                                v-for="item in navItems"
                                :key="item.route"
                                :href="route(item.route)"
                                class="text-sm font-medium transition"
                                :class="isActive(item) ? 'text-body' : 'text-muted hover:text-body'"
                            >
                                {{ item.label }}
                            </Link>
                        </div>

                        <!-- Right: settings + user -->
                        <div v-if="user" class="flex items-center gap-1">
                            <button
                                type="button"
                                title="Settings"
                                class="grid h-9 w-9 place-items-center rounded-lg text-muted transition hover:text-primary"
                                @click="showPreferences = true"
                            >
                                <Icon name="gear" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-body transition hover:text-primary"
                                @click="showUserModal = true"
                            >
                                <Icon name="user" size="sm" />
                                <span class="hidden sm:inline">{{ user.name }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Optional page header band -->
            <header
                v-if="$slots.header"
                class="border-b border-border"
                :class="stickyHeader ? 'sticky top-[72px] z-30 bg-surface-header/95 backdrop-blur' : 'bg-surface-header/60'"
            >
                <div class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>

        <!-- Accent / background preferences (gear) -->
        <PreferencesModal :show="showPreferences" @close="showPreferences = false" />

        <!-- Profile / logout (user) -->
        <Modal :show="showUserModal" max-width="sm" @close="showUserModal = false">
            <div class="p-6">
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-surface-inset">
                        <Icon name="user" size="2x" class="text-muted" />
                    </div>
                    <div class="text-lg font-semibold text-body">{{ user?.name }}</div>
                    <div class="text-sm text-muted">{{ user?.email }}</div>
                </div>
                <div class="space-y-3">
                    <Link :href="route('profile.edit')" class="block">
                        <Button variant="secondary" class="w-full" icon="user">Profile</Button>
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="w-full">
                        <Button variant="danger" class="w-full" icon="right-from-bracket">Log Out</Button>
                    </Link>
                </div>
            </div>
        </Modal>
    </div>
</template>
