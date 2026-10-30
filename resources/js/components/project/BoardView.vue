<script setup lang="ts">
import SegmentedControl from '@/components/design/SegmentedControl.vue';
import type { Section, Task } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';

const props = defineProps<{ projectId: number; sections: Section[]; tasks: Task[]; canEdit: boolean; selectedId?: number | null }>();
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

// Sur mobile, une seule colonne est affichée à la fois.
const mobileColumn = ref(props.sections[0]?.id ?? 0);

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

// Bouton « Ajouter une tâche » de l'en-tête du projet : saisie rapide dans la première colonne.
const board = ref<HTMLElement>();
async function addFromHeader() {
    if (!props.canEdit || !props.sections.length) return;
    board.value?.scrollTo({ left: 0, behavior: 'smooth' });
    addingIn.value = null;
    await nextTick();
    startAdding(props.sections[0].id);
}
onMounted(() => {
    window.addEventListener('flexi:add-task', addFromHeader);
    const url = new URL(window.location.href);
    if (url.searchParams.has('add')) {
        url.searchParams.delete('add');
        window.history.replaceState(window.history.state, '', url);
        addFromHeader();
    }
});
onBeforeUnmount(() => window.removeEventListener('flexi:add-task', addFromHeader));

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
    <div class="flex h-full flex-col">
        <div class="px-4 pt-3 md:hidden">
            <SegmentedControl
                v-model="mobileColumn"
                label="Colonne affichée"
                size="sm"
                :options="sections.map((s) => ({ value: s.id, label: s.name, count: columns[s.id]?.length ?? 0 }))"
            />
        </div>
        <div ref="board" class="flex min-h-0 flex-1 items-start gap-3.5 overflow-x-auto p-4 md:p-7">
            <section
                v-for="section in sections"
                :key="section.id"
                :aria-label="section.name"
                class="flex max-h-full shrink-0 flex-col rounded-card bg-app-hover max-md:w-full md:w-[272px]"
                :class="{ 'max-md:hidden': section.id !== mobileColumn }"
                data-test="column"
            >
                <div class="group flex items-center gap-2 px-[18px] pb-2.5 pt-4">
                    <input
                        v-if="renaming === section.id"
                        :value="section.name"
                        aria-label="Nom de la section"
                        class="flex-1 rounded-field border-2 border-app-border-strong bg-background px-2 py-0.5 text-[15px] font-bold"
                        v-focus
                        @keydown.enter="renameSection(section, ($event.target as HTMLInputElement).value)"
                        @blur="renameSection(section, ($event.target as HTMLInputElement).value)"
                    />
                    <h3
                        v-else
                        class="flex-1 text-[15px] font-bold"
                        :title="canEdit ? 'Double-cliquer pour renommer' : ''"
                        @dblclick="canEdit && (renaming = section.id)"
                    >
                        {{ section.name }}
                        <span class="ml-1 text-sm font-semibold text-ink-soft">{{ columns[section.id]?.length ?? 0 }}</span>
                    </h3>
                    <button
                        v-if="canEdit && sections.length > 1"
                        class="rounded-icon p-1 text-ink-soft opacity-0 hover:bg-background hover:text-brand-active focus-visible:opacity-100 group-hover:opacity-100"
                        :aria-label="`Supprimer la section ${section.name}`"
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
                    class="flex min-h-12 flex-1 flex-col gap-2.5 overflow-y-auto px-3 pb-1"
                    @change="onChange($event, section.id)"
                >
                    <template #item="{ element }">
                        <TaskCard :task="element" :can-edit="canEdit" :selected="element.id === selectedId" @open="emit('open', $event)" />
                    </template>
                </draggable>

                <template v-if="canEdit">
                    <form v-if="addingIn === section.id" class="m-3 mt-2" @submit.prevent="addTask(section.id)">
                        <input
                            v-focus
                            v-model="newTask.title"
                            aria-label="Nom de la nouvelle tâche"
                            placeholder="Nom de la tâche, puis Entrée"
                            class="w-full rounded-task border-2 border-brand bg-background px-3.5 py-2.5 text-sm font-semibold shadow-soft outline-none"
                            @keydown.esc="addingIn = null"
                            @blur="!newTask.title && (addingIn = null)"
                        />
                    </form>
                    <button
                        v-else
                        class="mx-3 mb-3 mt-1 flex items-center gap-1.5 rounded-icon px-2 py-2 text-left text-sm font-semibold text-ink-soft hover:bg-background hover:text-ink"
                        @click="startAdding(section.id)"
                    >
                        <Plus class="size-4" /> Ajouter une tâche
                    </button>
                </template>
            </section>

            <form v-if="canEdit" class="shrink-0 max-md:hidden md:w-[272px]" @submit.prevent="addSection">
                <input
                    v-model="newSection.name"
                    aria-label="Nom de la nouvelle section"
                    placeholder="+ Ajouter une section"
                    class="h-12 w-full rounded-card border-2 border-dashed border-app-border-strong bg-transparent px-[18px] text-sm font-bold placeholder:text-ink-soft focus:border-info focus:outline-none"
                />
            </form>
        </div>
    </div>
</template>
