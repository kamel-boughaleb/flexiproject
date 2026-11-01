<script setup lang="ts">
import SegmentedControl from '@/components/design/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useSectionEditing, vFocusSelect } from '@/composables/useSectionEditing';
import type { Section, Task } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Ellipsis, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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

const newSection = useForm({ name: '' });
function addSection() {
    newSection.post(route('sections.store', props.projectId), { preserveScroll: true, onSuccess: () => newSection.reset() });
}

// Sections : clic sur le titre pour renommer, glisser le titre pour changer l'ordre.
const { ordered, reorder, renaming, rename } = useSectionEditing(
    () => props.projectId,
    () => props.sections,
);

// Suppression d'une section : confirmée dans une fenêtre, avec le nombre de tâches perdues.
const deleting = ref<Section | null>(null);
const deleteOpen = ref(false);
function askDelete(section: Section) {
    deleting.value = section;
    deleteOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(route('sections.destroy', deleting.value.id), { preserveScroll: true, onFinish: () => (deleteOpen.value = false) });
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
        <div class="flex min-h-0 flex-1 items-start gap-3.5 overflow-x-auto p-4 md:p-7">
            <draggable
                :model-value="ordered"
                group="sections"
                item-key="id"
                handle="[data-section-handle]"
                :disabled="!canEdit || renaming !== null"
                :animation="150"
                ghost-class="opacity-40"
                class="flex min-h-0 items-start gap-3.5 self-stretch max-md:w-full"
                @update:model-value="reorder"
            >
                <template #item="{ element: section }">
                    <section
                        :aria-label="section.name"
                        class="flex max-h-full shrink-0 flex-col rounded-card bg-app-hover max-md:w-full md:w-[272px]"
                        :class="{ 'max-md:hidden': section.id !== mobileColumn }"
                        data-test="column"
                    >
                        <div class="group flex items-center gap-2 px-[18px] pb-2.5 pt-4">
                            <input
                                v-if="renaming === section.id"
                                v-focus-select
                                :value="section.name"
                                aria-label="Nom de la section"
                                maxlength="100"
                                class="min-w-0 flex-1 rounded-field border-2 border-info bg-background px-2 py-0.5 text-[15px] font-bold outline-none"
                                @keydown.enter.prevent="rename(section, ($event.target as HTMLInputElement).value)"
                                @keydown.esc="renaming = null"
                                @blur="rename(section, ($event.target as HTMLInputElement).value)"
                            />
                            <h3 v-else class="min-w-0 flex-1 text-[15px] font-bold">
                                <span
                                    v-if="canEdit"
                                    data-section-handle
                                    role="button"
                                    tabindex="0"
                                    title="Cliquer pour renommer, glisser pour déplacer"
                                    class="-mx-1.5 cursor-grab rounded-icon px-1.5 py-0.5 hover:bg-background active:cursor-grabbing"
                                    @click="renaming = section.id"
                                    @keydown.enter.prevent="renaming = section.id"
                                    >{{ section.name }}</span
                                >
                                <template v-else>{{ section.name }}</template>
                                <span class="ml-1 text-sm font-semibold text-ink-soft">{{ columns[section.id]?.length ?? 0 }}</span>
                            </h3>
                            <DropdownMenu v-if="canEdit">
                                <DropdownMenuTrigger
                                    class="flex size-8 items-center justify-center rounded-icon text-ink-soft hover:bg-background hover:text-ink data-[state=open]:bg-background data-[state=open]:text-ink"
                                    :aria-label="`Actions de la section ${section.name}`"
                                >
                                    <Ellipsis class="size-[18px]" />
                                </DropdownMenuTrigger>
                                <!-- Pas de retour du focus sur le bouton : il fermerait aussitôt le champ « Renommer ». -->
                                <DropdownMenuContent align="end" class="w-44 rounded-2xl p-1.5" @close-auto-focus.prevent>
                                    <DropdownMenuItem class="rounded-icon font-semibold" @select="renaming = section.id">
                                        <Pencil class="size-4" /> Renommer
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="sections.length > 1"
                                        class="rounded-icon font-semibold text-destructive focus:bg-destructive/10 focus:text-destructive"
                                        @select="askDelete(section)"
                                    >
                                        <Trash2 class="size-4" /> Supprimer
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
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
                </template>
            </draggable>

            <form v-if="canEdit" class="shrink-0 max-md:hidden md:w-[272px]" @submit.prevent="addSection">
                <input
                    v-model="newSection.name"
                    aria-label="Nom de la nouvelle section"
                    placeholder="+ Ajouter une section"
                    class="h-12 w-full rounded-card border-2 border-dashed border-app-border-strong bg-transparent px-[18px] text-sm font-bold placeholder:text-ink-soft focus:border-info focus:outline-none"
                />
            </form>
        </div>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Supprimer la section « {{ deleting?.name }} » ?</DialogTitle>
                    <DialogDescription>
                        <template v-if="deleting && columns[deleting.id]?.length">
                            Ses {{ columns[deleting.id].length }} tâche(s) seront aussi supprimées.
                        </template>
                        Cette action est définitive.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="deleteOpen = false">Annuler</Button>
                    <Button variant="destructive" @click="confirmDelete">Supprimer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
