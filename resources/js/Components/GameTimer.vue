<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Button from '@/Components/Base/Button.vue';

interface Props {
    timerStartedAt: string | null;
    timerDuration: number;
    isHost?: boolean;
    size?: 'md' | 'sm';
    // Hide the Start button (e.g. America Says starts the timer from its steps).
    hideStart?: boolean;
}

const props = withDefaults(defineProps<Props>(), { size: 'md', hideStart: false });

const displayClass = computed(() =>
    props.size === 'sm' ? 'text-4xl py-2 px-6' : 'text-6xl py-4 px-8'
);
const controlsClass = computed(() => (props.size === 'sm' ? 'mt-2' : 'mt-4'));

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
            class="font-mono font-bold rounded-lg transition-all duration-300"
            :class="[displayClass, {
                'bg-success text-white': !isWarning && !isExpired && isRunning,
                'bg-surface-inset text-body border border-border': !isRunning && !isExpired,
                'bg-danger text-white animate-pulse': isWarning,
                'bg-danger/70 text-white': isExpired,
            }]"
        >
            {{ formattedTime }}
        </div>

        <div v-if="isHost" class="flex gap-2 justify-center" :class="controlsClass">
            <Button v-if="!isRunning && !hideStart" variant="success" :size="size" @click="emit('start')">Start</Button>
            <Button v-if="isRunning" variant="accent" :size="size" @click="emit('pause')">Pause</Button>
            <Button variant="muted" :size="size" @click="emit('reset')">Reset</Button>
        </div>
    </div>
</template>
