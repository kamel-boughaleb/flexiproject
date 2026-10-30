<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        :class="
            cn(
                'relative flex min-h-svh flex-1 flex-col bg-background',
                'peer-data-[variant=inset]:min-h-[calc(100svh-20px)] md:peer-data-[variant=inset]:m-2.5 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:ml-2.5 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-card md:peer-data-[variant=inset]:shadow-soft',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
