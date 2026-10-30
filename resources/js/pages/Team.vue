<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MiniUser, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { MailPlus } from 'lucide-vue-next';
import { computed } from 'vue';

type TeamUser = MiniUser & { email: string; projects_count: number; pending: boolean; invited_at: string | null };
const props = defineProps<{ users: TeamUser[] }>();
const page = usePage<SharedData>();

const form = useForm({ email: '', name: '' });
const invite = () => form.post(route('team.store'), { preserveScroll: true, onSuccess: () => form.reset() });
const resend = (u: TeamUser) => router.post(route('team.resend', u.id), {}, { preserveScroll: true });
const cancel = (u: TeamUser) =>
    confirm(`Annuler l'invitation de ${u.email} ?`) && router.delete(route('team.destroy', u.id), { preserveScroll: true });

const pendingCount = computed(() => props.users.filter((u) => u.pending).length);
</script>

<template>
    <Head title="Équipe" />
    <AppLayout :breadcrumbs="[{ title: 'Équipe', href: route('team.index') }]">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold">Équipe</h1>
                <p class="text-sm text-muted-foreground">
                    {{ users.length - pendingCount }} collaborateur(s)<template v-if="pendingCount"
                        >, {{ pendingCount }} invitation(s) en attente</template
                    >. Ajoutez ensuite chaque personne aux projets concernés depuis l'onglet « Collaborateurs » du projet.
                </p>
            </div>

            <form class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="invite">
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">E-mail</span>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="collegue@flexilivre.com"
                        class="w-full rounded-md border bg-background px-3 py-1.5"
                    />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-muted-foreground">Nom (facultatif)</span>
                    <input v-model="form.name" placeholder="Prénom Nom" class="w-full rounded-md border bg-background px-3 py-1.5" />
                </label>
                <Button type="submit" :disabled="form.processing"><MailPlus class="size-4" /> Inviter</Button>
                <InputError :message="form.errors.email" class="sm:col-span-3" />
                <p v-if="page.props.flash?.success" class="text-sm text-emerald-600 sm:col-span-3">{{ page.props.flash.success }}</p>
                <p v-else class="text-xs text-muted-foreground sm:col-span-3">
                    La personne reçoit un e-mail pour créer son compte, ajouter sa photo et choisir un mot de passe (ou se connecter avec Google).
                </p>
            </form>

            <ul class="divide-y rounded-lg border">
                <li v-for="u in users" :key="u.id" class="flex items-center gap-3 px-4 py-3">
                    <UserAvatar :user="u" size="lg" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <span class="truncate">{{ u.name }}</span>
                            <span
                                v-if="u.pending"
                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                            >
                                Invitation en attente
                            </span>
                        </div>
                        <div class="truncate text-xs text-muted-foreground">{{ u.email }} · {{ u.projects_count }} projet(s)</div>
                    </div>
                    <template v-if="u.pending">
                        <Button variant="ghost" size="sm" @click="resend(u)">Renvoyer</Button>
                        <Button variant="ghost" size="sm" class="text-rose-600" @click="cancel(u)">Annuler</Button>
                    </template>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
