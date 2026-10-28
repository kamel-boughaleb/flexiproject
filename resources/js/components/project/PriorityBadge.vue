<script setup lang="ts">
import { PRIORITIES } from '@/lib/labels';
import type { Priority } from '@/types';

defineProps<{ priority: Priority }>();
</script>

<template>
    <span v-if="priority !== 'none'" :class="PRIORITIES[priority].class" class="rounded px-1.5 py-0.5 text-[11px] font-medium">{{
        PRIORITIES[priority].label
    }}</span>
</template>
