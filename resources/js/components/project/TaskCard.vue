<script setup lang="ts">
import { dateRange, isOverdue } from '@/lib/labels';
import type { Task } from '@/types';
import { ListTree, MessageSquare } from 'lucide-vue-next';
import PriorityBadge from './PriorityBadge.vue';
import TaskCheck from './TaskCheck.vue';
import UserAvatar from './UserAvatar.vue';

defineProps<{ task: Task; canEdit: boolean }>();
defineEmits<{ open: [id: number] }>();
</script>

<template>
    <div
        class="cursor-pointer rounded-lg border bg-card p-3 shadow-sm transition hover:border-neutral-300 hover:shadow dark:hover:border-neutral-600"
        @click="$emit('open', task.id)"
    >
        <div class="flex items-start gap-2">
            <TaskCheck class="mt-0.5" :task-id="task.id" :completed="task.completed" :disabled="!canEdit" />
            <span class="flex-1 text-sm" :class="{ 'text-muted-foreground line-through': task.completed }">{{ task.title }}</span>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2 pl-6 text-xs text-muted-foreground">
            <PriorityBadge :priority="task.priority" />
            <span v-if="task.start_date || task.due_date" :class="{ 'font-medium text-rose-600': isOverdue(task.due_date, task.completed) }">
                {{ dateRange(task.start_date, task.due_date) }}
            </span>
            <span v-if="task.subtasks_count" class="flex items-center gap-0.5"><ListTree class="size-3" />{{ task.subtasks_count }}</span>
            <span v-if="task.comments_count" class="flex items-center gap-0.5"><MessageSquare class="size-3" />{{ task.comments_count }}</span>
            <UserAvatar class="ml-auto" :user="task.assignee" />
        </div>
    </div>
</template>
