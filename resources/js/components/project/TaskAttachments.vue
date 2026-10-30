<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { fileSize } from '@/lib/files';
import { dateTime } from '@/lib/labels';
import type { Attachment, AttachmentKind, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { FileCode, FileIcon, FileText, Film, Image, Music, Paperclip, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AttachmentViewer from './AttachmentViewer.vue';

const props = defineProps<{ taskId: number; attachments: Attachment[]; canEdit: boolean; canManage: boolean }>();

const ICONS: Record<AttachmentKind, typeof FileIcon> = {
    image: Image,
    video: Film,
    audio: Music,
    pdf: FileText,
    text: FileCode,
    other: FileIcon,
};

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
</script>

<template>
    <section
        class="rounded-md"
        :class="{ 'ring-2 ring-primary ring-offset-2': dragging }"
        @dragover.prevent="canEdit && (dragging = true)"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
    >
        <div class="mb-2 flex items-center">
            <h4 class="text-sm font-semibold">Pièces jointes</h4>
            <template v-if="canEdit">
                <Button variant="ghost" size="sm" class="ml-auto" :disabled="form.processing" @click="input?.click()">
                    <Paperclip class="size-4" /> Joindre des fichiers
                </Button>
                <input ref="input" type="file" multiple class="hidden" @change="onPick" />
            </template>
        </div>

        <div v-if="form.progress" class="mb-2 h-1.5 overflow-hidden rounded-full bg-muted">
            <div class="h-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
        </div>
        <InputError v-for="message in errors" :key="message" :message="message" class="mb-2" />

        <ul v-if="attachments.length" class="divide-y rounded-md border">
            <li v-for="(a, i) in attachments" :key="a.id" class="group flex items-center gap-3 px-3 py-2 text-sm">
                <component :is="ICONS[a.kind]" class="size-4 shrink-0 text-muted-foreground" />
                <button class="min-w-0 flex-1 text-left" @click="viewing = i">
                    <span class="block truncate hover:underline">{{ a.name }}</span>
                    <span class="block text-xs text-muted-foreground">
                        {{ fileSize(a.size) }} · <template v-if="a.user">{{ a.user.name }}, </template>{{ dateTime(a.created_at) }}
                    </span>
                </button>
                <Button
                    v-if="canDelete(a)"
                    variant="ghost"
                    size="icon"
                    class="size-7 text-rose-600 opacity-0 group-hover:opacity-100"
                    title="Supprimer"
                    @click="destroy(a)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </li>
        </ul>
        <p
            v-else-if="canEdit"
            class="cursor-pointer rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground"
            @click="input?.click()"
        >
            Glissez des fichiers ici ou cliquez pour en joindre (vidéos, images, PDF, présentations, code…).
        </p>
        <p v-else class="text-xs text-muted-foreground">Aucune pièce jointe.</p>

        <AttachmentViewer
            v-if="viewing !== null && attachments[viewing]"
            :attachments="attachments"
            :index="viewing"
            @navigate="(i) => (viewing = i)"
            @close="viewing = null"
        />
    </section>
</template>
