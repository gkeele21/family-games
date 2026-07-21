<template>
    <div class="inline-flex items-center gap-3">
        <span v-if="label" class="text-sm text-muted font-medium">{{ label }}</span>
        <div class="inline-flex items-center gap-2 bg-surface rounded-lg px-3 py-2">
        <!-- Time display / edit -->
        <div v-if="!isRunning && !isFinished" class="flex items-center font-mono font-bold text-2xl">
            <input
                type="number"
                v-model.number="minutes"
                min="0"
                max="59"
                class="w-12 text-center bg-white rounded border border-border p-1 focus:outline-none focus:border-primary"
            />
            <span class="mx-1">:</span>
            <input
                type="number"
                v-model.number="seconds"
                min="0"
                max="59"
                class="w-12 text-center bg-white rounded border border-border p-1 focus:outline-none focus:border-primary"
            />
        </div>
        <span
            v-else
            class="font-mono font-bold tabular-nums text-2xl min-w-[5rem] text-center"
            :class="isFinished ? 'text-danger' : 'text-body'"
        >
            {{ displayTime }}
        </span>

        <!-- Controls -->
        <div class="flex items-center gap-1">
            <button
                v-if="!isRunning && !isFinished"
                @click="startTimer"
                class="p-2 rounded-full bg-primary text-white hover:bg-primary/80 transition-colors"
                title="Start"
            >
                <Icon name="play" size="sm" />
            </button>

            <button
                v-if="isRunning"
                @click="pauseTimer"
                class="p-2 rounded-full bg-warning text-white hover:bg-warning/80 transition-colors"
                title="Pause"
            >
                <Icon name="pause" size="sm" />
            </button>

            <button
                @click="resetTimer"
                class="p-2 rounded-full hover:bg-gray-300 transition-colors"
                :class="isFinished ? 'bg-primary text-white hover:bg-primary/80' : 'bg-white text-muted'"
                title="Reset"
            >
                <Icon name="rotate-right" size="sm" />
            </button>
        </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    initialMinutes: { type: Number, default: 1 },
    initialSeconds: { type: Number, default: 30 },
    label: { type: String, default: '' },
});

const emit = defineEmits(['start', 'pause', 'reset', 'finish']);

const minutes = ref(props.initialMinutes);
const seconds = ref(props.initialSeconds);
const isRunning = ref(false);
const isFinished = ref(false);
const intervalId = ref(null);

const displayTime = computed(() => {
    const m = String(minutes.value).padStart(2, '0');
    const s = String(seconds.value).padStart(2, '0');
    return `${m}:${s}`;
});

function startTimer() {
    if (minutes.value <= 0 && seconds.value <= 0) return;
    isRunning.value = true;
    isFinished.value = false;
    emit('start');

    intervalId.value = setInterval(() => {
        if (seconds.value > 0) {
            seconds.value--;
        } else if (minutes.value > 0) {
            minutes.value--;
            seconds.value = 59;
        } else {
            finishTimer();
        }
    }, 1000);
}

function pauseTimer() {
    isRunning.value = false;
    if (intervalId.value) {
        clearInterval(intervalId.value);
        intervalId.value = null;
    }
    emit('pause');
}

function resetTimer() {
    pauseTimer();
    minutes.value = props.initialMinutes;
    seconds.value = props.initialSeconds;
    isFinished.value = false;
    emit('reset');
}

function finishTimer() {
    pauseTimer();
    isFinished.value = true;
    emit('finish');
}

onUnmounted(() => {
    if (intervalId.value) {
        clearInterval(intervalId.value);
    }
});
</script>
