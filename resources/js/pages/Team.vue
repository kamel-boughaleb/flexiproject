<script setup lang="ts">
import InviteDialog from '@/components/InviteDialog.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MiniUser, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type TeamUser = MiniUser & { email: string; projects_count: number; pending: boolean; is_admin: boolean; invited_at: string | null };
const props = defineProps<{ users: TeamUser[] }>();
const me = usePage<SharedData>().props.auth.user;

const resend = (u: TeamUser) => router.post(route('team.resend', u.id), {}, { preserveScroll: true });
const cancel = (u: TeamUser) =>
    confirm(`Annuler l'invitation de ${u.email} ?`) && router.delete(route('team.destroy', u.id), { preserveScroll: true });

const setAdmin = (u: TeamUser, is_admin: boolean) => router.patch(route('team.admin', u.id), { is_admin }, { preserveScroll: true });

const pendingCount = computed(() => props.users.filter((u) => u.pending).length);
</script>

<template>
    <Head title="Équipe" />
    <AppLayout :breadcrumbs="[{ title: 'Équipe', href: route('team.index') }]">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-semibold">Équipe</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ users.length - pendingCount }} collaborateur(s)<template v-if="pendingCount"
                            >, {{ pendingCount }} invitation(s) en attente</template
                        >. Ajoutez ensuite chaque personne aux projets concernés depuis l'onglet « Collaborateurs » du projet.
                    </p>
                </div>
                <InviteDialog v-if="me.is_admin" />
            </div>

            <ul class="divide-y rounded-lg border">
                <li v-for="u in users" :key="u.id" class="flex items-center gap-3 px-4 py-3">
                    <UserAvatar :user="u" size="lg" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <span class="truncate">{{ u.name }}</span>
                            <span
                                v-if="u.is_admin"
                                class="rounded bg-orange-100 px-1.5 py-0.5 text-[11px] font-medium text-orange-700 dark:bg-orange-950 dark:text-orange-300"
                            >
                                Administrateur
                            </span>
                            <span
                                v-if="u.pending"
                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                            >
                                Invitation en attente
                            </span>
                        </div>
                        <div class="truncate text-xs text-muted-foreground">{{ u.email }} · {{ u.projects_count }} projet(s)</div>
                    </div>
                    <template v-if="me.is_admin && u.pending">
                        <Button variant="ghost" size="sm" @click="resend(u)">Renvoyer</Button>
                        <Button variant="ghost" size="sm" class="text-rose-600" @click="cancel(u)">Annuler</Button>
                    </template>
                    <Button v-else-if="me.is_admin && u.id !== me.id" variant="ghost" size="sm" @click="setAdmin(u, !u.is_admin)">
                        {{ u.is_admin ? 'Retirer admin' : 'Rendre admin' }}
                    </Button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
