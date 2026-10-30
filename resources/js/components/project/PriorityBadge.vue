<script setup lang="ts">
import { PRIORITIES } from '@/lib/labels';
import type { Priority } from '@/types';

defineProps<{ priority: Priority }>();
</script>

<template>
    <span v-if="priority !== 'none'" :class="PRIORITIES[priority].class" class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold leading-4">{{
        PRIORITIES[priority].label
    }}</span>
</template>
