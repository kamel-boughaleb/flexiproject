<script setup lang="ts">
import type { ProjectBadge } from '@/types';
import { computed } from 'vue';

type Size = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

/** Avatar d'un projet : image envoyée, sinon emoji, sinon carré de la couleur du projet avec son initiale. */
const props = withDefaults(defineProps<{ project: Pick<ProjectBadge, 'name' | 'color' | 'icon' | 'image'>; size?: Size; src?: string | null }>(), {
    size: 'sm',
});

const SIZES: Record<Size, string> = {
    xs: 'size-5 rounded-[5px] text-[10px]',
    sm: 'size-6 rounded-md text-xs',
    md: 'size-8 rounded-lg text-sm',
    lg: 'size-11 rounded-xl text-xl',
    xl: 'size-20 rounded-2xl text-4xl',
};
const image = computed(() => props.src ?? props.project.image);
const initial = computed(() => props.project.name.trim().charAt(0).toUpperCase() || '?');
</script>

<template>
    <img v-if="image" :src="image" :alt="project.name" :class="SIZES[size]" class="shrink-0 object-cover" />
    <span
        v-else
        :class="SIZES[size]"
        class="inline-flex shrink-0 select-none items-center justify-center font-bold leading-none text-white"
        :style="{ backgroundColor: project.icon ? `${project.color}26` : project.color }"
        aria-hidden="true"
    >
        {{ project.icon || initial }}
    </span>
</template>
