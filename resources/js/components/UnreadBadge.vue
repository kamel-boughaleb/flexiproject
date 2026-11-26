<script setup lang="ts">
import { ref, watch } from 'vue';

/**
 * Bulle « +N » à côté du nom d'une tâche ou d'un projet : nouveaux commentaires et modifications
 * des collègues pas encore vus. Elle reste affichée jusqu'à l'ouverture de la tâche et rebondit
 * quand le nombre augmente.
 */
const props = defineProps<{ count?: number | null }>();

const bump = ref(0);
watch(
    () => props.count ?? 0,
    (count, previous) => {
        if (count > previous) bump.value++;
    },
);
</script>

<template>
    <span
        v-if="count"
        :key="bump"
        class="unread-badge inline-flex h-[18px] min-w-[22px] shrink-0 items-center justify-center rounded-full bg-brand px-1.5 text-[11px] font-bold leading-none text-white"
        :title="$tc(':count nouveauté non vue|:count nouveautés non vues', count)"
        >+{{ count }}</span
    >
</template>
