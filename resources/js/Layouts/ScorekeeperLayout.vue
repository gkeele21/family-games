<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    household: { id: number; name: string };
    tab: 'games' | 'templates' | 'people';
}>();

const page = usePage();
const households = computed(() => page.props.households ?? []);

const tabs = computed(() => [
    {
        key: 'games',
        label: 'Games',
        href: route('scorekeeper.households.games.index', props.household.id),
    },
    {
        key: 'templates',
        label: 'Game templates',
        href: route(
            'scorekeeper.households.templates.index',
            props.household.id,
        ),
    },
    {
        key: 'people',
        label: 'People',
        href: route('scorekeeper.households.people', props.household.id),
    },
]);
</script>

<template>
    <AuthenticatedLayout>
        <!-- Scorekeeper module band -->
        <div class="border-b border-border bg-surface-header text-body">
            <div class="px-4 pt-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <!-- Red diamond mark (module logo color) -->
                    <svg
                        class="h-8 w-[27px] shrink-0"
                        viewBox="0 0 40 48"
                        aria-hidden="true"
                        style="filter: drop-shadow(0 0 10px rgb(var(--color-danger) / 0.45))"
                    >
                        <polygon
                            points="20,2 38,24 20,46 2,24"
                            fill="rgb(var(--color-danger))"
                        />
                    </svg>
                    <div>
                        <div
                            class="text-xl font-extrabold leading-none tracking-tight"
                        >
                            <span class="text-body">SCORE</span
                            ><span class="text-danger">KEEPER</span>
                        </div>
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="mt-1 flex items-center gap-1 text-xs text-muted hover:text-body"
                                >
                                    {{ household.name }}
                                    <svg
                                        class="h-3 w-3"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink
                                    v-for="h in households"
                                    :key="h.id"
                                    :href="
                                        route(
                                            'scorekeeper.households.games.index',
                                            h.id,
                                        )
                                    "
                                >
                                    <span
                                        :class="{
                                            'font-semibold':
                                                h.id === household.id,
                                        }"
                                        >{{ h.name }}</span
                                    >
                                    <span
                                        v-if="h.id === household.id"
                                        class="ml-1 text-xs text-primary"
                                        >✓</span
                                    >
                                </DropdownLink>
                                <div class="border-t border-border"></div>
                                <DropdownLink
                                    :href="route('scorekeeper.households.index')"
                                >
                                    Manage households…
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                    <Link
                        :href="
                            route(
                                'scorekeeper.households.games.create',
                                household.id,
                            )
                        "
                        class="ml-auto whitespace-nowrap rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover"
                    >
                        ＋ New game
                    </Link>
                </div>

                <nav class="mt-4 flex gap-2 overflow-x-auto pb-3">
                    <Link
                        v-for="t in tabs"
                        :key="t.key"
                        :href="t.href"
                        class="whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-semibold transition"
                        :class="
                            t.key === tab
                                ? 'border-primary/45 bg-primary/15 text-primary'
                                : 'border-border-strong text-muted hover:border-muted hover:text-body'
                        "
                    >
                        {{ t.label }}
                    </Link>
                </nav>
            </div>
        </div>

        <!-- Module ground -->
        <div class="min-h-screen bg-bg">
            <slot />
        </div>
    </AuthenticatedLayout>
</template>
