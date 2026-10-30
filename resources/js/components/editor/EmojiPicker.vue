<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import { ref } from 'vue';
import { EMOJI_GROUPS, emojiList } from './emojis';

const emit = defineEmits<{ pick: [emoji: string] }>();

const RECENT_KEY = 'flexi-recent-emojis';
const open = ref(false);
const tab = ref(0);

function readRecent(): string[] {
    try {
        return JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
    } catch {
        return [];
    }
}
const recent = ref<string[]>(readRecent());

function pick(emoji: string) {
    recent.value = [emoji, ...recent.value.filter((e) => e !== emoji)].slice(0, 16);
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(recent.value));
    } catch {
        // stockage indisponible (navigation privée) : on garde juste la liste en mémoire
    }
    open.value = false;
    emit('pick', emoji);
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <slot />
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="top"
                align="start"
                :side-offset="6"
                class="z-[60] w-80 rounded-md border bg-popover p-2 text-popover-foreground shadow-lg"
                @open-auto-focus.prevent
                @close-auto-focus.prevent
            >
                <div class="mb-2 flex gap-1 border-b pb-2">
                    <button
                        v-for="(g, i) in EMOJI_GROUPS"
                        :key="g.name"
                        type="button"
                        :title="g.name"
                        class="rounded px-1.5 py-0.5 text-lg"
                        :class="tab === i ? 'bg-accent' : 'opacity-60 hover:opacity-100'"
                        @click="tab = i"
                    >
                        {{ g.icon }}
                    </button>
                </div>
                <template v-if="recent.length">
                    <p class="px-1 text-xs text-muted-foreground">Récents</p>
                    <div class="mb-1 grid grid-cols-8">
                        <button v-for="e in recent" :key="`r${e}`" type="button" class="rounded p-1 text-xl hover:bg-accent" @click="pick(e)">
                            {{ e }}
                        </button>
                    </div>
                </template>
                <p class="px-1 text-xs text-muted-foreground">{{ EMOJI_GROUPS[tab].name }}</p>
                <div class="grid max-h-52 grid-cols-8 overflow-y-auto">
                    <button
                        v-for="e in emojiList(EMOJI_GROUPS[tab])"
                        :key="e"
                        type="button"
                        class="rounded p-1 text-xl hover:bg-accent"
                        @click="pick(e)"
                    >
                        {{ e }}
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
