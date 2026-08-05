<script setup lang="ts">
import { computed } from 'vue';

// Renders text where any run of 2+ underscores (a fill-in-the-blank the host
// types into a question) is squished into one continuous line instead of
// spaced-out underscores — matching the projector board (AmericaSaysDisplay).
const props = defineProps<{ text: string | null | undefined }>();

const segments = computed<{ text: string; blank?: boolean }[]>(() => {
    const text = props.text ?? '';
    const segs: { text: string; blank?: boolean }[] = [];
    const re = /_{2,}/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) segs.push({ text: text.slice(last, m.index) });
        segs.push({ text: m[0], blank: true });
        last = m.index + m[0].length;
    }
    if (last < text.length) segs.push({ text: text.slice(last) });
    return segs;
});
</script>

<template><span><template v-for="(seg, i) in segments" :key="i"><span v-if="seg.blank" class="blank-run">{{ seg.text }}</span><template v-else>{{ seg.text }}</template></template></span></template>

<style scoped>
/* Negative tracking collapses the underscores into a single unbroken line. */
.blank-run { letter-spacing: -0.15em; }
</style>
