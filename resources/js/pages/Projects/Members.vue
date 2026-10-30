<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import { ROLES } from '@/lib/labels';
import type { Member, ProjectPageProps, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<ProjectPageProps & { roles: Member['role'][]; directory: { id: number; name: string; email: string }[] }>();
const me = usePage<SharedData>().props.auth.user;

const form = useForm({ email: '', name: '', role: 'editor' });
const submit = () => form.post(route('members.store', props.project.id), { preserveScroll: true, onSuccess: () => form.reset() });
const setRole = (m: Member, role: string) => router.patch(route('members.update', [props.project.id, m.id]), { role }, { preserveScroll: true });
const resend = (m: Member) => router.post(route('team.resend', m.id), {}, { preserveScroll: true });
const remove = (m: Member) =>
    confirm(`Retirer ${m.name} du projet ?`) && router.delete(route('members.destroy', [props.project.id, m.id]), { preserveScroll: true });
</script>

<template>
    <ProjectLayout :project="project" :members="members" title="Collaborateurs">
        <div class="mx-auto max-w-3xl space-y-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Collaborateurs</h2>
                <p class="text-sm text-muted-foreground">
                    Les administrateurs gèrent le projet, les éditeurs modifient les tâches et les règles, les lecteurs consultent et commentent.
                </p>
            </div>
            <ul class="divide-y rounded-lg border">
                <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-4 py-3">
                    <UserAvatar :user="m" size="md" />
                    <div class="flex-1">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <span>{{ m.name }}<span v-if="m.id === me.id" class="text-muted-foreground"> (vous)</span></span>
                            <span
                                v-if="m.pending"
                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                            >
                                Invitation en attente
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">{{ m.email }}</div>
                    </div>
                    <Button v-if="can.manage && m.pending" variant="ghost" size="sm" @click="resend(m)">Renvoyer l'invitation</Button>
                    <template v-if="can.manage && m.id !== me.id">
                        <select
                            :value="m.role"
                            class="rounded-md border bg-background px-2 py-1 text-sm"
                            @change="setRole(m, ($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="r in roles" :key="r" :value="r">{{ ROLES[r] }}</option>
                        </select>
                        <Button variant="ghost" size="sm" class="text-rose-600" @click="remove(m)">Retirer</Button>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">{{ ROLES[m.role] }}</span>
                </li>
            </ul>

            <form v-if="can.manage" class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_1fr_150px_auto] sm:items-end" @submit.prevent="submit">
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">E-mail</span>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        list="team-directory"
                        placeholder="collegue@flexilivre.com"
                        class="w-full rounded-md border bg-background px-3 py-1.5"
                    />
                    <datalist id="team-directory">
                        <option v-for="u in directory" :key="u.id" :value="u.email">{{ u.name }}</option>
                    </datalist>
                    <InputError :message="form.errors.email" />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">Nom (nouveau compte)</span>
                    <input v-model="form.name" placeholder="Prénom Nom" class="w-full rounded-md border bg-background px-3 py-1.5" />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">Rôle</span>
                    <select v-model="form.role" class="w-full rounded-md border bg-background px-2 py-1.5">
                        <option v-for="r in roles" :key="r" :value="r">{{ ROLES[r] }}</option>
                    </select>
                </label>
                <Button type="submit" :disabled="form.processing">Ajouter</Button>
                <p class="text-xs text-muted-foreground sm:col-span-4">
                    <template v-if="me.is_admin">
                        Choisissez un membre de l'équipe ou saisissez une nouvelle adresse : la personne reçoit alors une invitation pour créer son
                        compte et ajouter sa photo.
                    </template>
                    <template v-else
                        >Choisissez un membre de l'équipe. Pour une nouvelle personne, demandez à un administrateur de l'inviter.</template
                    >
                </p>
            </form>
        </div>
    </ProjectLayout>
</template>
