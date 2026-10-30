<script setup lang="ts">
import DateField from '@/components/design/DateField.vue';
import InlineSelect from '@/components/design/InlineSelect.vue';
import PriorityPicker from '@/components/design/PriorityPicker.vue';
import { isOverdue, sectionColor } from '@/lib/labels';
import type { Member, Priority, Project, Section, TaskDetail } from '@/types';
import { CalendarDays, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import UserAvatar from './UserAvatar.vue';

/** Champs de la fiche tâche : « libellé à gauche / valeur cliquable à droite », modifiés en place. */
const props = defineProps<{ task: TaskDetail; project: Project; members: Member[]; sections: Section[]; canEdit: boolean; stacked?: boolean }>();
const emit = defineEmits<{ save: [field: string, value: string | number | null] }>();

const assignee = computed(() => props.members.find((m) => m.id === props.task.assignee_id) ?? null);
const sectionIndex = computed(() => props.sections.findIndex((s) => s.id === props.task.section_id));
const overdue = computed(() => isOverdue(props.task.due_date, props.task.completed));

const assigneeOptions = computed(() => [{ value: '', label: 'Personne' }, ...props.members.map((m) => ({ value: m.id, label: m.name }))]);
const sectionOptions = computed(() => props.sections.map((s) => ({ value: s.id, label: s.name })));

const assigneeModel = computed({
    get: () => props.task.assignee_id ?? '',
    set: (v: string | number) => emit('save', 'assignee_id', v === '' ? null : Number(v)),
});
const sectionModel = computed({ get: () => props.task.section_id, set: (v: string | number) => emit('save', 'section_id', Number(v)) });
const startModel = computed({ get: () => props.task.start_date, set: (v: string | null) => emit('save', 'start_date', v) });
const dueModel = computed({ get: () => props.task.due_date, set: (v: string | null) => emit('save', 'due_date', v) });
const priorityModel = computed({ get: () => props.task.priority, set: (v: Priority) => emit('save', 'priority', v) });
</script>

<template>
    <dl
        class="grid items-center gap-x-4"
        :class="stacked ? 'grid-cols-1 gap-y-1' : 'grid-cols-[110px_minmax(0,1fr)] gap-y-2 sm:grid-cols-[140px_minmax(0,1fr)]'"
    >
        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">Responsable</dt>
        <dd class="min-h-9 py-0.5">
            <InlineSelect v-model="assigneeModel" label="Responsable" :options="assigneeOptions" :disabled="!canEdit">
                <span class="flex items-center gap-2 py-0.5 pl-0.5 pr-3">
                    <UserAvatar :user="assignee" size="sm" />
                    <span class="truncate text-sm font-bold" :class="{ 'font-semibold text-ink-soft': !assignee }">{{
                        assignee?.name ?? 'Personne'
                    }}</span>
                </span>
            </InlineSelect>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">Dates</dt>
        <dd class="flex min-h-9 flex-wrap items-center gap-1">
            <span
                class="mr-1 flex size-7 items-center justify-center rounded-full border-[1.5px] border-dashed"
                :class="overdue ? 'border-brand-active text-brand-active' : 'border-app-muted text-ink-soft'"
            >
                <CalendarDays class="size-3.5" />
            </span>
            <DateField v-model="startModel" label="Date de début" placeholder="Début" :disabled="!canEdit" />
            <span class="text-sm text-ink-soft" aria-hidden="true">→</span>
            <DateField v-model="dueModel" label="Échéance" placeholder="Échéance" :disabled="!canEdit" :danger="overdue" />
            <span v-if="overdue" class="ml-1 rounded-full bg-brand-tint px-2 py-0.5 text-xs font-bold text-brand-active">En retard</span>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">Section</dt>
        <dd class="min-h-9 py-0.5">
            <InlineSelect v-model="sectionModel" label="Section" :options="sectionOptions" :disabled="!canEdit || !!task.parent_id">
                <span class="flex items-center gap-1.5 rounded-full bg-info-tint py-1 pl-3 pr-2.5 text-sm font-bold text-info-deep">
                    <span class="size-2 rounded-full" :style="{ backgroundColor: sectionColor(sectionIndex) }" />
                    {{ sections[sectionIndex]?.name }}
                    <ChevronDown v-if="canEdit && !task.parent_id" class="size-3.5" />
                </span>
            </InlineSelect>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">Priorité</dt>
        <dd class="min-h-9 py-1">
            <PriorityPicker v-model="priorityModel" :disabled="!canEdit" />
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">Projet</dt>
        <dd class="flex min-h-9 items-center gap-2 text-sm font-bold">
            <span class="size-3 rounded-[3px]" :style="{ backgroundColor: project.color }" />{{ project.name }}
        </dd>
    </dl>
</template>
