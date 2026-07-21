<template>
    <div :class="['flex items-center', flexible ? 'w-full' : '', centered && !flexible ? 'justify-center' : '']">
        <template v-for="(step, index) in steps" :key="index">
            <!-- Step -->
            <div
                :class="[
                    'flex flex-col items-center text-center flex-shrink-0',
                    clickable ? 'cursor-pointer' : '',
                    flexible ? '' : sizes[size].container
                ]"
                @click="clickable && step.action ? step.action() : selectStep(index)"
            >
                <!-- Circle indicator -->
                <div
                    :class="[
                        'flex items-center justify-center rounded-full border-2 transition-colors',
                        sizes[size].circle,
                        getStepClasses(index)
                    ]"
                >
                    <Icon
                        v-if="index < currentStep"
                        name="check"
                        :size="size === 'sm' ? 'xs' : 'sm'"
                    />
                    <span v-else class="step-number font-semibold">{{ index + 1 }}</span>
                </div>

                <!-- Label -->
                <div v-if="step.label" :class="['mt-1 leading-tight', sizes[size].label]">
                    <div :class="getLabelClasses(index)">{{ step.label }}</div>
                    <div v-if="step.description" class="text-gray-400 text-xs">{{ step.description }}</div>
                </div>
            </div>

            <!-- Connector line -->
            <div
                v-if="index < steps.length - 1"
                :class="[
                    'rounded transition-colors',
                    flexible ? 'flex-grow h-0.5 mx-2' : sizes[size].connector,
                    index < currentStep ? 'bg-warning' : 'bg-border'
                ]"
            />
        </template>
    </div>
</template>

<script setup>
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    steps: { type: Array, required: true }, // [{ label: 'Step 1', description: 'Optional', action: fn }]
    currentStep: { type: Number, default: 0 },
    clickable: { type: Boolean, default: false },
    centered: { type: Boolean, default: true },
    size: { type: String, default: 'md' }, // sm, md, lg
    flexible: { type: Boolean, default: false }, // Allow labels to take natural width
});

const emit = defineEmits(['update:currentStep', 'step-click']);

const sizes = {
    sm: {
        container: 'w-12',
        circle: 'w-6 h-6 text-xs',
        label: 'text-xs',
        connector: 'w-8 sm:w-12 h-0.5 mx-1',
    },
    md: {
        container: 'w-16',
        circle: 'w-8 h-8 text-sm',
        label: 'text-sm',
        connector: 'w-8 sm:w-16 h-1 mx-2',
    },
    lg: {
        container: 'w-24',
        circle: 'w-10 h-10 text-base',
        label: 'text-base',
        connector: 'w-12 sm:w-24 h-1 mx-3',
    },
};

function getStepClasses(index) {
    if (index < props.currentStep) {
        // Completed
        return 'bg-warning border-warning text-body';
    } else if (index === props.currentStep) {
        // Current
        return 'bg-secondary border-secondary text-white';
    } else {
        // Upcoming
        return 'bg-white border-border text-muted';
    }
}

function getLabelClasses(index) {
    if (index <= props.currentStep) {
        return 'font-semibold text-white';
    }
    return 'text-gray-400';
}

function selectStep(index) {
    if (props.clickable) {
        emit('update:currentStep', index);
        emit('step-click', index);
    }
}
</script>
