<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Navigation from '@/Components/PropOff/Base/Navigation.vue';
import PreferencesModal from '@/Components/PropOff/Domain/PreferencesModal.vue';
import Modal from '@/Components/PropOff/Base/Modal.vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';
import { useTheme } from '@/composables/useTheme';

// Theme classes are bound onto the .propoff-app wrapper below,
// which is what scopes the dark design to this module.
const { theme, bgMode } = useTheme();

const showPreferences = ref(false);
const showUserModal = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div
        id="propoff-app"
        class="propoff-app"
        :class="[`theme-${theme}`, `bg-mode-${bgMode}`]"
    >
        <div class="min-h-screen bg-bg">
            <Navigation :user="user">
                <!-- Desktop: Settings gear + User -->
                <template #user>
                    <div v-if="user" class="flex items-center gap-2">
                        <!-- Settings Gear -->
                        <button
                            @click="showPreferences = true"
                            type="button"
                            class="inline-flex items-center justify-center w-9 h-9 text-muted hover:text-primary focus:outline-none transition duration-150"
                            title="Settings"
                        >
                            <Icon name="gear" />
                        </button>

                        <!-- User Name with Icon -->
                        <button
                            @click="showUserModal = true"
                            type="button"
                            class="inline-flex items-center gap-2 h-9 px-3 text-sm font-medium text-body hover:text-primary focus:outline-none transition duration-150"
                        >
                            <Icon name="user" size="sm" />
                            {{ user.name }}
                        </button>
                    </div>
                </template>

                <!-- Mobile: User section -->
                <template #mobileUser="{ close }">
                    <div v-if="user" class="px-4 mb-3">
                        <div class="font-medium text-base text-body">{{ user.name }}</div>
                        <div class="font-medium text-sm text-muted">{{ user.email }}</div>
                    </div>
                    <div v-if="user" class="space-y-1">
                        <button
                            @click="showPreferences = true; close()"
                            class="block w-full ps-3 pe-4 py-2 text-start text-base font-medium text-muted hover:text-body hover:bg-surface-elevated border-l-4 border-transparent hover:border-primary/50 transition duration-150 ease-in-out"
                        >
                            <Icon name="gear" class="mr-2" />
                            Settings
                        </button>
                        <Link
                            :href="route('profile.edit')"
                            class="block w-full ps-3 pe-4 py-2 text-start text-base font-medium text-muted hover:text-body hover:bg-surface-elevated border-l-4 border-transparent hover:border-primary/50 transition duration-150 ease-in-out"
                        >
                            <Icon name="user" class="mr-2" />
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="block w-full ps-3 pe-4 py-2 text-start text-base font-medium text-muted hover:text-body hover:bg-surface-elevated border-l-4 border-transparent hover:border-primary/50 transition duration-150 ease-in-out"
                        >
                            <Icon name="right-from-bracket" class="mr-2" />
                            Log Out
                        </Link>
                    </div>
                </template>
            </Navigation>

            <!-- Page Header (provided by page via slot) -->
            <slot name="header" />

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>

        <!-- Preferences Modal -->
        <PreferencesModal :show="showPreferences" @close="showPreferences = false" />

        <!-- User Modal (Profile, Logout) -->
        <Modal :show="showUserModal" @close="showUserModal = false" max-width="sm">
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-surface-inset rounded-full flex items-center justify-center mx-auto mb-3">
                        <Icon name="user" size="2x" class="text-muted" />
                    </div>
                    <div class="text-lg font-semibold text-body">{{ user?.name }}</div>
                    <div class="text-sm text-muted">{{ user?.email }}</div>
                </div>
                <div class="space-y-3">
                    <Link :href="route('profile.edit')" class="block">
                        <Button variant="secondary" class="w-full" icon="user">
                            Profile
                        </Button>
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full"
                    >
                        <Button variant="danger" class="w-full" icon="right-from-bracket">
                            Log Out
                        </Button>
                    </Link>
                </div>
            </div>
        </Modal>
    </div>
</template>
