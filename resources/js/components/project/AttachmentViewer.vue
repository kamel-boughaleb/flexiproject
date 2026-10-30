<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { fileSize, languageOf } from '@/lib/files';
import type { Attachment } from '@/types';
import { onKeyStroke } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Download, FileIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** Au-delà, l'aperçu du code est tronqué (le fichier complet reste téléchargeable). */
const TEXT_PREVIEW_BYTES = 1024 * 1024;

const props = defineProps<{ attachments: Attachment[]; index: number }>();
const emit = defineEmits<{ close: []; navigate: [index: number] }>();

const file = computed(() => props.attachments[props.index]);
const src = computed(() => route('attachments.show', file.value.id));

const code = ref<string | null>(null);
const truncated = ref(false);
const failed = ref(false);

/** Charge le fichier texte puis le colore avec highlight.js (chargé uniquement si besoin). */
async function loadText(attachment: Attachment) {
    code.value = null;
    failed.value = false;
    truncated.value = attachment.size > TEXT_PREVIEW_BYTES;
    try {
        const [response, { default: hljs }] = await Promise.all([
            fetch(route('attachments.show', attachment.id), { headers: { Range: `bytes=0-${TEXT_PREVIEW_BYTES - 1}` } }),
            import('highlight.js/lib/common'),
        ]);
        if (!response.ok) throw new Error(String(response.status));
        const text = new TextDecoder().decode(await response.arrayBuffer());
        if (file.value.id !== attachment.id) return;
        const language = languageOf(attachment.extension);
        code.value = hljs.getLanguage(language) ? hljs.highlight(text, { language }).value : hljs.highlightAuto(text).value;
    } catch {
        failed.value = true;
    }
}

watch(file, (f) => f.kind === 'text' && loadText(f), { immediate: true });

const hasPrev = computed(() => props.index > 0);
const hasNext = computed(() => props.index < props.attachments.length - 1);
onKeyStroke('ArrowLeft', () => hasPrev.value && emit('navigate', props.index - 1));
onKeyStroke('ArrowRight', () => hasNext.value && emit('navigate', props.index + 1));
</script>

<template>
    <Dialog :open="true" @update:open="(open) => !open && emit('close')">
        <DialogContent class="flex h-[90vh] max-w-5xl flex-col gap-3 p-4 sm:p-5">
            <header class="flex items-center gap-3 pr-8">
                <div class="min-w-0 flex-1">
                    <DialogTitle class="truncate text-base">{{ file.name }}</DialogTitle>
                    <DialogDescription class="text-xs">
                        {{ fileSize(file.size) }}<template v-if="file.user"> · ajouté par {{ file.user.name }}</template>
                    </DialogDescription>
                </div>
                <span v-if="attachments.length > 1" class="text-xs text-muted-foreground">{{ index + 1 }} / {{ attachments.length }}</span>
                <Button variant="ghost" size="icon" :disabled="!hasPrev" title="Précédent" @click="emit('navigate', index - 1)">
                    <ChevronLeft class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" :disabled="!hasNext" title="Suivant" @click="emit('navigate', index + 1)">
                    <ChevronRight class="size-4" />
                </Button>
                <Button as="a" :href="route('attachments.download', file.id)" variant="outline" size="sm">
                    <Download class="size-4" /> Télécharger
                </Button>
            </header>

            <div class="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted/50">
                <img v-if="file.kind === 'image'" :key="file.id" :src="src" :alt="file.name" class="max-h-full max-w-full object-contain" />
                <video v-else-if="file.kind === 'video'" :key="file.id" :src="src" controls autoplay class="max-h-full max-w-full bg-black" />
                <audio v-else-if="file.kind === 'audio'" :key="file.id" :src="src" controls autoplay class="w-full max-w-lg" />
                <iframe v-else-if="file.kind === 'pdf'" :key="file.id" :src="src" :title="file.name" class="size-full rounded-md bg-white" />
                <div v-else-if="file.kind === 'text'" class="flex size-full flex-col">
                    <p v-if="truncated" class="border-b px-3 py-1.5 text-xs text-muted-foreground">
                        Aperçu limité au premier Mo : téléchargez le fichier pour le voir en entier.
                    </p>
                    <p v-if="failed" class="m-auto text-sm text-muted-foreground">Impossible d'afficher ce fichier.</p>
                    <p v-else-if="code === null" class="m-auto text-sm text-muted-foreground">Chargement…</p>
                    <!-- eslint-disable-next-line vue/no-v-html : sortie échappée par highlight.js -->
                    <pre v-else class="hljs size-full overflow-auto p-4 text-xs leading-relaxed"><code v-html="code" /></pre>
                </div>
                <div v-else class="flex flex-col items-center gap-3 p-8 text-center">
                    <FileIcon class="size-12 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        L'aperçu n'est pas disponible pour ce type de fichier ({{ file.extension || 'inconnu' }}).
                    </p>
                    <Button as="a" :href="route('attachments.download', file.id)"><Download class="size-4" /> Télécharger</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style>
@import 'highlight.js/styles/github-dark.css';
</style>
