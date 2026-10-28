<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

const props = defineProps<{ taskId: number; completed: boolean; disabled?: boolean }>();

function toggle() {
    if (props.disabled) return;
    router.post(route('tasks.toggle', props.taskId), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :aria-label="completed ? 'Rouvrir la tâche' : 'Marquer comme terminée'"
        @click.stop.prevent="toggle"
        :class="
            completed
                ? 'border-emerald-500 bg-emerald-500 text-white'
                : 'border-neutral-400 text-transparent hover:border-emerald-500 hover:text-emerald-500'
        "
        class="flex size-4 shrink-0 items-center justify-center rounded-full border transition"
    >
        <Check class="size-3" :stroke-width="3" />
    </button>
</template>
