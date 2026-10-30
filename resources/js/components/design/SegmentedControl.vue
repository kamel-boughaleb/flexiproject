<script setup lang="ts" generic="T extends string | number">
/** Contrôle segmenté : piste grise, onglet actif blanc (filtres, échelles de temps, colonnes mobiles). */
defineProps<{ options: { value: T; label: string; count?: number }[]; label: string; size?: 'sm' | 'md' }>();
const model = defineModel<T>({ required: true });
</script>

<template>
    <div role="tablist" :aria-label="label" class="inline-flex max-w-full gap-0.5 overflow-x-auto rounded-full bg-app-hover p-1">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            role="tab"
            :aria-selected="model === o.value"
            class="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full font-bold transition"
            :class="[
                size === 'sm' ? 'h-7 px-3 text-xs' : 'h-8 px-4 text-sm',
                model === o.value ? 'bg-background text-ink shadow-soft' : 'text-ink-soft hover:text-ink',
            ]"
            @click="model = o.value"
        >
            {{ o.label }}<span v-if="o.count !== undefined" class="text-xs font-semibold text-ink-soft">{{ o.count }}</span>
        </button>
    </div>
</template>
