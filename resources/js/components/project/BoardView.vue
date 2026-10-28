<script setup lang="ts">
import type { Section, Task } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';

const props = defineProps<{ projectId: number; sections: Section[]; tasks: Task[]; canEdit: boolean }>();
const emit = defineEmits<{ open: [id: number] }>();

// Copie locale des colonnes pour un glisser-déposer fluide ; resynchronisée à chaque réponse du serveur.
const columns = ref<Record<number, Task[]>>({});
watch(
    () => [props.sections, props.tasks],
    () => {
        columns.value = Object.fromEntries(props.sections.map((s) => [s.id, props.tasks.filter((t) => t.section_id === s.id)]));
    },
    { immediate: true },
);

type ChangeEvent = { added?: { element: Task; newIndex: number }; moved?: { element: Task; newIndex: number } };

function onChange(event: ChangeEvent, sectionId: number) {
    const change = event.added ?? event.moved;
    if (!change) return;
    router.post(
        route('tasks.move', change.element.id),
        { section_id: sectionId, index: change.newIndex },
        { preserveScroll: true, preserveState: true },
    );
}

// Ajout rapide d'une tâche en bas de colonne
const addingIn = ref<number | null>(null);
const newTask = useForm({ title: '', section_id: 0 });
const vFocus = { mounted: (el: HTMLElement) => el.focus() };
function startAdding(sectionId: number) {
    addingIn.value = sectionId;
}
function addTask(sectionId: number) {
    if (!newTask.title.trim()) return (addingIn.value = null);
    newTask.section_id = sectionId;
    newTask.post(route('tasks.store', props.projectId), { preserveScroll: true, preserveState: true, onSuccess: () => newTask.reset('title') });
}

const newSection = useForm({ name: '' });
function addSection() {
    newSection.post(route('sections.store', props.projectId), { preserveScroll: true, onSuccess: () => newSection.reset() });
}

const renaming = ref<number | null>(null);
function renameSection(section: Section, name: string) {
    renaming.value = null;
    if (name.trim() && name !== section.name) router.patch(route('sections.update', section.id), { name }, { preserveScroll: true });
}
function deleteSection(section: Section) {
    if (confirm(`Supprimer la section « ${section.name} » et toutes ses tâches ?`))
        router.delete(route('sections.destroy', section.id), { preserveScroll: true });
}
</script>

<template>
    <div class="flex h-full gap-4 overflow-x-auto p-6">
        <div v-for="section in sections" :key="section.id" class="flex w-72 shrink-0 flex-col rounded-lg bg-muted/60" data-test="column">
            <div class="group flex items-center gap-2 px-3 pb-2 pt-3">
                <input
                    v-if="renaming === section.id"
                    :value="section.name"
                    class="flex-1 rounded border bg-background px-2 py-0.5 text-sm"
                    v-focus
                    @keydown.enter="renameSection(section, ($event.target as HTMLInputElement).value)"
                    @blur="renameSection(section, ($event.target as HTMLInputElement).value)"
                />
                <h3
                    v-else
                    class="flex-1 text-sm font-semibold"
                    :title="canEdit ? 'Double-cliquer pour renommer' : ''"
                    @dblclick="canEdit && (renaming = section.id)"
                >
                    {{ section.name }}
                    <span class="ml-1 font-normal text-muted-foreground">{{ columns[section.id]?.length ?? 0 }}</span>
                </h3>
                <button
                    v-if="canEdit && sections.length > 1"
                    class="text-muted-foreground opacity-0 hover:text-rose-600 group-hover:opacity-100"
                    title="Supprimer la section"
                    @click="deleteSection(section)"
                >
                    <X class="size-4" />
                </button>
            </div>

            <draggable
                v-model="columns[section.id]"
                group="tasks"
                item-key="id"
                :disabled="!canEdit"
                :animation="150"
                ghost-class="opacity-40"
                class="flex min-h-16 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2"
                @change="onChange($event, section.id)"
            >
                <template #item="{ element }">
                    <TaskCard :task="element" :can-edit="canEdit" @open="emit('open', $event)" />
                </template>
            </draggable>

            <template v-if="canEdit">
                <form v-if="addingIn === section.id" class="m-2 mt-0" @submit.prevent="addTask(section.id)">
                    <input
                        v-focus
                        v-model="newTask.title"
                        placeholder="Nom de la tâche, puis Entrée"
                        class="w-full rounded-md border bg-background px-3 py-1.5 text-sm"
                        @keydown.esc="addingIn = null"
                        @blur="!newTask.title && (addingIn = null)"
                    />
                </form>
                <button
                    v-else
                    class="m-2 mt-0 flex items-center gap-1 rounded px-2 py-1.5 text-left text-sm text-muted-foreground hover:bg-muted"
                    @click="startAdding(section.id)"
                >
                    <Plus class="size-4" /> Ajouter une tâche
                </button>
            </template>
        </div>

        <form v-if="canEdit" class="w-72 shrink-0" @submit.prevent="addSection">
            <input
                v-model="newSection.name"
                placeholder="+ Ajouter une section"
                class="w-full rounded-md border border-dashed bg-transparent px-3 py-2 text-sm"
            />
        </form>
    </div>
</template>
