<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { FILE_ICONS as ICONS, FILE_TILES as TILES, fileSize } from '@/lib/files';
import { shortDate } from '@/lib/labels';
import type { Attachment, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AttachmentViewer from './AttachmentViewer.vue';

const props = defineProps<{ taskId: number; attachments: Attachment[]; canEdit: boolean; canManage: boolean }>();

const userId = computed(() => usePage<SharedData>().props.auth.user.id);
const canDelete = (a: Attachment) => props.canManage || (props.canEdit && a.user_id === userId.value);

const form = useForm<{ files: File[] }>({ files: [] });
const errors = computed(() => Object.entries(form.errors).map(([, message]) => message));

function upload(files: FileList | File[] | null | undefined) {
    if (!files?.length || !props.canEdit) return;
    form.files = Array.from(files);
    form.post(route('attachments.store', props.taskId), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

const input = ref<HTMLInputElement>();
function onPick(e: Event) {
    upload((e.target as HTMLInputElement).files);
    (e.target as HTMLInputElement).value = '';
}

const dragging = ref(false);
function onDrop(e: DragEvent) {
    dragging.value = false;
    upload(e.dataTransfer?.files);
}

function destroy(a: Attachment) {
    if (confirm(`Supprimer « ${a.name} » ?`)) {
        router.delete(route('attachments.destroy', a.id), { preserveScroll: true, preserveState: true });
    }
}

const viewing = ref<number | null>(null);

/** Ouvre le sélecteur de fichiers (bouton trombone de la barre d'outils de la fiche). */
defineExpose({ pick: () => input.value?.click() });
</script>

<template>
    <section
        aria-labelledby="attachments-title"
        @dragover.prevent="canEdit && (dragging = true)"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
    >
        <h3 id="attachments-title" class="mb-3 flex items-baseline gap-2 text-base font-bold">
            Pièces jointes <span class="text-sm font-semibold text-ink-soft">{{ attachments.length }}</span>
        </h3>
        <input v-if="canEdit" ref="input" type="file" multiple class="hidden" @change="onPick" />

        <div v-if="form.progress" class="mb-3 h-1.5 overflow-hidden rounded-full bg-app-hover">
            <div class="h-full rounded-full bg-success transition-all" :style="{ width: `${form.progress.percentage}%` }" />
        </div>
        <InputError v-for="message in errors" :key="message" :message="message" class="mb-2" />

        <ul class="flex gap-3 max-md:-mx-1 max-md:overflow-x-auto max-md:px-1 max-md:pb-1 md:grid md:grid-cols-[repeat(auto-fill,minmax(160px,1fr))]">
            <li
                v-for="(a, i) in attachments"
                :key="a.id"
                class="group relative w-40 shrink-0 overflow-hidden rounded-2xl border border-app-border bg-background transition-shadow hover:shadow-hover md:w-auto"
            >
                <button class="block w-full text-left" :aria-label="`Ouvrir ${a.name}`" @click="viewing = i">
                    <span class="flex h-[110px] items-center justify-center" :class="TILES[a.kind]">
                        <img v-if="a.kind === 'image'" :src="route('attachments.show', a.id)" alt="" loading="lazy" class="size-full object-cover" />
                        <span v-else-if="a.kind === 'pdf'" class="text-xl font-bold">PDF</span>
                        <component :is="ICONS[a.kind]" v-else class="size-7" />
                    </span>
                    <span class="block px-3 py-2">
                        <span class="block truncate text-[13px] font-bold">{{ a.name }}</span>
                        <span class="block truncate text-xs text-ink-soft">{{ fileSize(a.size) }} · {{ shortDate(a.created_at) }}</span>
                    </span>
                </button>
                <button
                    v-if="canDelete(a)"
                    class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-icon bg-background/90 text-brand-active opacity-0 shadow-soft transition focus-visible:opacity-100 group-hover:opacity-100"
                    :aria-label="`Supprimer ${a.name}`"
                    @click="destroy(a)"
                >
                    <Trash2 class="size-4" />
                </button>
            </li>
            <li v-if="canEdit" class="w-40 shrink-0 md:w-auto">
                <button
                    class="flex h-full min-h-[150px] w-full flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed text-sm font-bold text-ink-soft transition hover:border-app-muted hover:text-ink"
                    :class="dragging ? 'border-brand bg-brand-wash text-brand-active' : 'border-app-border-strong'"
                    :disabled="form.processing"
                    @click="input?.click()"
                >
                    <Plus class="size-5" />
                    {{ form.processing ? 'Envoi…' : 'Glisser un fichier' }}
                </button>
            </li>
        </ul>
        <p v-if="!canEdit && !attachments.length" class="text-sm text-ink-soft">Aucune pièce jointe.</p>

        <AttachmentViewer
            v-if="viewing !== null && attachments[viewing]"
            :attachments="attachments"
            :index="viewing"
            @navigate="(i) => (viewing = i)"
            @close="viewing = null"
        />
    </section>
</template>
