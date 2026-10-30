<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{ taskId: number; completed: boolean; disabled?: boolean; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });
const emit = defineEmits<{ toggled: [completed: boolean] }>();

// Coche optimiste : la case change tout de suite, le serveur confirme ensuite.
const done = ref(props.completed);
watch(
    () => props.completed,
    (v) => (done.value = v),
);

function toggle() {
    if (props.disabled) return;
    done.value = !done.value;
    emit('toggled', done.value);
    router.post(
        route('tasks.toggle', props.taskId),
        {},
        { preserveScroll: true, preserveState: true, onError: () => (done.value = props.completed) },
    );
}

const SIZES = { sm: 'size-[18px]', md: 'size-5', lg: 'size-[22px]' };
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :aria-pressed="done"
        :aria-label="done ? 'Rouvrir la tâche' : 'Marquer comme terminée'"
        :class="[
            SIZES[size],
            done ? 'border-success bg-success text-white' : 'border-app-muted text-transparent hover:border-success hover:text-success',
        ]"
        class="relative flex shrink-0 items-center justify-center rounded-full border-[1.5px] transition before:absolute before:-inset-2 before:content-[''] disabled:cursor-default"
        @click.stop.prevent="toggle"
    >
        <Check class="size-3" :stroke-width="3" />
    </button>
</template>
