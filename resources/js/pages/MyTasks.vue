<script setup lang="ts">
import PriorityBadge from '@/components/project/PriorityBadge.vue';
import TaskCheck from '@/components/project/TaskCheck.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateRange, isOverdue } from '@/lib/labels';
import type { Task } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ tasks: (Task & { project: { id: number; name: string; color: string }; section: { name: string } })[] }>();
</script>

<template>
    <Head title="Mes tâches" />
    <AppLayout :breadcrumbs="[{ title: 'Mes tâches', href: route('my-tasks') }]">
        <div class="p-6">
            <h1 class="text-2xl font-semibold">Mes tâches</h1>
            <p class="text-sm text-muted-foreground">{{ tasks.length }} tâche(s) ouverte(s), triées par échéance</p>
            <ul class="mt-6 divide-y rounded-lg border">
                <li v-for="t in tasks" :key="t.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                    <TaskCheck :task-id="t.id" :completed="t.completed" />
                    <Link :href="`${route('projects.view', [t.project.id, 'list'])}?task=${t.id}`" class="flex-1 hover:underline">{{ t.title }}</Link>
                    <PriorityBadge :priority="t.priority" />
                    <span class="hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
                        <span class="size-2 rounded-sm" :style="{ backgroundColor: t.project.color }" /> {{ t.project.name }} · {{ t.section.name }}
                    </span>
                    <span
                        class="w-28 text-right text-xs"
                        :class="isOverdue(t.due_date, t.completed) ? 'font-medium text-rose-600' : 'text-muted-foreground'"
                    >
                        {{ dateRange(t.start_date, t.due_date) }}
                    </span>
                </li>
                <li v-if="!tasks.length" class="px-4 py-8 text-center text-sm text-muted-foreground">Rien à faire pour le moment 🎉</li>
            </ul>
        </div>
    </AppLayout>
</template>
