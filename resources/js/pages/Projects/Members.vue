<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import { ROLES } from '@/lib/labels';
import type { Member, ProjectPageProps, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<ProjectPageProps & { roles: Member['role'][]; directory: { id: number; name: string; email: string }[] }>();
const me = usePage<SharedData>().props.auth.user;

const form = useForm({ email: '', name: '', role: 'editor' });
const submit = () => form.post(route('members.store', props.project.id), { preserveScroll: true, onSuccess: () => form.reset() });
const setRole = (m: Member, role: string) => router.patch(route('members.update', [props.project.id, m.id]), { role }, { preserveScroll: true });
const resend = (m: Member) => router.post(route('team.resend', m.id), {}, { preserveScroll: true });
const remove = (m: Member) =>
    confirm(`Retirer ${m.name} du projet ?`) && router.delete(route('members.destroy', [props.project.id, m.id]), { preserveScroll: true });

// Le nom n'est demandé que pour une adresse qui n'a pas encore de compte (invitation par un administrateur).
const isNewPerson = computed(() => me.is_admin && !!form.email && !props.directory.some((u) => u.email === form.email.trim().toLowerCase()));
const others = computed(() => props.members.filter((m) => m.id !== me.id));

const ROLE_HELP: Record<Member['role'], string> = {
    admin: 'Tout, y compris les paramètres, les collaborateurs et la suppression du projet.',
    editor: 'Crée, modifie et déplace les tâches, les sections et les règles.',
    viewer: 'Consulte le projet, commente et suit les tâches sans les modifier.',
};
const field =
    'h-11 w-full rounded-field border-2 border-app-border-strong bg-background px-3.5 text-sm font-semibold outline-none focus:border-info focus:ring-4 focus:ring-info-tint';
</script>

<template>
    <ProjectLayout :project="project" :members="members" title="Collaborateurs" :can-edit="can.edit">
        <div class="mx-auto max-w-[920px] space-y-8 px-4 py-6 md:px-8 md:py-8">
            <div>
                <h2 class="text-[28px] font-bold leading-tight">Collaborateurs</h2>
                <p class="mt-1 text-[15px] text-ink-soft">Invitez votre équipe pour assigner des tâches, commenter et suivre l'avancement.</p>
            </div>

            <form v-if="can.manage" class="rounded-card border border-app-border p-5 shadow-soft" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_170px_auto] sm:items-end">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-bold">Adresse e-mail</span>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            list="team-directory"
                            placeholder="collegue@flexilivre.com"
                            :class="field"
                        />
                        <datalist id="team-directory">
                            <option v-for="u in directory" :key="u.id" :value="u.email">{{ u.name }}</option>
                        </datalist>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-bold">Rôle</span>
                        <select v-model="form.role" :class="field">
                            <option v-for="r in roles" :key="r" :value="r">{{ ROLES[r] }}</option>
                        </select>
                    </label>
                    <Button type="submit" class="h-11" :disabled="form.processing"><UserPlus /> Inviter</Button>
                </div>
                <label v-if="isNewPerson" class="mt-3 block">
                    <span class="mb-1.5 block text-sm font-bold"
                        >Nom de la personne <span class="font-medium text-ink-soft">(nouveau compte)</span></span
                    >
                    <input v-model="form.name" placeholder="Prénom Nom" :class="field" />
                </label>
                <InputError :message="form.errors.email" class="mt-2" />
                <p class="mt-3 text-xs text-ink-soft">
                    <template v-if="me.is_admin">
                        Choisissez un membre de l'équipe ou saisissez une nouvelle adresse : la personne reçoit alors une invitation pour créer son
                        compte et ajouter sa photo.
                    </template>
                    <template v-else
                        >Choisissez un membre de l'équipe. Pour une nouvelle personne, demandez à un administrateur de l'inviter.</template
                    >
                </p>
            </form>

            <section aria-labelledby="members-title">
                <h3 id="members-title" class="mb-3 text-[15px] font-bold">
                    Membres du projet <span class="font-semibold text-ink-soft">· {{ members.length }}</span>
                </h3>
                <ul class="divide-y divide-app-border overflow-hidden rounded-card border border-app-border">
                    <li v-for="m in members" :key="m.id" class="flex flex-wrap items-center gap-3 px-4 py-3.5 md:px-5">
                        <UserAvatar :user="m" size="lg" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-[15px] font-bold">
                                <span class="truncate">{{ m.name }}</span>
                                <span v-if="m.id === me.id" class="rounded-full bg-info-tint px-2 py-0.5 text-xs text-info-deep">Vous</span>
                                <span v-if="m.pending" class="rounded-full bg-warning-tint px-2 py-0.5 text-xs text-warning-text"
                                    >Invitation en attente</span
                                >
                            </div>
                            <div class="truncate text-[13px] text-ink-soft">{{ m.email }}</div>
                        </div>
                        <Button v-if="can.manage && m.pending" variant="ghost" size="sm" @click="resend(m)">Renvoyer l'invitation</Button>
                        <template v-if="can.manage && m.id !== me.id">
                            <select
                                :value="m.role"
                                :aria-label="`Rôle de ${m.name}`"
                                class="h-9 rounded-full border-2 border-app-border-strong bg-background px-3 text-sm font-bold"
                                @change="setRole(m, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="r in roles" :key="r" :value="r">{{ ROLES[r] }}</option>
                            </select>
                            <Button variant="ghost" size="sm" class="text-brand-active" @click="remove(m)">Retirer</Button>
                        </template>
                        <span v-else class="rounded-full bg-app-hover px-3 py-1 text-xs font-bold text-app-strong">{{ ROLES[m.role] }}</span>
                    </li>
                    <li v-if="!others.length" class="px-5 py-6 text-center text-sm font-semibold text-ink-soft">
                        Personne d'autre pour l'instant. Les invitations en attente apparaîtront ici.
                    </li>
                </ul>
            </section>

            <section aria-labelledby="roles-title">
                <h3 id="roles-title" class="mb-3 text-[15px] font-bold">Ce que chaque rôle peut faire</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="r in roles" :key="r" class="rounded-card bg-app-surface p-5">
                        <div class="text-[15px] font-bold">{{ ROLES[r] }}</div>
                        <p class="mt-1 text-[13px] leading-5 text-ink-soft">{{ ROLE_HELP[r] }}</p>
                    </div>
                </div>
            </section>
        </div>
    </ProjectLayout>
</template>
