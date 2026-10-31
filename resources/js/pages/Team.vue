<script setup lang="ts">
import InviteDialog from '@/components/InviteDialog.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MiniUser, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Ban, Ellipsis, RotateCcw, Shield, ShieldOff, UserX } from 'lucide-vue-next';
import { computed } from 'vue';

type TeamUser = MiniUser & {
    email: string;
    projects_count: number;
    pending: boolean;
    deactivated: boolean;
    is_admin: boolean;
    invited_at: string | null;
};
const props = defineProps<{ users: TeamUser[] }>();
const me = usePage<SharedData>().props.auth.user;

const resend = (u: TeamUser) => router.post(route('team.resend', u.id), {}, { preserveScroll: true });
const cancel = (u: TeamUser) =>
    confirm(`Annuler l'invitation de ${u.email} ?`) && router.delete(route('team.destroy', u.id), { preserveScroll: true });

const setAdmin = (u: TeamUser, is_admin: boolean) => router.patch(route('team.admin', u.id), { is_admin }, { preserveScroll: true });

const deactivate = (u: TeamUser) =>
    confirm(
        `Désactiver le compte de ${u.name} ?\n\nIl reste dans ses projets et ses tâches et reste affiché dans l'équipe, mais ne peut plus se connecter ni recevoir de notification.`,
    ) && router.post(route('team.deactivate', u.id), {}, { preserveScroll: true });
const reactivate = (u: TeamUser) => router.post(route('team.reactivate', u.id), {}, { preserveScroll: true });
const remove = (u: TeamUser) =>
    confirm(
        `Supprimer ${u.name} de l'équipe ?\n\nIl reste dans ses projets et ses tâches (historique conservé), mais ne peut plus se connecter, ne reçoit plus de notification et n'apparaît plus ici. Pour le réactiver, invitez à nouveau ${u.email}.`,
    ) && router.delete(route('team.destroy', u.id), { preserveScroll: true });

const pendingCount = computed(() => props.users.filter((u) => u.pending).length);
const deactivatedCount = computed(() => props.users.filter((u) => u.deactivated && !u.pending).length);
</script>

<template>
    <Head title="Équipe" />
    <AppLayout :breadcrumbs="[{ title: 'Équipe', href: route('team.index') }]">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-semibold">Équipe</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ users.length - pendingCount - deactivatedCount }} collaborateur(s)<template v-if="pendingCount"
                            >, {{ pendingCount }} invitation(s) en attente</template
                        ><template v-if="deactivatedCount">, {{ deactivatedCount }} désactivé(s)</template>. Ajoutez ensuite chaque personne aux
                        projets concernés depuis l'onglet « Collaborateurs » du projet.
                    </p>
                </div>
                <InviteDialog v-if="me.is_admin" />
            </div>

            <ul class="divide-y rounded-lg border">
                <li v-for="u in users" :key="u.id" class="flex items-center gap-3 px-4 py-3">
                    <UserAvatar :user="u" size="lg" :class="{ 'opacity-50 grayscale': u.deactivated }" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <span class="truncate">{{ u.name }}</span>
                            <span v-if="u.is_admin" class="rounded-full bg-brand-tint px-2 py-0.5 text-xs font-bold text-brand-active">
                                Administrateur
                            </span>
                            <span v-if="u.pending" class="rounded-full bg-warning-tint px-2 py-0.5 text-xs font-bold text-warning-text">
                                Invitation en attente
                            </span>
                            <span v-if="u.deactivated" class="rounded-full bg-muted px-2 py-0.5 text-xs font-bold text-muted-foreground">
                                Désactivé
                            </span>
                        </div>
                        <div class="truncate text-xs text-muted-foreground">{{ u.email }} · {{ u.projects_count }} projet(s)</div>
                    </div>
                    <template v-if="me.is_admin && u.pending">
                        <Button variant="ghost" size="sm" @click="resend(u)">Renvoyer</Button>
                        <Button variant="ghost" size="sm" class="text-brand-active" @click="cancel(u)">Annuler</Button>
                    </template>
                    <template v-else-if="me.is_admin && u.id !== me.id">
                        <Button v-if="u.deactivated" variant="ghost" size="sm" @click="reactivate(u)">
                            <RotateCcw class="size-4" /> Réactiver
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                class="flex size-8 items-center justify-center rounded-icon text-ink-soft hover:bg-muted hover:text-ink data-[state=open]:bg-muted data-[state=open]:text-ink"
                                :aria-label="`Actions pour ${u.name}`"
                            >
                                <Ellipsis class="size-[18px]" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-56 rounded-2xl p-1.5">
                                <DropdownMenuItem v-if="!u.deactivated" class="rounded-icon font-semibold" @select="setAdmin(u, !u.is_admin)">
                                    <component :is="u.is_admin ? ShieldOff : Shield" class="size-4" />
                                    {{ u.is_admin ? 'Retirer les droits admin' : 'Rendre administrateur' }}
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="!u.deactivated" class="rounded-icon font-semibold" @select="deactivate(u)">
                                    <Ban class="size-4" /> Désactiver le compte
                                </DropdownMenuItem>
                                <DropdownMenuItem v-else class="rounded-icon font-semibold" @select="reactivate(u)">
                                    <RotateCcw class="size-4" /> Réactiver le compte
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="rounded-icon font-semibold text-destructive focus:bg-destructive/10 focus:text-destructive"
                                    @select="remove(u)"
                                >
                                    <UserX class="size-4" /> Supprimer de l'équipe
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
