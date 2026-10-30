<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import type { MiniUser } from '@/types';
import { ref, watch } from 'vue';
import type { SlashItem } from './suggestion';

type Item = MiniUser | SlashItem;

const props = defineProps<{ items: Item[]; command: (item: Item | { id: string; label: string }) => void }>();

const selected = ref(0);
watch(
    () => props.items,
    () => (selected.value = 0),
);

const isMember = (item: Item): item is MiniUser => 'id' in item;

function choose(index: number) {
    const item = props.items[index];
    if (!item) return;
    // Une mention enregistre l'identifiant du collaborateur (pour le prévenir) et son nom affiché.
    props.command(isMember(item) ? { id: String(item.id), label: item.name } : item);
}

function onKeyDown(event: KeyboardEvent): boolean {
    if (!props.items.length) return false;
    if (event.key === 'ArrowDown') {
        selected.value = (selected.value + 1) % props.items.length;
        return true;
    }
    if (event.key === 'ArrowUp') {
        selected.value = (selected.value + props.items.length - 1) % props.items.length;
        return true;
    }
    if (event.key === 'Enter' || event.key === 'Tab') {
        choose(selected.value);
        return true;
    }
    return false;
}

defineExpose({ onKeyDown });
</script>

<template>
    <div class="max-h-72 w-64 overflow-y-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-lg">
        <p v-if="!items.length" class="px-2 py-1.5 text-muted-foreground">Aucun résultat</p>
        <button
            v-for="(item, i) in items"
            :key="isMember(item) ? `u${item.id}` : item.title"
            type="button"
            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left"
            :class="i === selected ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/60'"
            @mousedown.prevent="choose(i)"
            @mouseenter="selected = i"
        >
            <template v-if="isMember(item)">
                <UserAvatar :user="item" />
                <span class="truncate">{{ item.name }}</span>
            </template>
            <template v-else>
                <component :is="item.icon" class="size-4 text-muted-foreground" />
                <span class="truncate">{{ item.title }}</span>
            </template>
        </button>
    </div>
</template>
