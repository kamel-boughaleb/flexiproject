<script setup lang="ts">
import RichContent from '@/components/editor/RichContent.vue';
import RichEditor from '@/components/editor/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { PRIORITIES, dateTime } from '@/lib/labels';
import type { Member, Section, TaskDetail } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TaskCheck from './TaskCheck.vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{ task: TaskDetail; members: Member[]; sections: Section[]; canEdit: boolean }>();
const emit = defineEmits<{ close: []; open: [id: number] }>();

const opts = { preserveScroll: true, preserveState: true };

/** Enregistre un champ dès qu'il change (comme dans Asana, pas de bouton « Enregistrer »). */
function save(field: string, value: string | number | null) {
    if ((props.task as unknown as Record<string, unknown>)[field] === value) return;
    router.patch(route('tasks.update', props.task.id), { [field]: value === '' ? null : value }, opts);
}
const val = (e: Event) => (e.target as HTMLInputElement).value;

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

const comment = useForm({ body: '' });
const commentEditor = ref<InstanceType<typeof RichEditor>>();
function addComment(html: string) {
    comment.body = html;
    comment.post(route('comments.store', props.task.id), {
        ...opts,
        onSuccess: () => {
            comment.reset();
            commentEditor.value?.clear();
        },
    });
}

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
</script>

<template>
    <Sheet :open="true" @update:open="(open) => !open && emit('close')">
        <SheetContent class="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
            <header class="flex items-center gap-3 border-b px-5 py-3 pr-12">
                <TaskCheck :task-id="task.id" :completed="task.completed" :disabled="!canEdit" />
                <span class="text-sm text-muted-foreground">{{ task.completed ? 'Terminée' : 'À faire' }}</span>
                <Button v-if="canEdit" variant="ghost" size="sm" class="ml-auto text-rose-600" @click="destroy"
                    ><Trash2 class="size-4" /> Supprimer</Button
                >
            </header>

            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-4">
                <button v-if="task.parent" class="text-xs text-muted-foreground hover:underline" @click="emit('open', task.parent.id)">
                    ↑ {{ task.parent.title }}
                </button>
                <SheetTitle as-child>
                    <input
                        :key="`title-${task.id}`"
                        :value="task.title"
                        :disabled="!canEdit"
                        class="w-full bg-transparent text-xl font-semibold outline-none"
                        @change="save('title', val($event))"
                    />
                </SheetTitle>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <label>
                        <span class="mb-1 block text-xs text-muted-foreground">Responsable</span>
                        <select
                            :value="task.assignee_id ?? ''"
                            :disabled="!canEdit"
                            class="w-full rounded-md border bg-background px-2 py-1.5"
                            @change="save('assignee_id', val($event) ? Number(val($event)) : null)"
                        >
                            <option value="">Personne</option>
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-xs text-muted-foreground">Section</span>
                        <select
                            :value="task.section_id"
                            :disabled="!canEdit || !!task.parent_id"
                            class="w-full rounded-md border bg-background px-2 py-1.5"
                            @change="save('section_id', Number(val($event)))"
                        >
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-xs text-muted-foreground">Date de début</span>
                        <input
                            type="date"
                            :value="task.start_date ?? ''"
                            :disabled="!canEdit"
                            class="w-full rounded-md border bg-background px-2 py-1"
                            @change="save('start_date', val($event))"
                        />
                    </label>
                    <label>
                        <span class="mb-1 block text-xs text-muted-foreground">Date d'échéance</span>
                        <input
                            type="date"
                            :value="task.due_date ?? ''"
                            :disabled="!canEdit"
                            class="w-full rounded-md border bg-background px-2 py-1"
                            @change="save('due_date', val($event))"
                        />
                    </label>
                    <label>
                        <span class="mb-1 block text-xs text-muted-foreground">Priorité</span>
                        <select
                            :value="task.priority"
                            :disabled="!canEdit"
                            class="w-full rounded-md border bg-background px-2 py-1.5"
                            @change="save('priority', val($event))"
                        >
                            <option v-for="(p, key) in PRIORITIES" :key="key" :value="key">{{ p.label }}</option>
                        </select>
                    </label>
                </div>

                <div class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">Description</span>
                    <RichEditor
                        v-if="canEdit"
                        :key="`desc-${task.id}`"
                        :model-value="task.description"
                        :project-id="task.project_id"
                        :members="mentionable"
                        placeholder="Ajoutez des détails… Saisissez « / » pour afficher le menu"
                        min-height="5rem"
                        :onChange="descriptionSaver(task.id)"
                    />
                    <RichContent v-else-if="task.description" :html="task.description" />
                    <p v-else class="text-muted-foreground">Aucune description.</p>
                </div>

                <section v-if="!task.parent_id">
                    <h4 class="mb-2 text-sm font-semibold">Sous-tâches</h4>
                    <ul class="divide-y rounded-md border">
                        <li v-for="s in task.subtasks" :key="s.id" class="flex items-center gap-2 px-3 py-2 text-sm">
                            <TaskCheck :task-id="s.id" :completed="s.completed" :disabled="!canEdit" />
                            <button
                                class="flex-1 text-left hover:underline"
                                :class="{ 'text-muted-foreground line-through': s.completed }"
                                @click="emit('open', s.id)"
                            >
                                {{ s.title }}
                            </button>
                            <UserAvatar :user="s.assignee" />
                        </li>
                        <li v-if="canEdit" class="px-3 py-2">
                            <form @submit.prevent="addSubtask">
                                <input
                                    v-model="subtask.title"
                                    placeholder="+ Ajouter une sous-tâche"
                                    class="w-full bg-transparent text-sm outline-none"
                                />
                            </form>
                        </li>
                    </ul>
                </section>

                <section>
                    <h4 class="mb-2 text-sm font-semibold">Commentaires</h4>
                    <ul class="space-y-3">
                        <li v-for="c in task.comments" :key="c.id" class="flex gap-2">
                            <UserAvatar :user="c.user" />
                            <div class="flex-1 rounded-md bg-muted/60 px-3 py-2 text-sm">
                                <div class="mb-0.5 text-xs text-muted-foreground">
                                    <strong class="text-foreground">{{ c.user.name }}</strong> · {{ dateTime(c.created_at) }}
                                </div>
                                <RichContent :html="c.body" />
                            </div>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <RichEditor
                            ref="commentEditor"
                            :key="`comment-${task.id}`"
                            :model-value="comment.body"
                            :project-id="task.project_id"
                            :members="mentionable"
                            submittable
                            :busy="comment.processing"
                            placeholder="Écrire un commentaire… « / » pour le menu, « @ » pour mentionner"
                            @submit="addComment"
                        />
                        <p v-if="comment.errors.body" class="mt-1 text-xs text-rose-600">{{ comment.errors.body }}</p>
                    </div>
                </section>

                <section>
                    <h4 class="mb-2 text-sm font-semibold">Historique</h4>
                    <ul class="space-y-1 text-xs text-muted-foreground">
                        <li v-for="a in task.activities" :key="a.id">
                            {{ dateTime(a.created_at) }} · <strong v-if="a.user">{{ a.user.name }}</strong
                            ><span v-else>⚙️</span> {{ a.message }}
                        </li>
                    </ul>
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
