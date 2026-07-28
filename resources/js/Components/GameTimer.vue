<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

interface Props {
    timerStartedAt: string | null;
    timerDuration: number;
    isHost?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'start'): void;
    (e: 'pause'): void;
    (e: 'reset'): void;
    (e: 'expired'): void;
}>();

const remainingSeconds = ref(props.timerDuration);
let intervalId: number | null = null;

const isRunning = computed(() => props.timerStartedAt !== null);

const formattedTime = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const isWarning = computed(() => remainingSeconds.value <= 10 && remainingSeconds.value > 0);
const isExpired = computed(() => remainingSeconds.value <= 0);

const updateTimer = () => {
    if (props.timerStartedAt) {
        const startTime = new Date(props.timerStartedAt).getTime();
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        remainingSeconds.value = Math.max(0, props.timerDuration - elapsed);

        if (remainingSeconds.value <= 0) {
            emit('expired');
        }
    } else {
        remainingSeconds.value = props.timerDuration;
    }
};

onMounted(() => {
    updateTimer();
    intervalId = window.setInterval(updateTimer, 100);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});

watch(() => props.timerDuration, () => {
    updateTimer();
});

watch(() => props.timerStartedAt, () => {
    updateTimer();
});
</script>

<template>
    <div class="text-center">
        <div
            class="text-6xl font-mono font-bold py-4 px-8 rounded-lg transition-all duration-300"
            :class="{
                'bg-success text-white': !isWarning && !isExpired && isRunning,
                'bg-surface-inset text-body border border-border': !isRunning && !isExpired,
                'bg-danger text-white animate-pulse': isWarning,
                'bg-danger/70 text-white': isExpired,
            }"
        >
            {{ formattedTime }}
        </div>

        <div v-if="isHost" class="flex gap-2 mt-4 justify-center">
            <button
                v-if="!isRunning"
                @click="emit('start')"
                class="px-5 py-2 bg-success text-white font-semibold rounded-lg hover:opacity-90 transition"
            >
                Start
            </button>
            <button
                v-if="isRunning"
                @click="emit('pause')"
                class="px-5 py-2 bg-warning text-white font-semibold rounded-lg hover:opacity-90 transition"
            >
                Pause
            </button>
            <button
                @click="emit('reset')"
                class="px-5 py-2 bg-surface-overlay text-body font-semibold rounded-lg border border-border hover:bg-surface-elevated transition"
            >
                Reset
            </button>
        </div>
    </div>
</template>
