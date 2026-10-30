<script setup lang="ts">
import RichEditor from '@/components/editor/RichEditor.vue';
import type { MiniUser, SharedData, TaskDetail } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserAvatar from './UserAvatar.vue';

/** Compositeur de commentaire (collé en bas du panneau, sous le fil en plein écran). */
const props = defineProps<{ task: TaskDetail; members: MiniUser[] }>();
const me = usePage<SharedData>().props.auth.user;

const comment = useForm({ body: '' });
const editor = ref<InstanceType<typeof RichEditor>>();
function send(html: string) {
    comment.body = html;
    comment.post(route('comments.store', props.task.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            comment.reset();
            editor.value?.clear();
        },
    });
}
</script>

<template>
    <div class="flex gap-2.5">
        <UserAvatar :user="me" size="md" class="mt-1 max-sm:hidden" />
        <div class="min-w-0 flex-1">
            <RichEditor
                ref="editor"
                :key="`comment-${task.id}`"
                :model-value="comment.body"
                :project-id="task.project_id"
                :members="members"
                submittable
                min-height="2.5rem"
                :busy="comment.processing"
                placeholder="Ajoutez un commentaire ou @mentionnez un collègue"
                @submit="send"
            />
            <p v-if="comment.errors.body" class="mt-1 text-xs text-brand-active">{{ comment.errors.body }}</p>
        </div>
    </div>
</template>
