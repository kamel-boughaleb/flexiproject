<script setup lang="ts">
import { PRIORITIES } from '@/lib/labels';
import type { Priority } from '@/types';

/** Trois pilules radio Basse / Moyenne / Haute ; un second clic sur l'option active retire la priorité. */
defineProps<{ disabled?: boolean; label?: string }>();
const model = defineModel<Priority>({ required: true });
const OPTIONS = ['low', 'medium', 'high'] as const;
</script>

<template>
    <div role="radiogroup" :aria-label="label ?? 'Priorité'" class="flex flex-wrap gap-1.5">
        <button
            v-for="p in OPTIONS"
            :key="p"
            type="button"
            role="radio"
            :aria-checked="model === p"
            :disabled="disabled"
            class="h-7 rounded-full border-2 px-3 text-xs font-bold transition disabled:cursor-default"
            :class="model === p ? [PRIORITIES[p].class, PRIORITIES[p].border] : 'border-app-border bg-background text-ink-soft hover:border-app-border-strong'"
            @click="model = model === p ? 'none' : p"
        >
            {{ PRIORITIES[p].label }}
        </button>
    </div>
</template>
