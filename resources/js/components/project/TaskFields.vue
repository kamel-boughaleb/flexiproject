<script setup lang="ts">
import DateField from '@/components/design/DateField.vue';
import InlineSelect from '@/components/design/InlineSelect.vue';
import PriorityPicker from '@/components/design/PriorityPicker.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { isOverdue, sectionColor } from '@/lib/labels';
import type { Member, Priority, Project, Section, TaskDetail } from '@/types';
import { router } from '@inertiajs/vue3';
import { CalendarDays, ChevronDown, Plus, X } from 'lucide-vue-next';
import { computed } from 'vue';
import UserAvatar from './UserAvatar.vue';

/** Champs de la fiche tâche : « libellé à gauche / valeur cliquable à droite », modifiés en place. */
const props = defineProps<{ task: TaskDetail; project: Project; members: Member[]; sections: Section[]; canEdit: boolean; stacked?: boolean }>();
const emit = defineEmits<{ save: [field: string, value: string | number | null] }>();

const assignee = computed(() => props.members.find((m) => m.id === props.task.assignee_id) ?? null);
const sectionIndex = computed(() => props.sections.findIndex((s) => s.id === props.task.section_id));
const overdue = computed(() => isOverdue(props.task.due_date, props.task.completed));

const assigneeOptions = computed(() => [{ value: '', label: t('Personne') }, ...props.members.map((m) => ({ value: m.id, label: m.name }))]);
const sectionOptions = computed(() => props.sections.map((s) => ({ value: s.id, label: s.name })));

const assigneeModel = computed({
    get: () => props.task.assignee_id ?? '',
    set: (v: string | number) => emit('save', 'assignee_id', v === '' ? null : Number(v)),
});
const sectionModel = computed({ get: () => props.task.section_id, set: (v: string | number) => emit('save', 'section_id', Number(v)) });
const startModel = computed({ get: () => props.task.start_date, set: (v: string | null) => emit('save', 'start_date', v) });
const dueModel = computed({ get: () => props.task.due_date, set: (v: string | null) => emit('save', 'due_date', v) });
const priorityModel = computed({ get: () => props.task.priority, set: (v: Priority) => emit('save', 'priority', v) });

/** Collaborateurs liés à la tâche (responsable, commentateurs, mentionnés, ajoutés à la main). */
const followers = computed(() => props.task.followers ?? []);
const addable = computed(() => props.members.filter((m) => !m.pending && !m.deactivated && !followers.value.some((f) => f.id === m.id)));
const opts = { preserveScroll: true, preserveState: true };
const addFollower = (id: number) => router.post(route('tasks.followers.store', props.task.id), { user_id: id }, opts);
const removeFollower = (id: number) => router.delete(route('tasks.followers.destroy', [props.task.id, id]), opts);
</script>

<template>
    <dl
        class="grid items-center gap-x-4"
        :class="stacked ? 'grid-cols-1 gap-y-1' : 'grid-cols-[110px_minmax(0,1fr)] gap-y-2 sm:grid-cols-[140px_minmax(0,1fr)]'"
    >
        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Responsable') }}</dt>
        <dd class="min-h-9 py-0.5">
            <InlineSelect v-model="assigneeModel" :label="$t('Responsable')" :options="assigneeOptions" :disabled="!canEdit">
                <span class="flex items-center gap-2 py-0.5 pl-0.5 pr-3">
                    <UserAvatar :user="assignee" size="sm" />
                    <span class="truncate text-sm font-bold" :class="{ 'font-semibold text-ink-soft': !assignee }">{{
                        assignee?.name ?? $t('Personne')
                    }}</span>
                </span>
            </InlineSelect>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Collaborateurs') }}</dt>
        <dd class="flex min-h-9 flex-wrap items-center gap-1.5 py-0.5">
            <span
                v-for="f in followers"
                :key="f.id"
                class="group/follower relative inline-flex items-center gap-1.5 rounded-full bg-app-hover py-0.5 pl-0.5 text-sm font-semibold"
                :class="canEdit ? 'pr-1' : 'pr-2.5'"
            >
                <UserAvatar :user="f" size="sm" />
                <span class="max-w-32 truncate">{{ f.name }}</span>
                <button
                    v-if="canEdit"
                    type="button"
                    class="flex size-5 items-center justify-center rounded-full text-ink-soft transition hover:bg-background hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="$t('Retirer :name de la tâche', { name: f.name })"
                    :title="$t('Retirer :name de la tâche', { name: f.name })"
                    @click="removeFollower(f.id)"
                >
                    <X class="size-3.5" />
                </button>
            </span>
            <DropdownMenu v-if="canEdit">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-full border-[1.5px] border-dashed border-app-muted text-ink-soft transition hover:border-brand hover:text-brand-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :aria-label="$t('Ajouter un collaborateur')"
                        :title="$t('Ajouter un collaborateur')"
                    >
                        <Plus class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="z-[60] max-h-72 w-60 overflow-y-auto">
                    <DropdownMenuItem v-for="m in addable" :key="m.id" class="gap-2" @select="addFollower(m.id)">
                        <UserAvatar :user="m" size="xs" /><span class="truncate">{{ m.name }}</span>
                    </DropdownMenuItem>
                    <p v-if="!addable.length" class="px-2 py-1.5 text-sm text-ink-soft">{{ $t('Tous les membres du projet sont déjà là.') }}</p>
                </DropdownMenuContent>
            </DropdownMenu>
            <span v-if="!followers.length && !canEdit" class="text-sm text-ink-soft">{{ $t("Personne pour l'instant.") }}</span>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Dates') }}</dt>
        <dd class="flex min-h-9 flex-wrap items-center gap-1">
            <span
                class="mr-1 flex size-7 items-center justify-center rounded-full border-[1.5px] border-dashed"
                :class="overdue ? 'border-brand-active text-brand-active' : 'border-app-muted text-ink-soft'"
            >
                <CalendarDays class="size-3.5" />
            </span>
            <DateField v-model="startModel" :label="$t('Date de début')" :placeholder="$t('Début')" :disabled="!canEdit" />
            <span class="text-sm text-ink-soft" aria-hidden="true">→</span>
            <DateField v-model="dueModel" :label="$t('Échéance')" :placeholder="$t('Échéance')" :disabled="!canEdit" :danger="overdue" />
            <span v-if="overdue" class="ml-1 rounded-full bg-brand-tint px-2 py-0.5 text-xs font-bold text-brand-active">{{ $t('En retard') }}</span>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Section') }}</dt>
        <dd class="min-h-9 py-0.5">
            <InlineSelect v-model="sectionModel" :label="$t('Section')" :options="sectionOptions" :disabled="!canEdit || !!task.parent_id">
                <span class="flex items-center gap-1.5 rounded-full bg-info-tint py-1 pl-3 pr-2.5 text-sm font-bold text-info-deep">
                    <span class="size-2 rounded-full" :style="{ backgroundColor: sectionColor(sectionIndex) }" />
                    {{ sections[sectionIndex]?.name }}
                    <ChevronDown v-if="canEdit && !task.parent_id" class="size-3.5" />
                </span>
            </InlineSelect>
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Priorité') }}</dt>
        <dd class="min-h-9 py-1">
            <PriorityPicker v-model="priorityModel" :disabled="!canEdit" />
        </dd>

        <dt class="text-sm font-semibold text-ink-soft" :class="{ 'mt-2': stacked }">{{ $t('Projet') }}</dt>
        <dd class="flex min-h-9 items-center gap-2 text-sm font-bold">
            <span class="size-3 rounded-[3px]" :style="{ backgroundColor: project.color }" />{{ project.name }}
        </dd>
    </dl>
</template>
