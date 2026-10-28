<script setup lang="ts">
import BoardView from '@/components/project/BoardView.vue';
import CalendarView from '@/components/project/CalendarView.vue';
import ListView from '@/components/project/ListView.vue';
import TaskPanel from '@/components/project/TaskPanel.vue';
import TimelineView from '@/components/project/TimelineView.vue';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import type { ProjectPageProps, Section, Task, TaskDetail } from '@/types';
import { router } from '@inertiajs/vue3';

defineProps<
    ProjectPageProps & {
        view: 'board' | 'list' | 'calendar' | 'timeline';
        sections: Section[];
        tasks: Task[];
        selectedTask: TaskDetail | null;
    }
>();

const TITLES = { board: 'Kanban', list: 'Liste', calendar: 'Calendrier', timeline: 'Planning' };

/** Ouvre la fiche d'une tâche en ne rechargeant que ses données (l'URL reste partageable). */
function openTask(id: number | null) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('task', String(id));
    else url.searchParams.delete('task');
    router.get(url.pathname + url.search, {}, { only: ['selectedTask'], preserveState: true, preserveScroll: true, replace: !id });
}
</script>

<template>
    <ProjectLayout :project="project" :members="members" :title="TITLES[view]">
        <BoardView v-if="view === 'board'" :project-id="project.id" :sections="sections" :tasks="tasks" :can-edit="can.edit" @open="openTask" />
        <ListView v-else-if="view === 'list'" :project-id="project.id" :sections="sections" :tasks="tasks" :can-edit="can.edit" @open="openTask" />
        <CalendarView v-else-if="view === 'calendar'" :project="project" :tasks="tasks" @open="openTask" />
        <TimelineView v-else :project="project" :sections="sections" :tasks="tasks" :can-edit="can.edit" @open="openTask" />

        <TaskPanel
            v-if="selectedTask"
            :task="selectedTask"
            :members="members"
            :sections="sections"
            :can-edit="can.edit"
            :can-manage="can.manage"
            @close="openTask(null)"
            @open="openTask"
        />
    </ProjectLayout>
</template>
