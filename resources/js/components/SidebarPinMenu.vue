<script setup lang="ts">
import { Pin, PinOff } from 'lucide-vue-next';
import { ContextMenuContent, ContextMenuItem, ContextMenuPortal, ContextMenuRoot, ContextMenuTrigger } from 'radix-vue';

/** Clic droit sur un projet ou un fil du menu de gauche : Épingler / Désépingler. */
defineProps<{ pinned?: boolean }>();
const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <ContextMenuRoot>
        <ContextMenuTrigger as="div">
            <slot />
        </ContextMenuTrigger>
        <ContextMenuPortal>
            <ContextMenuContent
                class="z-50 w-48 overflow-hidden rounded-2xl border bg-popover p-1.5 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <ContextMenuItem
                    class="relative flex cursor-default select-none items-center gap-2 rounded-icon px-2 py-1.5 text-sm font-semibold outline-none transition-colors focus:bg-accent focus:text-accent-foreground [&>svg]:size-4 [&>svg]:shrink-0"
                    @select="emit('toggle')"
                >
                    <component :is="pinned ? PinOff : Pin" />
                    {{ pinned ? $t('Désépingler') : $t('Épingler') }}
                </ContextMenuItem>
            </ContextMenuContent>
        </ContextMenuPortal>
    </ContextMenuRoot>
</template>
