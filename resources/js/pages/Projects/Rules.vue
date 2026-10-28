<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import { ACTIONS, PRIORITIES, TRIGGERS } from '@/lib/labels';
import type { ProjectPageProps, Rule, RuleAction, Section } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';

const props = defineProps<ProjectPageProps & { sections: Section[]; rules: Rule[]; triggers: string[]; actions: string[] }>();

const form = useForm<{ name: string; trigger: string; trigger_section_id: number | null; actions: RuleAction[] }>({
    name: '',
    trigger: 'task_moved',
    trigger_section_id: null,
    actions: [{ type: 'set_completed' }],
});
const submit = () => form.post(route('rules.store', props.project.id), { preserveScroll: true, onSuccess: () => form.reset() });

const sectionName = (id?: number) => props.sections.find((s) => s.id === id)?.name ?? '?';
const memberName = (id?: number) => props.members.find((m) => m.id === id)?.name ?? '?';

function describe(a: RuleAction) {
    let text = ACTIONS[a.type]?.toLowerCase() ?? a.type;
    if (a.section_id) text += ` « ${sectionName(a.section_id)} »`;
    if (a.user_id) text += ` ${memberName(a.user_id)}`;
    if (a.priority) text += ` ${PRIORITIES[a.priority].label.toLowerCase()}`;
    if (a.days !== undefined) text = text.replace('+ n', `+ ${a.days}`);
    if (a.text) text += ` : « ${a.text} »`;
    return text;
}
const toggle = (rule: Rule) => router.patch(route('rules.update', rule.id), { enabled: !rule.enabled }, { preserveScroll: true });
const destroy = (rule: Rule) =>
    confirm(`Supprimer la règle « ${rule.name} » ?`) && router.delete(route('rules.destroy', rule.id), { preserveScroll: true });
</script>

<template>
    <ProjectLayout :project="project" :members="members" title="Règles">
        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Règles d'automatisation</h2>
                <p class="text-sm text-muted-foreground">Automatisez les tâches répétitives : « quand il se passe ceci, alors fais cela ».</p>
            </div>

            <ul class="space-y-2">
                <li
                    v-for="rule in rules"
                    :key="rule.id"
                    class="flex items-center gap-4 rounded-lg border p-4"
                    :class="{ 'opacity-60': !rule.enabled }"
                >
                    <div class="flex-1">
                        <div class="font-medium">{{ rule.name }}</div>
                        <div class="mt-1 text-sm text-muted-foreground">
                            <span class="font-medium text-sky-600">Quand</span> {{ TRIGGERS[rule.trigger]?.toLowerCase() }}
                            <template v-if="rule.trigger_section"> « {{ rule.trigger_section.name }} »</template>
                            <template v-for="(a, i) in rule.actions" :key="i"
                                >, <span class="font-medium text-emerald-600">alors</span> {{ describe(a) }}</template
                            >
                        </div>
                    </div>
                    <template v-if="can.edit">
                        <Button variant="ghost" size="sm" @click="toggle(rule)">{{ rule.enabled ? 'Désactiver' : 'Activer' }}</Button>
                        <Button variant="ghost" size="sm" class="text-rose-600" @click="destroy(rule)">Supprimer</Button>
                    </template>
                </li>
                <li v-if="!rules.length" class="rounded-lg border p-6 text-center text-sm text-muted-foreground">Aucune règle pour l'instant.</li>
            </ul>

            <form v-if="can.edit" class="space-y-4 rounded-lg border p-4" @submit.prevent="submit">
                <h3 class="font-semibold">Nouvelle règle</h3>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">Nom de la règle</span>
                    <input
                        v-model="form.name"
                        required
                        class="w-full rounded-md border bg-background px-3 py-1.5"
                        placeholder="Ex. Prévenir Kamel quand une maquette est à valider"
                    />
                    <InputError :message="form.errors.name" />
                </label>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 rounded-md bg-sky-50 p-3 dark:bg-sky-950/40">
                        <span class="text-xs font-semibold uppercase text-sky-700 dark:text-sky-300">Quand…</span>
                        <select v-model="form.trigger" class="w-full rounded-md border bg-background px-2 py-1.5 text-sm">
                            <option v-for="t in triggers" :key="t" :value="t">{{ TRIGGERS[t] }}</option>
                        </select>
                        <select
                            v-if="form.trigger === 'task_moved'"
                            v-model="form.trigger_section_id"
                            class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                        >
                            <option :value="null">… dans n'importe quelle section</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">… dans « {{ s.name }} »</option>
                        </select>
                    </div>
                    <div class="space-y-3 rounded-md bg-emerald-50 p-3 dark:bg-emerald-950/40">
                        <span class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-300">Alors…</span>
                        <div v-for="(action, i) in form.actions" :key="i" class="space-y-2">
                            <div class="flex gap-2">
                                <select v-model="action.type" class="w-full rounded-md border bg-background px-2 py-1.5 text-sm">
                                    <option v-for="a in actions" :key="a" :value="a">{{ ACTIONS[a] }}</option>
                                </select>
                                <button v-if="form.actions.length > 1" type="button" aria-label="Retirer l'action" @click="form.actions.splice(i, 1)">
                                    <X class="size-4" />
                                </button>
                            </div>
                            <select
                                v-if="action.type === 'move_to_section'"
                                v-model="action.section_id"
                                required
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                            >
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <select
                                v-if="action.type === 'assign_to' || action.type === 'notify_user'"
                                v-model="action.user_id"
                                required
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                            >
                                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <select
                                v-if="action.type === 'set_priority'"
                                v-model="action.priority"
                                required
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                            >
                                <option v-for="(p, key) in PRIORITIES" :key="key" :value="key">{{ p.label }}</option>
                            </select>
                            <input
                                v-if="action.type === 'set_due_in_days'"
                                v-model.number="action.days"
                                type="number"
                                min="0"
                                required
                                placeholder="Nombre de jours"
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                            />
                            <input
                                v-if="['add_comment', 'notify_assignee', 'notify_user'].includes(action.type)"
                                v-model="action.text"
                                :required="action.type === 'add_comment'"
                                :placeholder="action.type === 'add_comment' ? 'Texte du commentaire' : 'Message (facultatif)'"
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                            />
                        </div>
                        <button
                            type="button"
                            class="flex items-center gap-1 text-xs text-emerald-700 hover:underline dark:text-emerald-300"
                            @click="form.actions.push({ type: 'notify_assignee' })"
                        >
                            <Plus class="size-3" /> Ajouter une action
                        </button>
                    </div>
                </div>
                <InputError v-for="(msg, key) in form.errors" :key="key" :message="key !== 'name' ? msg : undefined" />
                <Button type="submit" :disabled="form.processing">Créer la règle</Button>
            </form>
        </div>
    </ProjectLayout>
</template>
