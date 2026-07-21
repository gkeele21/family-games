<template>
    <div v-if="message" class="flex text-danger mt-1">
        <Icon name="circle-exclamation" class="mr-2" />
        <p class="text-sm font-semibold">{{ message }}</p>
    </div>
</template>

<script setup>
    import Icon from '@/Components/Base/Icon.vue';

    defineProps({
        message: String,
    });
</script>
