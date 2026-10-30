<script setup lang="ts">
/** Interrupteur 48×28 (role="switch") : vert quand il est actif. */
defineProps<{ label: string; disabled?: boolean }>();
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :aria-label="label"
        :disabled="disabled"
        class="relative h-7 w-12 shrink-0 rounded-full transition-colors disabled:opacity-50"
        :class="model ? 'bg-success' : 'bg-ink-medium'"
        @click="model = !model"
    >
        <span class="absolute left-0.5 top-0.5 size-6 rounded-full bg-white shadow-soft transition-transform" :class="{ 'translate-x-5': model }" />
    </button>
</template>
