<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();

// Gentle drifting table confetti: card suits and dice, not a rave.
const pieces = ref<
    Array<{ id: number; glyph: string; left: string; delay: string; duration: string; size: string }>
>([]);
const glyphs = ['♠', '♥', '♦', '♣', '⚄', '⚅', '⚂'];

onMounted(() => {
    for (let i = 0; i < 24; i++) {
        pieces.value.push({
            id: i,
            glyph: glyphs[i % glyphs.length],
            left: `${Math.random() * 100}%`,
            delay: `${Math.random() * 12}s`,
            duration: `${14 + Math.random() * 10}s`,
            size: `${14 + Math.random() * 18}px`,
        });
    }
});
</script>

<template>
    <Head title="Family Game Night" />

    <div
        class="relative min-h-screen overflow-hidden bg-[#0b5d3b] text-[#f7f1e3]"
    >
        <!-- Felt-table glow -->
        <div
            class="pointer-events-none absolute inset-0"
            style="
                background:
                    radial-gradient(
                        900px 480px at 50% -10%,
                        rgba(247, 241, 227, 0.14),
                        transparent 65%
                    ),
                    radial-gradient(
                        700px 500px at 85% 110%,
                        rgba(242, 210, 124, 0.1),
                        transparent 60%
                    );
            "
        ></div>

        <!-- Drifting suits & dice -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <span
                v-for="p in pieces"
                :key="p.id"
                class="drift absolute select-none"
                :class="
                    ['♥', '♦'].includes(p.glyph)
                        ? 'text-[#d24141]/25'
                        : 'text-[#f7f1e3]/15'
                "
                :style="{
                    left: p.left,
                    fontSize: p.size,
                    animationDelay: p.delay,
                    animationDuration: p.duration,
                }"
                >{{ p.glyph }}</span
            >
        </div>

        <!-- Top nav -->
        <nav
            v-if="canLogin"
            class="relative z-20 flex items-center justify-end gap-4 px-6 py-5"
        >
            <template v-if="$page.props.auth.user">
                <span class="text-sm text-[#cfe4d3]">
                    Hey,
                    <span class="font-semibold text-[#f2d27c]">{{
                        $page.props.auth.user.first_name
                    }}</span
                    >!
                </span>
                <Link
                    :href="route('games.index')"
                    class="rounded-full border border-[#f7f1e3]/40 px-5 py-2 text-sm font-semibold hover:border-[#f7f1e3]/80"
                >
                    Party games
                </Link>
                <Link
                    :href="route('scorekeeper.home')"
                    class="rounded-full border border-[#f7f1e3]/40 px-5 py-2 text-sm font-semibold hover:border-[#f7f1e3]/80"
                >
                    Scorekeeper
                </Link>
            </template>
            <template v-else>
                <Link
                    :href="route('login')"
                    class="px-4 py-2 text-sm font-semibold text-[#f7f1e3]/90 hover:text-[#f2d27c]"
                >
                    Log in
                </Link>
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-full bg-[#f2d27c] px-6 py-2 text-sm font-bold text-[#0b5d3b] shadow-lg hover:bg-[#f8dd94]"
                >
                    Sign up
                </Link>
            </template>
        </nav>

        <!-- Hero -->
        <div class="relative z-10 mx-auto max-w-6xl px-6 pt-10 text-center sm:pt-16">
            <div class="mb-6 flex justify-center">
                <svg width="84" height="84" viewBox="0 0 48 48" aria-hidden="true">
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
            </div>

            <h1
                class="mb-4 text-5xl font-black tracking-tight text-[#f7f1e3] md:text-7xl"
                style="text-wrap: balance"
            >
                Family <span class="text-[#f2d27c]">Game Night</span>
            </h1>

            <p class="mx-auto mb-12 max-w-2xl text-lg text-[#cfe4d3] md:text-xl">
                One table for everything you play — host live game-show battles,
                and keep score of every card and board game, round by round.
            </p>

            <!-- The two halves of the product -->
            <div class="mb-14 grid grid-cols-1 gap-6 text-left md:grid-cols-3">
                <!-- Party games -->
                <div
                    class="flex flex-col rounded-2xl border-t-4 border-[#d24141] bg-[#fffdf7] p-8 text-gray-800 shadow-2xl"
                >
                    <div class="mb-3 text-4xl">📺</div>
                    <h2 class="mb-1 text-2xl font-extrabold text-[#0b5d3b]">
                        Party games
                    </h2>
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-[#d24141]">
                        Live game-show battles
                    </p>
                    <ul class="mb-6 space-y-2 text-sm text-gray-600">
                        <li>• Family Feud, America Says, Oodles &amp; more</li>
                        <li>• Host on a big screen, everyone joins with a code</li>
                        <li>• Teams, timers, steals, and buzzer-beater rounds</li>
                    </ul>
                    <div class="mt-auto flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('games.create')"
                            class="rounded-full bg-[#d24141] px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-[#bb3535]"
                        >
                            Host a game
                        </Link>
                        <Link
                            :href="route('player.join')"
                            class="rounded-full border-2 border-[#d24141]/60 px-6 py-2.5 text-sm font-bold text-[#d24141] hover:bg-[#d24141]/5"
                        >
                            Join with a code
                        </Link>
                    </div>
                </div>

                <!-- Scorekeeper -->
                <div
                    class="flex flex-col rounded-2xl border-t-4 border-[#f2d27c] bg-[#fffdf7] p-8 text-gray-800 shadow-2xl"
                >
                    <div class="mb-3 text-4xl">🎲</div>
                    <h2 class="mb-1 text-2xl font-extrabold text-[#0b5d3b]">
                        Scorekeeper
                    </h2>
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-[#8a6d1a]">
                        The score pad for game night
                    </p>
                    <ul class="mb-6 space-y-2 text-sm text-gray-600">
                        <li>• Any card or board game — build your own score pad</li>
                        <li>• Track multiple scores per round, solo or in teams</li>
                        <li>
                            • Live standings; players can enter their own scores
                            from their phone
                        </li>
                    </ul>
                    <div class="mt-auto flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('scorekeeper.home')"
                            class="rounded-full bg-[#0b5d3b] px-6 py-2.5 text-sm font-bold text-[#f7f1e3] shadow hover:bg-[#0d6f47]"
                        >
                            Open Scorekeeper
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register')"
                            class="rounded-full bg-[#0b5d3b] px-6 py-2.5 text-sm font-bold text-[#f7f1e3] shadow hover:bg-[#0d6f47]"
                        >
                            Start keeping score
                        </Link>
                    </div>
                </div>

                <!-- PropOff (dark, matching its in-module design) -->
                <div
                    class="flex flex-col rounded-2xl border-t-4 border-[#57d025] bg-[#1f1f1f] p-8 text-gray-200 shadow-2xl"
                >
                    <div class="mb-3 text-4xl">🏈</div>
                    <h2 class="mb-1 text-2xl font-extrabold text-[#f5f5f5]">
                        PropOff
                    </h2>
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-[#57d025]">
                        Predict the big game
                    </p>
                    <ul class="mb-6 space-y-2 text-sm text-gray-400">
                        <li>• Prop-bet style predictions for any event</li>
                        <li>• Captains run groups; friends join with a link — no
                            account needed</li>
                        <li>• Grade the answers live and crown a champion</li>
                    </ul>
                    <div class="mt-auto flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('propoff.home')"
                            class="rounded-full bg-[#57d025] px-6 py-2.5 text-sm font-bold text-[#0f0f0f] shadow hover:bg-[#4db820]"
                        >
                            Make your picks
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register')"
                            class="rounded-full bg-[#57d025] px-6 py-2.5 text-sm font-bold text-[#0f0f0f] shadow hover:bg-[#4db820]"
                        >
                            Start predicting
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Primary CTA for guests -->
            <div
                v-if="!$page.props.auth.user && canRegister"
                class="mb-16 flex flex-col items-center gap-3"
            >
                <Link
                    :href="route('register')"
                    class="rounded-full bg-[#f2d27c] px-10 py-4 text-xl font-black text-[#0b5d3b] shadow-2xl transition hover:scale-105 hover:bg-[#f8dd94]"
                >
                    Get started free
                </Link>
                <span class="text-sm text-[#cfe4d3]"
                    >Invite the whole household — everyone sees the same games
                    and history.</span
                >
            </div>

            <!-- How it works -->
            <div class="pb-16 text-[#cfe4d3]">
                <p class="mb-6 text-lg font-semibold text-[#f7f1e3]">
                    How game night works
                </p>
                <div class="flex flex-wrap justify-center gap-8 text-sm">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#f2d27c] font-bold text-[#0b5d3b]"
                        >
                            1
                        </div>
                        <span>Set up your household &amp; players</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#d24141] font-bold text-white"
                        >
                            2
                        </div>
                        <span>Pick a party game or a score pad</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#fffdf7] font-bold text-[#0b5d3b]"
                        >
                            3
                        </div>
                        <span>Play — the scores take care of themselves</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer
            class="relative z-10 border-t border-[#f7f1e3]/15 py-4 text-center text-sm text-[#cfe4d3]/60"
        >
            Made with love for family game nights
        </footer>
    </div>
</template>

<style scoped>
@keyframes drift {
    0% {
        transform: translateY(-8vh) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(108vh) rotate(180deg);
        opacity: 0;
    }
}

.drift {
    animation: drift linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .drift {
        animation: none;
        display: none;
    }
}
</style>
