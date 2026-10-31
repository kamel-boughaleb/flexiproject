<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PRIORITIES, dueLabel, isOverdue, sectionColor } from '@/lib/labels';
import type { Priority, Section, SharedData, Task } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { ArrowUpDown, ChevronDown, ListChecks, ListFilter, MessageCircle, Rows3, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PriorityBadge from './PriorityBadge.vue';
import TaskCheck from './TaskCheck.vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{ projectId: number; sections: Section[]; tasks: Task[]; canEdit: boolean; selectedId?: number | null }>();
const emit = defineEmits<{ open: [id: number] }>();
const me = usePage<SharedData>().props.auth.user;

// Recherche, filtres, tri et regroupement (côté navigateur : toutes les tâches du projet sont déjà chargées).
const search = ref('');
const hideDone = ref(false);
const onlyMine = ref(false);
const onlyLate = ref(false);
const sort = ref<'position' | 'due' | 'priority' | 'title'>('position');
const groupBy = ref<'section' | 'assignee' | 'priority' | 'none'>('section');

const GROUP_LABELS = { section: 'Section', assignee: 'Responsable', priority: 'Priorité', none: 'Aucun' };
const PRIORITY_RANK: Record<Priority, number> = { high: 0, medium: 1, low: 2, none: 3 };

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = props.tasks.filter(
        (t) =>
            (!q || t.title.toLowerCase().includes(q)) &&
            (!hideDone.value || !t.completed) &&
            (!onlyMine.value || t.assignee_id === me.id) &&
            (!onlyLate.value || isOverdue(t.due_date, t.completed)),
    );
    const by = {
        position: () => 0,
        due: (a: Task, b: Task) => (a.due_date ?? '9999').localeCompare(b.due_date ?? '9999'),
        priority: (a: Task, b: Task) => PRIORITY_RANK[a.priority] - PRIORITY_RANK[b.priority],
        title: (a: Task, b: Task) => a.title.localeCompare(b.title, 'fr'),
    }[sort.value];
    return [...list].sort(by);
});

const groups = computed(() => {
    if (groupBy.value === 'section')
        return props.sections.map((s, i) => ({
            key: `s${s.id}`,
            name: s.name,
            color: sectionColor(i),
            sectionId: s.id,
            tasks: filtered.value.filter((t) => t.section_id === s.id),
        }));
    if (groupBy.value === 'priority')
        return (['high', 'medium', 'low', 'none'] as Priority[]).map((p) => ({
            key: `p${p}`,
            name: p === 'none' ? 'Sans priorité' : PRIORITIES[p].label,
            color: null,
            sectionId: null,
            tasks: filtered.value.filter((t) => t.priority === p),
        }));
    if (groupBy.value === 'assignee') {
        const people = new Map<number | null, string>();
        filtered.value.forEach((t) => people.set(t.assignee_id, t.assignee?.name ?? 'Sans responsable'));
        return [...people.entries()]
            .sort(([a], [b]) => (a === null ? 1 : b === null ? -1 : 0))
            .map(([id, name]) => ({ key: `a${id}`, name, color: null, sectionId: null, tasks: filtered.value.filter((t) => t.assignee_id === id) }));
    }
    return [{ key: 'all', name: 'Toutes les tâches', color: null, sectionId: null, tasks: filtered.value }];
});

const collapsed = ref<Record<string, boolean>>({});

const form = useForm({ title: '', section_id: 0 });
function add(sectionId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    form.title = input.value;
    form.section_id = sectionId;
    if (!form.title.trim()) return;
    form.post(route('tasks.store', props.projectId), { preserveScroll: true, preserveState: true, onSuccess: () => (input.value = '') });
}

const newSection = useForm({ name: '' });
const addingSection = ref(false);
function addSection() {
    if (!newSection.name.trim()) return (addingSection.value = false);
    newSection.post(route('sections.store', props.projectId), { preserveScroll: true, onSuccess: () => newSection.reset() });
}

const tool = 'flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-bold text-app-strong hover:bg-app-hover data-[state=open]:bg-app-hover';
const cols = 'md:grid md:grid-cols-[minmax(0,1fr)_170px_110px_110px_100px_70px] md:items-center md:gap-3';
const filterCount = computed(() => [hideDone.value, onlyMine.value, onlyLate.value].filter(Boolean).length);
</script>

<template>
    <div class="min-h-full">
        <div class="flex flex-wrap items-center gap-2 border-b border-app-border px-4 py-3 md:px-7">
            <label class="relative mr-auto w-full sm:w-[270px]">
                <span class="sr-only">Rechercher une tâche</span>
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher une tâche"
                    class="h-10 w-full rounded-full border-2 border-app-border-strong bg-background pl-10 pr-3 text-sm font-medium outline-none focus:border-info focus:ring-4 focus:ring-info-tint"
                />
            </label>
            <DropdownMenu>
                <DropdownMenuTrigger :class="tool">
                    <ListFilter class="size-4" /> Filtrer<span
                        v-if="filterCount"
                        class="rounded-full bg-brand-tint px-1.5 text-xs text-brand-active"
                        >{{ filterCount }}</span
                    >
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 rounded-2xl p-1.5">
                    <DropdownMenuCheckboxItem v-model:checked="hideDone" class="rounded-icon font-semibold"
                        >Masquer les tâches terminées</DropdownMenuCheckboxItem
                    >
                    <DropdownMenuCheckboxItem v-model:checked="onlyMine" class="rounded-icon font-semibold"
                        >Mes tâches uniquement</DropdownMenuCheckboxItem
                    >
                    <DropdownMenuCheckboxItem v-model:checked="onlyLate" class="rounded-icon font-semibold">En retard</DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
                <DropdownMenuTrigger :class="tool"><ArrowUpDown class="size-4" /> Trier</DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52 rounded-2xl p-1.5">
                    <DropdownMenuRadioGroup v-model="sort">
                        <DropdownMenuRadioItem value="position" class="rounded-icon font-semibold">Ordre du projet</DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="due" class="rounded-icon font-semibold">Échéance</DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="priority" class="rounded-icon font-semibold">Priorité</DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="title" class="rounded-icon font-semibold">Nom</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
                <DropdownMenuTrigger :class="[tool, 'bg-app-hover']"
                    ><Rows3 class="size-4" /> Regrouper : {{ GROUP_LABELS[groupBy] }}</DropdownMenuTrigger
                >
                <DropdownMenuContent align="end" class="w-48 rounded-2xl p-1.5">
                    <DropdownMenuLabel class="text-xs text-ink-soft">Regrouper par</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="groupBy">
                        <DropdownMenuRadioItem v-for="(label, key) in GROUP_LABELS" :key="key" :value="key" class="rounded-icon font-semibold">{{
                            label
                        }}</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="px-2 pb-10 md:px-7" role="table" aria-label="Tâches du projet">
            <div
                role="row"
                :class="cols"
                class="sticky top-0 z-10 hidden border-b border-app-border bg-background py-3 pl-9 pr-2 text-xs font-bold uppercase tracking-[.04em] text-ink-soft md:grid"
            >
                <span role="columnheader">Nom de la tâche</span><span role="columnheader">Responsable</span><span role="columnheader">Échéance</span
                ><span role="columnheader">Priorité</span><span role="columnheader">Sous-tâches</span><span role="columnheader">Activité</span>
            </div>

            <section v-for="group in groups" :key="group.key" role="rowgroup" class="mt-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-icon px-2 py-2 text-left hover:bg-app-hover"
                    :aria-expanded="!collapsed[group.key]"
                    @click="collapsed[group.key] = !collapsed[group.key]"
                >
                    <ChevronDown class="size-4 transition-transform" :class="{ '-rotate-90': collapsed[group.key] }" />
                    <span v-if="group.color" class="size-2 rounded-full" :style="{ backgroundColor: group.color }" />
                    <span class="text-base font-bold">{{ group.name }}</span>
                    <span class="text-xs font-semibold text-ink-soft">{{ group.tasks.length }}</span>
                </button>
                <template v-if="!collapsed[group.key]">
                    <div
                        v-for="task in group.tasks"
                        :key="task.id"
                        role="row"
                        :class="[cols, task.id === selectedId ? 'bg-brand-wash' : 'hover:bg-app-surface']"
                        class="flex cursor-pointer items-center gap-3 border-b border-app-border py-2 pl-2 pr-2 text-sm"
                        @click="emit('open', task.id)"
                    >
                        <span role="cell" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center md:size-6"
                                ><TaskCheck :task-id="task.id" :completed="task.completed" :disabled="!canEdit"
                            /></span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold" :class="{ 'text-ink-soft line-through': task.completed }">{{
                                    task.title
                                }}</span>
                                <!-- Mobile : méta sous le nom -->
                                <span class="flex flex-wrap items-center gap-x-2 text-xs font-medium text-ink-soft md:hidden">
                                    <span v-if="task.due_date" :class="{ 'font-bold text-brand-active': isOverdue(task.due_date, task.completed) }">{{
                                        dueLabel(task.due_date)
                                    }}</span>
                                    <PriorityBadge :priority="task.priority" />
                                    <span v-if="task.comments_count" class="flex items-center gap-0.5"
                                        ><MessageCircle class="size-3" />{{ task.comments_count }}</span
                                    >
                                </span>
                            </span>
                        </span>
                        <span role="cell" class="hidden min-w-0 items-center gap-2 md:flex">
                            <template v-if="task.assignee"
                                ><UserAvatar :user="task.assignee" size="xs" /><span class="truncate font-bold">{{
                                    task.assignee.name
                                }}</span></template
                            >
                        </span>
                        <span
                            role="cell"
                            class="hidden font-semibold md:block"
                            :class="isOverdue(task.due_date, task.completed) ? 'font-bold text-brand-active' : 'text-app-strong'"
                            >{{ dueLabel(task.due_date) }}</span
                        >
                        <span role="cell" class="hidden md:block"><PriorityBadge :priority="task.priority" /></span>
                        <span role="cell" class="hidden items-center gap-1 text-xs font-bold text-ink-soft md:flex">
                            <template v-if="task.subtasks_count"
                                ><ListChecks class="size-3.5" />{{ task.subtasks_done_count ?? 0 }}/{{ task.subtasks_count }}</template
                            >
                        </span>
                        <span role="cell" class="hidden items-center gap-1 text-xs font-bold text-ink-soft md:flex">
                            <template v-if="task.comments_count"><MessageCircle class="size-3.5" />{{ task.comments_count }}</template>
                        </span>
                    </div>
                    <input
                        v-if="canEdit && group.sectionId"
                        data-add-task
                        aria-label="Ajouter une tâche"
                        placeholder="Ajouter une tâche…"
                        class="h-11 w-full border-b border-app-border bg-transparent pl-14 text-sm font-semibold outline-none placeholder:text-ink-soft focus:bg-app-surface md:pl-11"
                        @keydown.enter.prevent="add(group.sectionId, $event)"
                    />
                </template>
            </section>

            <template v-if="canEdit && groupBy === 'section'">
                <form v-if="addingSection" class="mt-5" @submit.prevent="addSection">
                    <input
                        v-model="newSection.name"
                        autofocus
                        aria-label="Nom de la nouvelle section"
                        placeholder="Nom de la section, puis Entrée"
                        class="h-10 w-72 rounded-full border-2 border-info px-4 text-sm font-bold outline-none"
                        @blur="addSection"
                        @keydown.esc="addingSection = false"
                    />
                </form>
                <button
                    v-else
                    type="button"
                    class="mt-5 h-10 rounded-full border-2 border-dashed border-app-border-strong px-4 text-sm font-bold text-ink-soft hover:border-app-muted hover:text-ink"
                    @click="addingSection = true"
                >
                    + Ajouter une section
                </button>
            </template>
        </div>
    </div>
</template>
