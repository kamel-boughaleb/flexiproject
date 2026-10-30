<script setup lang="ts">
/**
 * Valeur cliquable qui s'édite en place : le contenu affiché reste celui du slot, et un vrai <select>
 * transparent posé dessus ouvre la liste native (clavier et lecteurs d'écran compris).
 */
defineProps<{ label: string; options: { value: string | number; label: string }[]; disabled?: boolean }>();
const model = defineModel<string | number>({ required: true });
</script>

<template>
    <span class="relative inline-flex max-w-full rounded-full transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring" :class="{ 'hover:bg-app-hover': !disabled }">
        <slot />
        <select
            v-model="model"
            :aria-label="label"
            :disabled="disabled"
            class="absolute inset-0 size-full cursor-pointer appearance-none opacity-0 disabled:cursor-default"
        >
            <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
    </span>
</template>
