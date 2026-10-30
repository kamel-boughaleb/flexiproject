<script setup lang="ts">
import type { MiniUser } from '@/types';
import { computed } from 'vue';

type Size = 'sm' | 'md' | 'lg' | 'xl';

const props = withDefaults(defineProps<{ user?: MiniUser | null; size?: Size; src?: string | null }>(), { size: 'sm' });

const SIZES: Record<Size, string> = { sm: 'size-6 text-[10px]', md: 'size-8 text-xs', lg: 'size-10 text-sm', xl: 'size-20 text-xl' };
const FACES = ['#fff7ed', '#fefce8', '#f0fdfa', '#eef2ff', '#fdf2f8', '#f8fafc'];

// Photo (ou aperçu d'une photo en cours d'envoi), sinon avatar dessiné à partir du collaborateur.
const photo = computed(() => props.src ?? props.user?.avatar);

/** Avatar par défaut : un petit visage dont la forme et l'expression dépendent du collaborateur. */
const face = computed(() => {
    const seed = `${props.user?.id ?? ''}${props.user?.name ?? ''}`;
    let h = 0;
    for (const c of seed) h = (Math.imul(h, 31) + c.charCodeAt(0)) >>> 0;
    const pick = (n: number, shift: number) => (h >>> shift) % n;
    return {
        background: props.user?.color ?? '#f97316',
        fill: FACES[pick(FACES.length, 3)],
        radius: 6 + pick(7, 6) * 2,
        rotate: pick(21, 9) - 10,
        x: pick(5, 13) - 2,
        y: pick(5, 16) - 1,
        eyeGap: 4 + pick(3, 19),
        smile: pick(3, 22),
    };
});
</script>

<template>
    <span
        v-if="!user && !src"
        :class="SIZES[size]"
        class="inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-neutral-300 text-neutral-400"
        >?</span
    >
    <img v-else-if="photo" :src="photo" :alt="user?.name" :title="user?.name" :class="SIZES[size]" class="shrink-0 rounded-full object-cover" />
    <svg v-else viewBox="0 0 36 36" :class="SIZES[size]" class="shrink-0 rounded-full" role="img" :aria-label="user?.name">
        <title>{{ user?.name }}</title>
        <rect width="36" height="36" :fill="face.background" />
        <g :transform="`translate(${face.x} ${face.y}) rotate(${face.rotate} 18 18)`">
            <rect x="6" y="7" width="24" height="24" :rx="face.radius" :fill="face.fill" />
            <circle :cx="18 - face.eyeGap" cy="17" r="1.6" fill="#1f2937" />
            <circle :cx="18 + face.eyeGap" cy="17" r="1.6" fill="#1f2937" />
            <path v-if="face.smile === 0" d="M14 22.5 Q18 26.5 22 22.5" stroke="#1f2937" stroke-width="1.5" fill="none" stroke-linecap="round" />
            <path v-else-if="face.smile === 1" d="M14.5 22 Q18 27 21.5 22 Z" fill="#1f2937" />
            <path v-else d="M15 23.5 H21" stroke="#1f2937" stroke-width="1.5" stroke-linecap="round" />
        </g>
    </svg>
</template>
