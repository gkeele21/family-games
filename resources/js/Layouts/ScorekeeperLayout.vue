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
        <div class="border-b-4 border-[#d8b95c] bg-[#0b5d3b] text-[#f7f1e3]">
            <div class="px-4 pt-5 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <svg
                        width="38"
                        height="38"
                        viewBox="0 0 48 48"
                        class="shrink-0"
                        aria-hidden="true"
                    >
                        <rect
                            x="4"
                            y="10"
                            width="26"
                            height="26"
                            rx="6"
                            fill="#f7f1e3"
                            transform="rotate(-8 17 23)"
                        />
                        <circle cx="12" cy="17" r="2.6" fill="#0b5d3b" />
                        <circle cx="22" cy="27" r="2.6" fill="#0b5d3b" />
                        <circle cx="12" cy="27" r="2.6" fill="#d24141" />
                        <circle cx="22" cy="17" r="2.6" fill="#d24141" />
                        <rect
                            x="22"
                            y="16"
                            width="22"
                            height="22"
                            rx="5"
                            fill="#f2d27c"
                            transform="rotate(7 33 27)"
                        />
                        <circle cx="33" cy="27" r="2.8" fill="#0b5d3b" />
                    </svg>
                    <div>
                        <div
                            class="text-xl font-extrabold leading-tight tracking-tight"
                        >
                            <span class="text-[#f2d27c]">Score</span>keeper
                        </div>
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-xs text-[#cfe4d3] hover:text-[#f7f1e3]"
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
                                        class="ml-1 text-xs text-gray-400"
                                        >✓</span
                                    >
                                </DropdownLink>
                                <div class="border-t border-gray-100"></div>
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
                        class="ml-auto whitespace-nowrap rounded-full bg-[#d24141] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#bb3535]"
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
                                ? 'border-[#f7f1e3] bg-[#f7f1e3] text-[#0b5d3b]'
                                : 'border-[#f7f1e3]/40 text-[#f7f1e3]/85 hover:border-[#f7f1e3]/80 hover:text-[#f7f1e3]'
                        "
                    >
                        {{ t.label }}
                    </Link>
                </nav>
            </div>
        </div>

        <!-- Module ground -->
        <div class="min-h-screen bg-[#f6f3ea]">
            <slot />
        </div>
    </AuthenticatedLayout>
</template>
