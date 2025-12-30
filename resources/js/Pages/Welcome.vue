<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();

const confettiColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8'];
const confetti = ref<Array<{ id: number; left: string; delay: string; duration: string; color: string }>>([]);

onMounted(() => {
    for (let i = 0; i < 50; i++) {
        confetti.value.push({
            id: i,
            left: `${Math.random() * 100}%`,
            delay: `${Math.random() * 5}s`,
            duration: `${3 + Math.random() * 4}s`,
            color: confettiColors[Math.floor(Math.random() * confettiColors.length)],
        });
    }
});
</script>

<template>
    <Head title="Family Game Night" />

    <div class="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 overflow-hidden relative">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <!-- Floating confetti -->
            <div
                v-for="piece in confetti"
                :key="piece.id"
                class="absolute w-3 h-3 rounded-sm animate-confetti opacity-60"
                :style="{
                    left: piece.left,
                    animationDelay: piece.delay,
                    animationDuration: piece.duration,
                    backgroundColor: piece.color,
                }"
            ></div>

            <!-- Glowing orbs -->
            <div class="absolute top-20 left-10 w-72 h-72 bg-pink-500/30 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
            <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-yellow-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s"></div>
        </div>

        <!-- Navigation -->
        <nav v-if="canLogin" class="absolute top-0 right-0 p-6 z-50">
            <div class="flex items-center gap-4">
                <template v-if="$page.props.auth.user">
                    <span class="text-white/80">
                        Hey, <span class="font-semibold text-yellow-400">{{ $page.props.auth.user.first_name }}</span>!
                    </span>
                    <Link
                        :href="route('games.index')"
                        class="px-6 py-2 bg-white/10 backdrop-blur-sm text-white rounded-full hover:bg-white/20 transition font-semibold"
                    >
                        My Games
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="px-6 py-2 text-white hover:text-yellow-300 transition font-semibold"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-6 py-2 bg-yellow-500 text-gray-900 rounded-full hover:bg-yellow-400 transition font-bold shadow-lg hover:shadow-yellow-500/50"
                    >
                        Sign Up
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="relative z-10 flex flex-col items-center justify-center min-h-screen px-6 text-center">
            <!-- Logo/Title Section -->
            <div class="mb-8 animate-bounce-slow">
                <div class="text-8xl mb-4">
                    <span class="inline-block animate-wiggle" style="animation-delay: 0s">&#127922;</span>
                    <span class="inline-block animate-wiggle" style="animation-delay: 0.1s">&#127918;</span>
                    <span class="inline-block animate-wiggle" style="animation-delay: 0.2s">&#127881;</span>
                </div>
            </div>

            <h1 class="text-6xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-pink-500 to-purple-500 mb-4 animate-gradient">
                Family Game Night
            </h1>

            <p class="text-xl md:text-2xl text-white/80 mb-12 max-w-2xl">
                Host epic game show battles with your friends and family!
                Play Family Feud, America Says, Oodles, and more!
            </p>

            <!-- Game Type Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 max-w-5xl w-full">
                <!-- Family Feud -->
                <div class="group bg-gradient-to-br from-red-500 to-orange-500 p-6 rounded-2xl shadow-2xl transform hover:scale-105 transition-all duration-300 hover:shadow-red-500/50">
                    <div class="text-5xl mb-4 group-hover:animate-bounce">&#128170;</div>
                    <h3 class="text-2xl font-bold text-white mb-2">Family Feud</h3>
                    <p class="text-white/80 text-sm">Survey says... it's time for a family showdown!</p>
                </div>

                <!-- America Says -->
                <div class="group bg-gradient-to-br from-blue-500 to-indigo-600 p-6 rounded-2xl shadow-2xl transform hover:scale-105 transition-all duration-300 hover:shadow-blue-500/50">
                    <div class="text-5xl mb-4 group-hover:animate-bounce">&#127479;&#127480;</div>
                    <h3 class="text-2xl font-bold text-white mb-2">America Says</h3>
                    <p class="text-white/80 text-sm">Race against the clock to guess what America says!</p>
                </div>

                <!-- Oodles -->
                <div class="group bg-gradient-to-br from-green-500 to-teal-500 p-6 rounded-2xl shadow-2xl transform hover:scale-105 transition-all duration-300 hover:shadow-green-500/50">
                    <div class="text-5xl mb-4 group-hover:animate-bounce">&#127922;</div>
                    <h3 class="text-2xl font-bold text-white mb-2">Oodles</h3>
                    <p class="text-white/80 text-sm">Word-guessing fun with letter challenges!</p>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('games.create')"
                    class="px-10 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 text-xl font-bold rounded-full shadow-2xl hover:shadow-yellow-500/50 transform hover:scale-105 transition-all duration-300"
                >
                    Host a Game
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="route('register')"
                    class="px-10 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 text-xl font-bold rounded-full shadow-2xl hover:shadow-yellow-500/50 transform hover:scale-105 transition-all duration-300"
                >
                    Get Started Free
                </Link>

                <Link
                    :href="route('player.join')"
                    class="px-10 py-4 bg-white/10 backdrop-blur-sm text-white text-xl font-bold rounded-full border-2 border-white/30 hover:bg-white/20 transform hover:scale-105 transition-all duration-300"
                >
                    Join a Game
                </Link>
            </div>

            <!-- How it works -->
            <div class="mt-20 text-white/60">
                <p class="text-lg mb-6 font-semibold">How it works</p>
                <div class="flex flex-wrap justify-center gap-8">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-gray-900 font-bold">1</div>
                        <span>Host creates game</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-500 rounded-full flex items-center justify-center text-white font-bold">2</div>
                        <span>Share the code</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">3</div>
                        <span>Play together!</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="absolute bottom-0 w-full text-center py-4 text-white/40 text-sm">
            Made with love for family game nights
        </footer>
    </div>
</template>

<style scoped>
@keyframes confetti {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

@keyframes wiggle {
    0%, 100% { transform: rotate(-5deg); }
    50% { transform: rotate(5deg); }
}

@keyframes gradient {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

@keyframes bounce-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.animate-confetti {
    animation: confetti linear infinite;
}

.animate-wiggle {
    animation: wiggle 0.5s ease-in-out infinite;
}

.animate-gradient {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}

.animate-bounce-slow {
    animation: bounce-slow 2s ease-in-out infinite;
}
</style>
