<script setup lang="ts">
import { FILE_ICONS, FILE_TILES } from '@/lib/files';
import { dueLabel, isOverdue } from '@/lib/labels';
import type { Task } from '@/types';
import { ListChecks, MessageCircle, Paperclip } from 'lucide-vue-next';
import PriorityBadge from './PriorityBadge.vue';
import TaskCheck from './TaskCheck.vue';
import UserAvatar from './UserAvatar.vue';

defineProps<{ task: Task; canEdit: boolean; selected?: boolean }>();
defineEmits<{ open: [id: number] }>();
</script>

<template>
    <div
        role="button"
        tabindex="0"
        :aria-current="selected ? 'true' : undefined"
        class="shrink-0 cursor-pointer overflow-hidden rounded-task border-2 bg-card px-3.5 py-3 shadow-soft transition-shadow hover:shadow-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :class="selected ? 'border-brand' : 'border-transparent'"
        @click="$emit('open', task.id)"
        @keydown.enter.self="$emit('open', task.id)"
    >
        <div v-if="task.cover" class="-mx-3.5 -mt-3 mb-3 border-b border-app-border">
            <img
                v-if="task.cover.kind === 'image'"
                :src="route('attachments.thumbnail', task.cover.id)"
                :alt="task.cover.name"
                loading="lazy"
                decoding="async"
                draggable="false"
                class="block max-h-44 w-full bg-app-hover object-cover"
            />
            <div
                v-else
                class="flex h-24 flex-col items-center justify-center gap-1.5 px-4"
                :class="FILE_TILES[task.cover.kind]"
                :title="task.cover.name"
            >
                <span v-if="task.cover.kind === 'pdf'" class="text-xl font-bold leading-none">PDF</span>
                <span v-else class="flex items-center gap-1.5">
                    <component :is="FILE_ICONS[task.cover.kind]" class="size-6" />
                    <span v-if="task.cover.extension" class="text-sm font-bold uppercase">{{ task.cover.extension }}</span>
                </span>
                <span class="max-w-full truncate text-xs font-medium opacity-80">{{ task.cover.name }}</span>
            </div>
        </div>
        <div class="flex items-start gap-2.5">
            <TaskCheck class="mt-px" :task-id="task.id" :completed="task.completed" :disabled="!canEdit" />
            <span class="flex-1 text-sm font-semibold leading-5" :class="{ 'text-ink-soft line-through': task.completed }">{{ task.title }}</span>
        </div>
        <div class="mt-2.5 flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs font-medium text-ink-soft">
            <PriorityBadge :priority="task.priority" />
            <span v-if="task.due_date" :class="{ 'font-bold text-brand-active': isOverdue(task.due_date, task.completed) }">
                {{ dueLabel(task.due_date) }}
            </span>
            <span v-if="task.comments_count" class="flex items-center gap-1" :title="`${task.comments_count} commentaire(s)`">
                <MessageCircle class="size-3.5" />{{ task.comments_count }}
            </span>
            <span v-if="task.subtasks_count" class="flex items-center gap-1" title="Sous-tâches terminées">
                <ListChecks class="size-3.5" />{{ task.subtasks_done_count ?? 0 }}/{{ task.subtasks_count }}
            </span>
            <span v-if="task.attachments_count" class="flex items-center gap-1" :title="`${task.attachments_count} pièce(s) jointe(s)`">
                <Paperclip class="size-3.5" />{{ task.attachments_count }}
            </span>
            <UserAvatar v-if="task.assignee" class="ml-auto" :user="task.assignee" size="xs" />
        </div>
    </div>
</template>
