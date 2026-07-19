<template>
    <!-- PDF Viewer -->
    <div v-if="isPdf" class="rounded-lg overflow-hidden border border-gray-200" :class="width">
        <iframe
            :src="pdfUrl"
            :title="title"
            class="w-full"
            :style="pdfFitContent ? { height: '1200px' } : { height: pdfHeight, minHeight: '500px' }"
        />
    </div>

    <!-- Video Embed (YouTube, etc.) -->
    <div v-else class="relative rounded-lg overflow-hidden" :class="width">
        <div class="h-0 pt-[56.25%]">
            <iframe
                :src="videoUrl"
                :title="title"
                class="w-full h-full absolute top-0 left-0"
                allowfullscreen
                allowtransparency
                allow="autoplay"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    url: String,
    title: { type: String, default: 'Media Viewer' },
    width: { type: String, default: 'w-full' },
    pdfHeight: { type: String, default: '80vh' },
    pdfFitContent: { type: Boolean, default: false },
});

const isPdf = computed(() => {
    if (!props.url) return false;
    const lowerUrl = props.url.toLowerCase();
    return lowerUrl.endsWith('.pdf') || lowerUrl.includes('.pdf?') || lowerUrl.includes('application/pdf');
});

const pdfUrl = computed(() => {
    if (!props.url) return '';
    // Add parameters to hide sidebar and set 100% zoom
    const separator = props.url.includes('#') ? '&' : '#';
    return `${props.url}${separator}navpanes=0&zoom=100`;
});

const videoUrl = computed(() => {
    if (!props.url) return '';
    // Convert YouTube watch URLs to embed URLs
    return props.url.replace('/watch?v=', '/embed/');
});
</script>
