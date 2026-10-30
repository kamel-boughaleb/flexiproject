<script setup lang="ts">
import BoardView from '@/components/project/BoardView.vue';
import CalendarView from '@/components/project/CalendarView.vue';
import ListView from '@/components/project/ListView.vue';
import QuickAddSheet from '@/components/project/QuickAddSheet.vue';
import TaskPanel from '@/components/project/TaskPanel.vue';
import TimelineView from '@/components/project/TimelineView.vue';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import type { ProjectPageProps, Section, Task, TaskDetail } from '@/types';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<
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
    url.searchParams.delete('full');
    router.get(url.pathname + url.search, {}, { only: ['selectedTask'], preserveState: true, preserveScroll: true, replace: !id });
}

// Plein écran de la fiche : mémorisé dans l'URL pour que le lien copié rouvre la même vue.
const full = ref(new URL(window.location.href).searchParams.has('full'));
watch(full, (value) => {
    const url = new URL(window.location.href);
    if (value) url.searchParams.set('full', '1');
    else url.searchParams.delete('full');
    window.history.replaceState(window.history.state, '', url);
});
watch(
    () => props.selectedTask?.id,
    (id) => !id && (full.value = false),
);

const quickAdd = ref(false);
</script>

<template>
    <ProjectLayout :project="project" :members="members" :title="TITLES[view]" :can-edit="can.edit">
        <BoardView
            v-if="view === 'board'"
            :project-id="project.id"
            :sections="sections"
            :tasks="tasks"
            :can-edit="can.edit"
            :selected-id="selectedTask?.id"
            @open="openTask"
        />
        <ListView
            v-else-if="view === 'list'"
            :project-id="project.id"
            :sections="sections"
            :tasks="tasks"
            :can-edit="can.edit"
            :selected-id="selectedTask?.id"
            @open="openTask"
        />
        <CalendarView v-else-if="view === 'calendar'" :project="project" :tasks="tasks" @open="openTask" />
        <TimelineView v-else :project="project" :sections="sections" :tasks="tasks" :can-edit="can.edit" @open="openTask" />

        <!-- Mobile : bouton flottant d'ajout et feuille de création -->
        <button
            v-if="can.edit && !selectedTask"
            type="button"
            aria-label="Ajouter une tâche"
            class="fixed bottom-[84px] right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-active text-white shadow-product md:hidden"
            @click="quickAdd = true"
        >
            <Plus class="size-6" />
        </button>
        <QuickAddSheet v-if="can.edit" v-model:open="quickAdd" :project-id="project.id" :sections="sections" :members="members" />

        <template #aside>
            <TaskPanel
                v-if="selectedTask"
                v-model:full="full"
                :task="selectedTask"
                :project="project"
                :members="members"
                :sections="sections"
                :can-edit="can.edit"
                :can-manage="can.manage"
                @close="openTask(null)"
                @open="openTask"
            />
        </template>
    </ProjectLayout>
</template>
