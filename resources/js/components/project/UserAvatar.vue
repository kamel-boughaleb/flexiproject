<script setup lang="ts">
import { useInitials } from '@/composables/useInitials';
import type { MiniUser } from '@/types';

withDefaults(defineProps<{ user?: MiniUser | null; size?: 'sm' | 'md' }>(), { size: 'sm' });
const { getInitials } = useInitials();
</script>

<template>
    <span
        v-if="!user"
        :class="size === 'sm' ? 'size-6 text-[10px]' : 'size-8 text-xs'"
        class="inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-neutral-300 text-neutral-400"
        >?</span
    >
    <img
        v-else-if="user.avatar"
        :src="user.avatar"
        :alt="user.name"
        :title="user.name"
        :class="size === 'sm' ? 'size-6' : 'size-8'"
        class="shrink-0 rounded-full object-cover"
    />
    <span
        v-else
        :title="user.name"
        :style="{ backgroundColor: user.color }"
        :class="size === 'sm' ? 'size-6 text-[10px]' : 'size-8 text-xs'"
        class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white"
        >{{ getInitials(user.name) }}</span
    >
</template>
