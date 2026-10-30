<script setup lang="ts">
import { dueLabel } from '@/lib/labels';
import { ref } from 'vue';

/** Date cliquable : affiche « 4 nov. » / « Hier » et ouvre le sélecteur natif au clic. */
const props = defineProps<{ label: string; placeholder: string; disabled?: boolean; danger?: boolean }>();
const model = defineModel<string | null>({ required: true });
const input = ref<HTMLInputElement>();

function open() {
    if (props.disabled || !input.value) return;
    try {
        input.value.showPicker();
    } catch {
        input.value.focus();
    }
}
</script>

<template>
    <span class="relative inline-flex">
        <button
            type="button"
            :disabled="disabled"
            :aria-label="`${label} : ${model ? dueLabel(model) : 'aucune'}`"
            class="rounded-full px-1.5 py-0.5 text-sm font-bold transition enabled:hover:bg-app-hover disabled:cursor-default"
            :class="model ? (danger ? 'text-brand-active' : 'text-ink') : 'font-semibold text-ink-soft'"
            @click="open"
        >
            {{ model ? dueLabel(model) : placeholder }}
        </button>
        <input
            ref="input"
            type="date"
            tabindex="-1"
            aria-hidden="true"
            :value="model ?? ''"
            class="pointer-events-none absolute bottom-0 left-0 h-0 w-full opacity-0"
            @change="model = ($event.target as HTMLInputElement).value || null"
        />
    </span>
</template>
