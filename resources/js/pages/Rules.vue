<script setup lang="ts">
import ToggleSwitch from '@/components/design/ToggleSwitch.vue';
import InputError from '@/components/InputError.vue';
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { ACTIONS, PRIORITIES, TRIGGERS } from '@/lib/labels';
import type { Rule, RuleAction } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, CircleCheck, Plus, Trash2, UserRound, X, Zap } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

/**
 * Règles globales : elles s'appliquent à tous les projets et à tous les collaborateurs.
 * Les sections y sont désignées par leur nom (« Terminé »…), retrouvé dans chaque projet.
 */
const props = defineProps<{
    rules: Rule[];
    projectRules: Rule[];
    sectionLabels: Record<number, string>;
    sectionNames: string[];
    users: { id: number; name: string }[];
    triggers: string[];
    actions: string[];
    canManage: boolean;
}>();

const form = useForm<{ name: string; trigger: string; trigger_section_name: string; actions: RuleAction[] }>({
    name: '',
    trigger: 'task_moved',
    trigger_section_name: '',
    actions: [{ type: 'set_completed' }],
});
const creating = ref(false);
const formCard = ref<HTMLElement>();
const submit = () =>
    form.post(route('rules.global.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
async function startCreating() {
    creating.value = true;
    await nextTick();
    formCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    formCard.value?.querySelector('input')?.focus();
}

/** Modèles suggérés : préremplissent le formulaire avec une règle courante. */
const TEMPLATES = computed(() => [
    {
        icon: UserRound,
        tone: 'bg-info-tint text-info-active',
        title: 'Assigner les nouvelles tâches',
        text: 'Chaque tâche créée reçoit un responsable par défaut.',
        apply: () =>
            Object.assign(form, {
                name: 'Assigner les nouvelles tâches',
                trigger: 'task_created',
                trigger_section_name: '',
                actions: [{ type: 'assign_to', user_id: props.users[0]?.id }],
            }),
    },
    {
        icon: CircleCheck,
        tone: 'bg-success-tint text-success-text',
        title: 'Terminer en arrivant dans « Terminé »',
        text: 'Dans chaque projet, une tâche déplacée dans la section « Terminé » est marquée comme terminée.',
        apply: () =>
            Object.assign(form, {
                name: 'Terminer en fin de parcours',
                trigger: 'task_moved',
                trigger_section_name: 'Terminé',
                actions: [{ type: 'set_completed' }],
            }),
    },
    {
        icon: CalendarClock,
        tone: 'bg-warning-tint text-warning-text',
        title: "Prévenir quand l'échéance change",
        text: "Le responsable est notifié dès que la date d'échéance de sa tâche est modifiée.",
        apply: () =>
            Object.assign(form, {
                name: "Prévenir quand l'échéance change",
                trigger: 'due_date_changed',
                trigger_section_name: '',
                actions: [{ type: 'notify_assignee' }],
            }),
    },
]);
function useTemplate(t: { apply: () => void }) {
    t.apply();
    startCreating();
}

const userName = (id?: number) => props.users.find((u) => u.id === id)?.name ?? '?';

function describe(a: RuleAction) {
    let text = ACTIONS[a.type] ?? a.type;
    if (a.section_name) text += ` « ${a.section_name} »`;
    else if (a.section_id) text += ` « ${props.sectionLabels[a.section_id] ?? '?'} »`;
    if (a.user_id) text += ` ${userName(a.user_id)}`;
    if (a.priority) text += ` ${PRIORITIES[a.priority].label.toLowerCase()}`;
    if (a.days !== undefined) text = text.replace('+ n', `+ ${a.days}`);
    if (a.text) text += ` : « ${a.text} »`;
    return text;
}
const toggle = (rule: Rule, enabled: boolean) => router.patch(route('rules.update', rule.id), { enabled }, { preserveScroll: true });
const field = 'h-11 w-full rounded-field border-2 border-app-border-strong bg-background px-3 text-sm font-semibold outline-none focus:border-info';
const activeCount = computed(() => props.rules.filter((r) => r.enabled).length);
const whenLabel = (rule: Rule) => {
    const section = rule.trigger_section_name ?? rule.trigger_section?.name;
    return TRIGGERS[rule.trigger] + (section ? ` « ${section} »` : '');
};
/** Une règle de projet se modifie par un administrateur ou un éditeur du projet (le serveur vérifie). */
const canChange = (rule: Rule) => props.canManage || rule.project_id !== null;
const destroy = (rule: Rule) =>
    confirm(`Supprimer la règle « ${rule.name} » ?`) && router.delete(route('rules.destroy', rule.id), { preserveScroll: true });
</script>

<template>
    <Head title="Règles" />
    <AppLayout :breadcrumbs="[{ title: 'Règles', href: route('rules.index') }]">
        <div class="mx-auto w-full max-w-[920px] space-y-8 px-4 py-6 md:px-8 md:py-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[28px] font-bold leading-tight">Règles</h2>
                    <p class="mt-1 text-[15px] text-ink-soft">
                        Automatisez les gestes répétitifs : quand quelque chose se passe, Flexi Projets s'occupe du reste. Ces règles s'appliquent à
                        tous les projets et à tous les collaborateurs.
                    </p>
                    <p v-if="!canManage" class="mt-2 text-[13px] font-semibold text-ink-soft">Seuls les administrateurs peuvent les modifier.</p>
                </div>
                <Button v-if="canManage" class="h-11" @click="startCreating"><Plus /> Créer une règle</Button>
            </div>

            <section aria-labelledby="rules-title">
                <h3 id="rules-title" class="mb-3 text-[15px] font-bold">
                    Règles globales <span class="font-semibold text-ink-soft">· {{ activeCount }} active(s) sur {{ rules.length }}</span>
                </h3>
                <ul class="space-y-3">
                    <li
                        v-for="rule in rules"
                        :key="rule.id"
                        class="rounded-card border border-app-border bg-background p-4 shadow-soft transition-opacity md:p-5"
                        :class="{ 'opacity-70': !rule.enabled }"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-icon bg-brand-tint text-brand-active"
                                ><Zap class="size-5"
                            /></span>
                            <span class="min-w-0 flex-1 text-base font-bold">{{ rule.name }}</span>
                            <template v-if="canManage">
                                <button
                                    class="flex size-10 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-brand-active"
                                    :aria-label="`Supprimer la règle ${rule.name}`"
                                    @click="destroy(rule)"
                                >
                                    <Trash2 class="size-[18px]" />
                                </button>
                                <ToggleSwitch
                                    :model-value="rule.enabled"
                                    :label="`Activer la règle ${rule.name}`"
                                    @update:model-value="(v) => toggle(rule, v)"
                                />
                            </template>
                            <span v-else class="text-xs font-bold text-ink-soft">{{ rule.enabled ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 md:pl-[52px]">
                            <span class="text-xs font-bold tracking-[.04em] text-ink-soft">QUAND</span>
                            <span class="rounded-full bg-info-tint px-3 py-1 text-[13px] font-bold text-info-deep">{{ whenLabel(rule) }}</span>
                            <ArrowRight class="size-4 text-ink-soft" aria-hidden="true" />
                            <span class="text-xs font-bold tracking-[.04em] text-ink-soft">ALORS</span>
                            <span
                                v-for="(a, i) in rule.actions"
                                :key="i"
                                class="rounded-full bg-brand-tint px-3 py-1 text-[13px] font-bold text-brand-active"
                                >{{ describe(a) }}</span
                            >
                        </div>
                    </li>
                    <li
                        v-if="!rules.length"
                        class="rounded-card border-2 border-dashed border-app-border-strong p-8 text-center text-sm font-semibold text-ink-soft"
                    >
                        Aucune règle globale pour l'instant.<template v-if="canManage"> Partez d'un modèle ci-dessous ou créez la vôtre.</template>
                    </li>
                </ul>
            </section>

            <div v-if="canManage && creating" ref="formCard">
                <form class="space-y-4 rounded-card border border-app-border p-5 shadow-soft" @submit.prevent="submit">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold">Nouvelle règle</h3>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-icon hover:bg-app-hover"
                            aria-label="Annuler"
                            @click="creating = false"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-bold">Nom de la règle</span>
                        <input v-model="form.name" required :class="field" placeholder="Ex. Prévenir Kamel quand une maquette est à valider" />
                        <InputError :message="form.errors.name" />
                    </label>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="bg-info-tint/60 space-y-2 rounded-2xl p-4">
                            <span class="text-xs font-bold tracking-[.04em] text-info-deep">QUAND…</span>
                            <select v-model="form.trigger" aria-label="Déclencheur" :class="field">
                                <option v-for="t in triggers" :key="t" :value="t">{{ TRIGGERS[t] }}</option>
                            </select>
                            <template v-if="form.trigger === 'task_moved'">
                                <input
                                    v-model="form.trigger_section_name"
                                    list="section-names"
                                    aria-label="Section"
                                    placeholder="… dans n'importe quelle section"
                                    :class="field"
                                />
                                <p class="text-xs text-info-deep">Nom de la section, par ex. « Terminé ». Vide : n'importe quelle section.</p>
                            </template>
                        </div>
                        <div class="space-y-3 rounded-2xl bg-brand-wash p-4">
                            <span class="text-xs font-bold tracking-[.04em] text-brand-active">ALORS…</span>
                            <div v-for="(action, i) in form.actions" :key="i" class="space-y-2">
                                <div class="flex gap-2">
                                    <select v-model="action.type" aria-label="Action" :class="field">
                                        <option v-for="a in actions" :key="a" :value="a">{{ ACTIONS[a] }}</option>
                                    </select>
                                    <button
                                        v-if="form.actions.length > 1"
                                        type="button"
                                        class="flex size-11 shrink-0 items-center justify-center rounded-icon hover:bg-background"
                                        aria-label="Retirer l'action"
                                        @click="form.actions.splice(i, 1)"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                                <input
                                    v-if="action.type === 'move_to_section'"
                                    v-model="action.section_name"
                                    required
                                    list="section-names"
                                    aria-label="Section"
                                    placeholder="Nom de la section"
                                    :class="field"
                                />
                                <select
                                    v-if="action.type === 'assign_to' || action.type === 'notify_user'"
                                    v-model="action.user_id"
                                    required
                                    aria-label="Personne"
                                    :class="field"
                                >
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                                <select v-if="action.type === 'set_priority'" v-model="action.priority" required aria-label="Priorité" :class="field">
                                    <option v-for="(p, key) in PRIORITIES" :key="key" :value="key">{{ p.label }}</option>
                                </select>
                                <input
                                    v-if="action.type === 'set_due_in_days'"
                                    v-model.number="action.days"
                                    type="number"
                                    min="0"
                                    required
                                    placeholder="Nombre de jours"
                                    :class="field"
                                />
                                <input
                                    v-if="['add_comment', 'notify_assignee', 'notify_user'].includes(action.type)"
                                    v-model="action.text"
                                    :required="action.type === 'add_comment'"
                                    :placeholder="action.type === 'add_comment' ? 'Texte du commentaire' : 'Message (facultatif)'"
                                    :class="field"
                                />
                            </div>
                            <button
                                type="button"
                                class="flex items-center gap-1 text-sm font-bold text-brand-active hover:underline"
                                @click="form.actions.push({ type: 'notify_assignee' })"
                            >
                                <Plus class="size-4" /> Ajouter une action
                            </button>
                        </div>
                    </div>
                    <datalist id="section-names">
                        <option v-for="n in sectionNames" :key="n" :value="n" />
                    </datalist>
                    <p v-if="form.actions.some((a) => a.type === 'assign_to')" class="text-xs text-ink-soft">
                        « Assigner à » ne s'applique que dans les projets dont la personne est membre.
                    </p>
                    <InputError v-for="(msg, key) in form.errors" :key="key" :message="key !== 'name' ? msg : undefined" />
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="creating = false">Annuler</Button>
                        <Button type="submit" :disabled="form.processing">Créer la règle</Button>
                    </div>
                </form>
            </div>

            <section v-if="projectRules.length" aria-labelledby="project-rules-title">
                <h3 id="project-rules-title" class="mb-1 text-[15px] font-bold">Règles propres à un projet</h3>
                <p class="mb-3 text-[13px] text-ink-soft">Créées avant les règles globales, elles restent actives dans leur projet.</p>
                <ul class="space-y-3">
                    <li
                        v-for="rule in projectRules"
                        :key="rule.id"
                        class="rounded-card border border-app-border bg-background p-4 shadow-soft transition-opacity md:p-5"
                        :class="{ 'opacity-70': !rule.enabled }"
                    >
                        <div class="flex items-center gap-3">
                            <ProjectAvatar v-if="rule.project" :project="rule.project" size="lg" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-base font-bold">{{ rule.name }}</span>
                                <span class="block text-xs font-semibold text-ink-soft">{{ rule.project?.name }}</span>
                            </span>
                            <template v-if="canChange(rule)">
                                <button
                                    class="flex size-10 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-brand-active"
                                    :aria-label="`Supprimer la règle ${rule.name}`"
                                    @click="destroy(rule)"
                                >
                                    <Trash2 class="size-[18px]" />
                                </button>
                                <ToggleSwitch
                                    :model-value="rule.enabled"
                                    :label="`Activer la règle ${rule.name}`"
                                    @update:model-value="(v) => toggle(rule, v)"
                                />
                            </template>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 md:pl-[56px]">
                            <span class="text-xs font-bold tracking-[.04em] text-ink-soft">QUAND</span>
                            <span class="rounded-full bg-info-tint px-3 py-1 text-[13px] font-bold text-info-deep">{{ whenLabel(rule) }}</span>
                            <ArrowRight class="size-4 text-ink-soft" aria-hidden="true" />
                            <span class="text-xs font-bold tracking-[.04em] text-ink-soft">ALORS</span>
                            <span
                                v-for="(a, i) in rule.actions"
                                :key="i"
                                class="rounded-full bg-brand-tint px-3 py-1 text-[13px] font-bold text-brand-active"
                                >{{ describe(a) }}</span
                            >
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="canManage" aria-labelledby="templates-title">
                <h3 id="templates-title" class="mb-3 text-[15px] font-bold">Modèles suggérés</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <button
                        v-for="t in TEMPLATES"
                        :key="t.title"
                        type="button"
                        class="rounded-card border-2 border-app-border p-5 text-left transition hover:border-app-border-strong hover:shadow-hover"
                        @click="useTemplate(t)"
                    >
                        <span class="mb-3 flex size-9 items-center justify-center rounded-icon" :class="t.tone"
                            ><component :is="t.icon" class="size-[18px]"
                        /></span>
                        <span class="block text-[15px] font-bold">{{ t.title }}</span>
                        <span class="mt-1 block text-[13px] leading-5 text-ink-soft">{{ t.text }}</span>
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
