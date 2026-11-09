<script setup lang="ts">
import RichEditor from '@/components/editor/RichEditor.vue';
import InputError from '@/components/InputError.vue';
import TaskAttachments from '@/components/project/TaskAttachments.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fileSize } from '@/lib/files';
import type { FeedPost } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { Paperclip, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Rédaction d'une actualité : titre, date, texte riche (images collées ou glissées, liens…)
 * et pièces jointes. En modification, les pièces jointes sont envoyées ou retirées tout de suite.
 */
const props = defineProps<{ feedId: number; post?: FeedPost | null }>();
const open = defineModel<boolean>('open', { required: true });

const today = () => format(new Date(), 'yyyy-MM-dd');
const form = useForm<{ title: string; published_on: string; body: string; files: File[] }>({ title: '', published_on: today(), body: '', files: [] });
const editorKey = ref(0);
watch(
    open,
    (value) => {
        if (!value) return;
        form.clearErrors();
        form.title = props.post?.title ?? '';
        form.published_on = props.post?.published_on?.slice(0, 10) ?? today();
        form.body = props.post?.body ?? '';
        form.files = [];
        editorKey.value++;
    },
    { immediate: true },
);

const fileInput = ref<HTMLInputElement>();
function addFiles(e: Event) {
    const input = e.target as HTMLInputElement;
    form.files = [...form.files, ...Array.from(input.files ?? [])];
    input.value = '';
}
const removeFile = (i: number) => (form.files = form.files.filter((_, j) => j !== i));
const fileErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message),
);

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (props.post) {
        form.transform(({ title, published_on, body }) => ({ title, published_on, body })).patch(route('posts.update', props.post.id), options);
    } else {
        form.transform((data) => data).post(route('posts.store', props.feedId), { ...options, forceFormData: true });
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ post ? $t("Modifier l'actualité") : $t('Nouvelle actualité') }}</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <div class="grid gap-2">
                        <Label for="post-title">{{ $t('Titre') }}</Label>
                        <Input id="post-title" v-model="form.title" required :placeholder="$t('Ex. Lancement du nouveau catalogue')" />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-date">{{ $t('Date') }}</Label>
                        <Input id="post-date" v-model="form.published_on" type="date" required />
                        <InputError :message="form.errors.published_on" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('Texte') }}</Label>
                    <RichEditor
                        :key="editorKey"
                        v-model="form.body"
                        :upload-url="route('feed-images.store', feedId)"
                        min-height="10rem"
                        :placeholder="$t('Écrivez votre actualité… Collez ou glissez des images, ajoutez des liens. « / » affiche le menu.')"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <TaskAttachments
                    v-if="post"
                    :upload-url="route('posts.attachments.store', post.id)"
                    :attachments="post.attachments"
                    :can-edit="post.can_manage"
                    :can-manage="post.can_manage"
                />
                <div v-else class="space-y-2">
                    <input ref="fileInput" type="file" multiple class="hidden" @change="addFiles" />
                    <ul v-if="form.files.length" class="space-y-1">
                        <li
                            v-for="(f, i) in form.files"
                            :key="`${f.name}-${i}`"
                            class="flex items-center gap-2 rounded-lg bg-app-hover px-3 py-1.5 text-sm"
                        >
                            <Paperclip class="size-4 shrink-0 text-ink-soft" />
                            <span class="min-w-0 flex-1 truncate font-semibold">{{ f.name }}</span>
                            <span class="text-xs text-ink-soft">{{ fileSize(f.size) }}</span>
                            <button
                                type="button"
                                class="text-ink-soft hover:text-ink"
                                :aria-label="$t('Retirer :name', { name: f.name })"
                                @click="removeFile(i)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                    </ul>
                    <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                        <Paperclip class="size-4" /> {{ $t('Joindre des fichiers') }}
                    </Button>
                    <InputError v-for="message in fileErrors" :key="message" :message="message" />
                </div>

                <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-app-hover">
                    <div class="h-full rounded-full bg-success transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                </div>
                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">{{ post ? $t('Enregistrer') : $t('Publier') }}</Button>
                    <span v-if="!post" class="text-xs text-muted-foreground">{{ $t('Les abonnés du fil sont prévenus par e-mail.') }}</span>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
