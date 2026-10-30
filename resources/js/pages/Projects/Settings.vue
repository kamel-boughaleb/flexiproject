<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import { dateTime, sectionColor } from '@/lib/labels';
import type { Activity, ProjectPageProps, Section } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<ProjectPageProps & { colors: string[]; sections: (Section & { tasks_count: number })[]; activities: Activity[] }>();

const form = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    color: props.project.color,
    start_date: props.project.start_date?.slice(0, 10) ?? '',
    end_date: props.project.end_date?.slice(0, 10) ?? '',
});
const submit = () => form.patch(route('projects.update', props.project.id), { preserveScroll: true, onSuccess: () => form.defaults() });
const destroy = () =>
    confirm(`Supprimer définitivement « ${props.project.name} » et toutes ses tâches ?`) &&
    router.delete(route('projects.destroy', props.project.id));

// Sections : renommer, supprimer, ajouter (les colonnes du Kanban).
const renaming = ref<number | null>(null);
const vFocus = { mounted: (el: HTMLElement) => el.focus() };
function rename(section: Section, name: string) {
    renaming.value = null;
    if (name.trim() && name !== section.name) router.patch(route('sections.update', section.id), { name }, { preserveScroll: true });
}
const removeSection = (section: Section & { tasks_count: number }) =>
    confirm(`Supprimer la section « ${section.name} »${section.tasks_count ? ` et ses ${section.tasks_count} tâche(s)` : ''} ?`) &&
    router.delete(route('sections.destroy', section.id), { preserveScroll: true });
const newSection = useForm({ name: '' });
const addSection = () => newSection.post(route('sections.store', props.project.id), { preserveScroll: true, onSuccess: () => newSection.reset() });

const field =
    'h-11 w-full rounded-field border-2 border-app-border-strong bg-background px-3.5 text-sm font-semibold outline-none focus:border-info focus:ring-4 focus:ring-info-tint disabled:bg-app-surface';
const card = 'grid gap-5 rounded-card border border-app-border p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] md:p-6';
</script>

<template>
    <ProjectLayout :project="project" :members="members" title="Paramètres" :can-edit="can.edit">
        <form class="mx-auto max-w-[920px] space-y-5 px-4 py-6 md:px-8 md:py-8" @submit.prevent="submit">
            <section :class="card" aria-labelledby="general-title">
                <div>
                    <h2 id="general-title" class="text-lg font-bold">Général</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">Nom, couleur et dates du projet.</p>
                </div>
                <fieldset :disabled="!can.manage" class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-bold">Nom du projet</span>
                        <input v-model="form.name" required :class="field" />
                        <InputError :message="form.errors.name" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-bold">Description</span>
                        <textarea v-model="form.description" rows="3" placeholder="À quoi sert ce projet ?" :class="[field, 'h-auto py-2.5']" />
                    </label>
                    <div>
                        <span class="mb-1.5 block text-sm font-bold">Couleur</span>
                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Couleur du projet">
                            <button
                                v-for="c in colors"
                                :key="c"
                                type="button"
                                role="radio"
                                :aria-checked="form.color === c"
                                :aria-label="`Couleur ${c}`"
                                class="flex size-8 items-center justify-center rounded-icon text-white ring-offset-2 ring-offset-background"
                                :class="{ 'ring-2 ring-ink': form.color === c }"
                                :style="{ backgroundColor: c }"
                                @click="form.color = c"
                            >
                                <Check v-if="form.color === c" class="size-4" :stroke-width="3" />
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-bold">Date de début</span>
                            <input v-model="form.start_date" type="date" :class="field" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-bold">Date de fin</span>
                            <input v-model="form.end_date" type="date" :class="field" />
                            <InputError :message="form.errors.end_date" />
                        </label>
                    </div>
                </fieldset>
            </section>

            <section :class="card" aria-labelledby="sections-title">
                <div>
                    <h2 id="sections-title" class="text-lg font-bold">Sections</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">
                        Les colonnes du Kanban, dans l'ordre du projet. Double-cliquez sur un nom pour le modifier.
                    </p>
                </div>
                <div class="space-y-2">
                    <div v-for="(s, i) in sections" :key="s.id" class="flex min-h-12 items-center gap-3 rounded-2xl border border-app-border px-4">
                        <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: sectionColor(i) }" />
                        <input
                            v-if="renaming === s.id"
                            v-focus
                            :value="s.name"
                            aria-label="Nom de la section"
                            class="h-9 flex-1 rounded-field border-2 border-info px-2 text-[15px] font-bold outline-none"
                            @keydown.enter.prevent="rename(s, ($event.target as HTMLInputElement).value)"
                            @blur="rename(s, ($event.target as HTMLInputElement).value)"
                        />
                        <span v-else class="flex-1 truncate text-[15px] font-bold" @dblclick="can.edit && (renaming = s.id)">{{ s.name }}</span>
                        <span class="text-xs font-bold text-ink-soft">{{ s.tasks_count }} tâche{{ s.tasks_count > 1 ? 's' : '' }}</span>
                        <template v-if="can.edit">
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover"
                                :aria-label="`Renommer ${s.name}`"
                                @click="renaming = s.id"
                            >
                                <Pencil class="size-4" />
                            </button>
                            <button
                                v-if="sections.length > 1"
                                type="button"
                                class="flex size-9 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-brand-active"
                                :aria-label="`Supprimer ${s.name}`"
                                @click="removeSection(s)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </template>
                    </div>
                    <input
                        v-if="can.edit"
                        v-model="newSection.name"
                        aria-label="Nouvelle section"
                        placeholder="+ Ajouter une section"
                        class="h-12 w-full rounded-2xl border-2 border-dashed border-app-border-strong bg-transparent text-center text-sm font-bold outline-none placeholder:text-ink-soft focus:border-info focus:px-4 focus:text-left"
                        @keydown.enter.prevent="addSection"
                    />
                </div>
            </section>

            <section :class="card" aria-labelledby="notif-title">
                <div>
                    <h2 id="notif-title" class="text-lg font-bold">Notifications</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">Ce que vous recevez par e-mail.</p>
                </div>
                <p class="self-center text-sm text-app-strong">
                    Vos e-mails (assignation, commentaires, mentions, récapitulatif quotidien) se règlent dans
                    <Link :href="route('notifications.settings')" class="font-bold text-info-active underline underline-offset-2"
                        >vos réglages de notifications</Link
                    >.
                </p>
            </section>

            <section :class="card" aria-labelledby="activity-title">
                <div>
                    <h2 id="activity-title" class="text-lg font-bold">Activité récente</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">Les dernières modifications du projet.</p>
                </div>
                <ul class="max-h-72 space-y-1.5 overflow-y-auto text-[13px] text-ink-soft">
                    <li v-for="a in activities" :key="a.id">
                        <strong class="text-app-strong">{{ a.user?.name ?? 'Règle automatique' }}</strong> {{ a.message }} ·
                        {{ dateTime(a.created_at) }}
                    </li>
                </ul>
            </section>

            <section
                v-if="can.manage"
                class="grid gap-5 rounded-card border border-brand-disabled bg-brand-wash p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] md:p-6"
                aria-labelledby="danger-title"
            >
                <div>
                    <h2 id="danger-title" class="text-lg font-bold text-brand-active">Zone sensible</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">Supprimer le projet efface définitivement ses tâches, règles et commentaires.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Button type="button" variant="destructive" class="h-11" @click="destroy">Supprimer le projet</Button>
                </div>
            </section>

            <div
                v-if="can.manage"
                class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-app-border bg-background/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8"
            >
                <span v-if="form.recentlySuccessful" role="status" class="mr-auto text-sm font-bold text-success-text">Enregistré.</span>
                <Button type="button" variant="outline" class="h-12 px-7" :disabled="!form.isDirty" @click="form.reset()">Annuler</Button>
                <Button type="submit" size="lg" :disabled="form.processing || !form.isDirty">Enregistrer</Button>
            </div>
        </form>
    </ProjectLayout>
</template>
