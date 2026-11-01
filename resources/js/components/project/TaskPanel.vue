<script setup lang="ts">
import RichContent from '@/components/editor/RichContent.vue';
import RichEditor from '@/components/editor/RichEditor.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { dateTime, sectionColor } from '@/lib/labels';
import type { Member, Project, Section, TaskDetail } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { ArrowRightToLine, Check, ChevronLeft, ChevronRight, Ellipsis, Link2, Maximize2, Minimize2, Paperclip, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import TaskActivity from './TaskActivity.vue';
import TaskAttachments from './TaskAttachments.vue';
import TaskCheck from './TaskCheck.vue';
import TaskComposer from './TaskComposer.vue';
import TaskFields from './TaskFields.vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{
    task: TaskDetail;
    project: Project;
    members: Member[];
    sections: Section[];
    canEdit: boolean;
    canManage: boolean;
}>();
const emit = defineEmits<{ close: []; open: [id: number] }>();
/** Plein écran (maquette « Tâche ») ou panneau latéral à droite du board (maquette « Main »). */
const full = defineModel<boolean>('full', { default: false });

const opts = { preserveScroll: true, preserveState: true };

/** Enregistre un champ dès qu'il change (comme dans Asana, pas de bouton « Enregistrer »). */
function save(field: string, value: string | number | null) {
    if ((props.task as unknown as Record<string, unknown>)[field] === value) return;
    router.patch(route('tasks.update', props.task.id), { [field]: value === '' ? null : value }, opts);
}

/**
 * Enregistre la description d'une tâche précise : l'éditeur peut signaler sa dernière modification
 * en se fermant, alors que le panneau affiche déjà une autre tâche.
 */
function descriptionSaver(id: number) {
    return (html: string) => {
        if (id === props.task.id) save('description', html);
        else router.patch(route('tasks.update', id), { description: html || null }, opts);
    };
}

/** Collaborateurs que l'on peut mentionner avec « @ » (invitations acceptées seulement). */
const mentionable = computed(() => props.members.filter((m) => !m.pending));
const section = computed(() => props.sections.find((s) => s.id === props.task.section_id));
const sectionIndex = computed(() => props.sections.findIndex((s) => s.id === props.task.section_id));

// « Marquer comme terminée » : bascule optimiste, confirmée par le serveur.
const done = ref(props.task.completed);
watch(
    () => [props.task.id, props.task.completed],
    () => (done.value = props.task.completed),
);
function toggleDone() {
    if (!props.canEdit) return;
    done.value = !done.value;
    router.post(route('tasks.toggle', props.task.id), {}, { ...opts, onError: () => (done.value = props.task.completed) });
}

// Sous-tâches : compteur et barre de progression mis à jour dès que l'on coche.
const subDone = ref<Record<number, boolean>>({});
watch(
    () => props.task.subtasks,
    (subtasks) => (subDone.value = Object.fromEntries(subtasks.map((s) => [s.id, s.completed]))),
    { immediate: true },
);
const subDoneCount = computed(() => Object.values(subDone.value).filter(Boolean).length);

const subtask = useForm({ title: '', parent_id: 0 });
function addSubtask() {
    subtask.parent_id = props.task.id;
    subtask.post(route('tasks.store', props.task.project_id), { ...opts, onSuccess: () => subtask.reset('title') });
}

function destroy() {
    if (confirm(`Supprimer « ${props.task.title} » ?`)) {
        router.delete(route('tasks.destroy', props.task.id), { ...opts, onSuccess: () => emit('close') });
    }
}

const copied = ref(false);
async function copyLink() {
    try {
        await navigator.clipboard.writeText(window.location.href);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        window.prompt('Lien de la tâche :', window.location.href);
    }
}

const attachments = ref<InstanceType<typeof TaskAttachments>>();
const pickFiles = async () => {
    await nextTick();
    attachments.value?.pick();
};

// Titre : zone de texte qui s'agrandit avec le contenu.
const titleInput = ref<HTMLTextAreaElement>();
function autosize() {
    const el = titleInput.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}
function saveTitle(e: Event) {
    const value = (e.target as HTMLTextAreaElement).value.replace(/\s*\n\s*/g, ' ').trim();
    if (value) save('title', value);
    else (e.target as HTMLTextAreaElement).value = props.task.title;
}
onMounted(autosize);
watch([() => props.task.id, full], () => nextTick(autosize));

// Échap ferme la fiche (sauf pendant une saisie ou si une fenêtre est ouverte par-dessus).
useEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key !== 'Escape' || e.defaultPrevented) return;
    const target = e.target as HTMLElement | null;
    if (target?.closest('input, textarea, select, [contenteditable="true"]')) return;
    if (document.querySelector('[role="dialog"], [role="menu"]')) return;
    if (full.value) full.value = false;
    else emit('close');
});

/** Personnes qui suivent la tâche : responsable et auteurs des commentaires. */
const participants = computed(() => {
    const people = new Map<number, { id: number; name: string; color: string; avatar?: string | null }>();
    const assignee = props.members.find((m) => m.id === props.task.assignee_id);
    if (assignee) people.set(assignee.id, assignee);
    props.task.comments.forEach((c) => people.set(c.user.id, c.user));
    return [...people.values()];
});

const iconBtn =
    'flex size-10 items-center justify-center rounded-icon text-app-strong transition hover:bg-app-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring [&_svg]:size-[18px]';
</script>

<template>
    <aside
        :aria-label="`Tâche : ${task.title}`"
        class="flex flex-col bg-background"
        :class="
            full
                ? 'absolute inset-0 z-30'
                : 'fixed inset-0 z-40 md:absolute md:inset-y-0 md:left-auto md:right-0 md:z-30 md:w-[min(640px,58%)] md:border-l md:border-app-border md:shadow-[-12px_0_24px_-12px_#112a3b40]'
        "
    >
        <!-- Barre d'outils -->
        <header class="flex shrink-0 items-center gap-1 border-b border-app-border px-3 py-2.5 md:px-5">
            <template v-if="full">
                <button class="flex items-center gap-1 rounded-full px-2 py-1.5 text-sm font-bold hover:bg-app-hover" @click="full = false">
                    <ChevronLeft class="size-4" /> {{ project.name }}
                </button>
                <ChevronRight class="size-3.5 text-ink-soft" aria-hidden="true" />
                <span class="truncate px-1 text-sm font-medium text-ink-soft">{{ section?.name }}</span>
            </template>
            <button
                type="button"
                :disabled="!canEdit"
                :aria-pressed="done"
                class="flex h-9 shrink-0 items-center gap-1.5 rounded-full border-2 px-3.5 text-sm font-bold transition disabled:opacity-60"
                :class="[
                    done ? 'border-success bg-success-tint text-success-text' : 'border-app-border-strong text-app-strong hover:bg-app-hover',
                    full ? 'ml-auto' : 'max-md:hidden',
                ]"
                @click="toggleDone"
            >
                <Check class="size-4" :stroke-width="2.5" />
                {{ done ? 'Terminée' : 'Marquer comme terminée' }}
            </button>
            <div class="flex items-center gap-0.5" :class="{ 'ml-auto': !full }">
                <span v-if="copied" role="status" class="mr-1 text-xs font-bold text-success-text">Lien copié</span>
                <button :class="iconBtn" aria-label="Copier le lien de la tâche" title="Copier le lien" @click="copyLink"><Link2 /></button>
                <button v-if="canEdit && !full" :class="iconBtn" aria-label="Joindre un fichier" title="Joindre un fichier" @click="pickFiles">
                    <Paperclip />
                </button>
                <button
                    :class="[iconBtn, 'max-md:hidden']"
                    :aria-label="full ? 'Réduire en panneau' : 'Ouvrir en plein écran'"
                    :title="full ? 'Réduire' : 'Plein écran'"
                    @click="full = !full"
                >
                    <Minimize2 v-if="full" /><Maximize2 v-else />
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger :class="iconBtn" aria-label="Plus d'actions"><Ellipsis /></DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52 rounded-2xl p-1.5">
                        <DropdownMenuItem class="rounded-icon font-semibold" @select="copyLink"
                            ><Link2 class="size-4" /> Copier le lien</DropdownMenuItem
                        >
                        <template v-if="canEdit">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="rounded-icon font-semibold text-brand-active focus:text-brand-active" @select="destroy">
                                <Trash2 class="size-4" /> Supprimer la tâche
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
                <template v-if="!full">
                    <span class="mx-1 h-6 w-px bg-app-border" aria-hidden="true" />
                    <button :class="iconBtn" aria-label="Fermer la tâche" title="Fermer (Échap)" @click="emit('close')"><ArrowRightToLine /></button>
                </template>
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div
                :class="
                    full
                        ? 'mx-auto grid max-w-[1180px] items-start gap-8 px-5 py-8 lg:grid-cols-[minmax(0,1fr)_minmax(280px,340px)] lg:px-10'
                        : 'px-4 py-5 md:px-8 md:py-6'
                "
            >
                <div class="flex min-w-0 flex-col gap-7">
                    <!-- Mobile : bouton Terminer pleine largeur -->
                    <button
                        v-if="!full"
                        type="button"
                        :disabled="!canEdit"
                        :aria-pressed="done"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-full border-2 text-sm font-bold md:hidden"
                        :class="done ? 'border-success bg-success-tint text-success-text' : 'border-app-border-strong text-app-strong'"
                        @click="toggleDone"
                    >
                        <Check class="size-4" :stroke-width="2.5" /> {{ done ? 'Terminée' : 'Marquer comme terminée' }}
                    </button>

                    <p
                        v-if="done"
                        role="status"
                        class="flex items-center gap-2 rounded-2xl bg-success-tint px-4 py-2.5 text-[13px] font-bold text-success-text"
                    >
                        <Check class="size-4" :stroke-width="2.5" />
                        Tâche terminée<template v-if="task.completed_at"> · {{ dateTime(task.completed_at) }}</template>
                    </p>

                    <div>
                        <nav v-if="!full" aria-label="Emplacement" class="mb-2 flex flex-wrap items-center gap-1.5 text-xs font-medium text-ink-soft">
                            <span class="size-2 rounded-[2px]" :style="{ backgroundColor: project.color }" />{{ project.name }}
                            <ChevronRight class="size-3" aria-hidden="true" />
                            <span class="flex items-center gap-1"
                                ><span class="size-1.5 rounded-full" :style="{ backgroundColor: sectionColor(sectionIndex) }" />{{
                                    section?.name
                                }}</span
                            >
                        </nav>
                        <button
                            v-if="task.parent"
                            class="mb-1 flex items-center gap-1 text-xs font-bold text-info-active hover:underline"
                            @click="emit('open', task.parent.id)"
                        >
                            <ChevronLeft class="size-3.5" /> {{ task.parent.title }}
                        </button>
                        <textarea
                            ref="titleInput"
                            :key="`title-${task.id}`"
                            :value="task.title"
                            :disabled="!canEdit"
                            rows="1"
                            aria-label="Nom de la tâche"
                            class="-mx-2 block w-[calc(100%+1rem)] resize-none overflow-hidden rounded-field border-2 border-transparent bg-transparent px-2 py-0.5 font-bold leading-tight outline-none transition focus:border-info enabled:hover:border-app-border disabled:bg-transparent"
                            :class="[full ? 'text-[28px] md:text-[35px]' : 'text-[22px] md:text-[26px]', { 'text-ink-soft line-through': done }]"
                            @input="autosize"
                            @keydown.enter.prevent="($event.target as HTMLTextAreaElement).blur()"
                            @change="saveTitle"
                        />
                    </div>

                    <TaskFields
                        v-if="!full"
                        :task="task"
                        :project="project"
                        :members="members"
                        :sections="sections"
                        :can-edit="canEdit"
                        @save="save"
                    />

                    <section aria-labelledby="desc-title">
                        <h3 id="desc-title" class="mb-2 text-base font-bold" :class="{ 'sr-only': full }">Description</h3>
                        <RichEditor
                            v-if="canEdit"
                            :key="`desc-${task.id}`"
                            bare
                            :model-value="task.description"
                            :project-id="task.project_id"
                            :members="mentionable"
                            placeholder="Ajoutez des détails… Saisissez « / » pour afficher le menu"
                            min-height="2.5rem"
                            :onChange="descriptionSaver(task.id)"
                            class="-mx-1.5"
                        />
                        <RichContent v-else-if="task.description" :html="task.description" class="text-[15px]" />
                        <p v-else class="text-sm text-ink-soft">Aucune description.</p>
                    </section>

                    <section v-if="!task.parent_id" aria-labelledby="subtasks-title">
                        <div class="mb-3 flex items-center gap-3">
                            <h3 id="subtasks-title" class="text-base font-bold">Sous-tâches</h3>
                            <template v-if="task.subtasks.length">
                                <span class="text-sm font-semibold text-ink-soft">{{ subDoneCount }}/{{ task.subtasks.length }}</span>
                                <span
                                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-app-hover"
                                    role="progressbar"
                                    :aria-valuenow="subDoneCount"
                                    aria-valuemin="0"
                                    :aria-valuemax="task.subtasks.length"
                                    aria-label="Avancement des sous-tâches"
                                >
                                    <span
                                        class="block h-full rounded-full bg-success transition-all"
                                        :style="{ width: `${(subDoneCount / task.subtasks.length) * 100}%` }"
                                    />
                                </span>
                            </template>
                        </div>
                        <ul class="divide-y divide-app-border overflow-hidden rounded-card border border-app-border">
                            <li v-for="s in task.subtasks" :key="s.id" class="flex min-h-11 items-center gap-3 px-4 py-2 hover:bg-app-surface">
                                <TaskCheck
                                    :task-id="s.id"
                                    :completed="s.completed"
                                    :disabled="!canEdit"
                                    size="lg"
                                    @toggled="(v) => (subDone[s.id] = v)"
                                />
                                <button
                                    class="min-w-0 flex-1 truncate text-left text-sm font-semibold hover:underline"
                                    :class="{ 'text-ink-soft line-through': subDone[s.id] }"
                                    @click="emit('open', s.id)"
                                >
                                    {{ s.title }}
                                </button>
                                <span v-if="s.due_date" class="text-xs font-semibold text-ink-soft">{{ dateTime(s.due_date).split(' à')[0] }}</span>
                                <UserAvatar v-if="s.assignee" :user="s.assignee" size="xs" />
                            </li>
                            <li v-if="canEdit">
                                <form class="flex min-h-11 items-center gap-3 px-4" @submit.prevent="addSubtask">
                                    <Plus class="size-[18px] shrink-0 text-ink-soft" />
                                    <input
                                        v-model="subtask.title"
                                        aria-label="Nouvelle sous-tâche"
                                        placeholder="Ajouter une sous-tâche"
                                        class="h-11 w-full bg-transparent text-sm font-semibold outline-none placeholder:text-ink-soft"
                                    />
                                </form>
                            </li>
                        </ul>
                    </section>

                    <TaskAttachments
                        ref="attachments"
                        :task-id="task.id"
                        :attachments="task.attachments"
                        :can-edit="canEdit"
                        :can-manage="canManage"
                    />

                    <div class="border-t border-app-border pt-6">
                        <TaskActivity :task="task" />
                    </div>

                    <TaskComposer v-if="full" :task="task" :members="mentionable" />
                </div>

                <!-- Plein écran : colonne de droite -->
                <div v-if="full" class="space-y-4 lg:sticky lg:top-8">
                    <section class="rounded-card border border-app-border p-5 shadow-soft" aria-labelledby="details-title">
                        <h3 id="details-title" class="mb-1 text-xs font-bold uppercase tracking-[.04em] text-ink-soft">Détails</h3>
                        <TaskFields
                            stacked
                            :task="task"
                            :project="project"
                            :members="members"
                            :sections="sections"
                            :can-edit="canEdit"
                            @save="save"
                        />
                    </section>
                    <section class="rounded-card border border-app-border p-5 shadow-soft" aria-labelledby="people-title">
                        <h3 id="people-title" class="mb-3 text-xs font-bold uppercase tracking-[.04em] text-ink-soft">Collaborateurs</h3>
                        <div class="flex flex-wrap gap-1.5">
                            <UserAvatar v-for="p in participants" :key="p.id" :user="p" size="md" />
                            <span v-if="!participants.length" class="text-sm text-ink-soft">Personne pour l'instant.</span>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-ink-soft">
                            <template v-if="task.created_at">Créée le {{ dateTime(task.created_at) }}<br /></template>
                            <template v-if="task.updated_at">Modifiée le {{ dateTime(task.updated_at) }}</template>
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <!-- Panneau : compositeur collé en bas -->
        <footer v-if="!full" class="shrink-0 border-t border-app-border bg-background px-4 py-3 md:px-8">
            <TaskComposer :task="task" :members="mentionable" />
        </footer>
    </aside>
</template>
