<script setup lang="ts">
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, dueLabel } from '@/lib/labels';
import type { FeedBadge, MiniUser, ProjectBadge, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, AtSign, Newspaper, Paperclip } from 'lucide-vue-next';
import { ref } from 'vue';

type LatestPost = {
    id: number;
    title: string;
    published_on: string;
    excerpt: string;
    attachments_count: number;
    feed: FeedBadge;
    user: MiniUser | null;
};
type TaskRow = { id: number; title: string; due_date: string | null; overdue: boolean; urgent: boolean; url: string; project: ProjectBadge };
type Mention = {
    id: number;
    excerpt: string;
    created_at: string;
    user: MiniUser | null;
    task: { id: number; title: string; url: string };
    project: ProjectBadge;
};

const props = defineProps<{
    posts: LatestPost[];
    alertTasks: TaskRow[];
    mentions: Mention[];
    morningAlert: { overdue: number; urgent: number; tasks: TaskRow[] } | null;
}>();

const user = usePage<SharedData>().props.auth.user;
const alertOpen = ref(!!props.morningAlert);
</script>

<template>
    <Head :title="$t('Accueil')" />
    <AppLayout>
        <div class="w-full max-w-6xl p-6">
            <h1 class="text-2xl font-semibold">{{ $t('Bonjour :name 👋', { name: user.name.split(' ')[0] }) }}</h1>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <!-- Tâches en retard ou urgentes (échéance d'ici 2 jours ouvrés). -->
                <section
                    class="rounded-2xl border border-app-border p-4 lg:col-span-2"
                    :class="alertTasks.length ? 'bg-brand-tint/40 border-brand' : ''"
                >
                    <h2 class="flex items-center gap-2 font-bold">
                        <AlertTriangle class="size-4" :class="alertTasks.length ? 'text-brand-active' : 'text-ink-soft'" />
                        {{ $t('Tâches en retard ou urgentes') }}
                    </h2>
                    <ul v-if="alertTasks.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="t in alertTasks" :key="t.id">
                            <Link :href="t.url" class="flex items-center gap-3 py-2 hover:opacity-80">
                                <AlertTriangle class="size-4 shrink-0 text-brand-active" />
                                <span class="min-w-0 flex-1 truncate font-semibold">{{ t.title }}</span>
                                <span class="hidden min-w-0 items-center gap-1.5 truncate text-xs text-ink-soft sm:flex">
                                    <ProjectAvatar :project="t.project" size="xs" />{{ t.project.name }}
                                </span>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold"
                                    :class="t.overdue ? 'bg-brand text-white' : 'bg-warning-tint text-warning-text'"
                                >
                                    {{ t.overdue ? $t('En retard') : $t('Urgent') }} · {{ dueLabel(t.due_date) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-ink-soft">{{ $t('Aucune tâche en retard ou urgente. Bravo !') }}</p>
                </section>

                <!-- Dernières actualités (moins de 5 jours ouvrés). -->
                <section class="rounded-2xl border border-app-border p-4">
                    <div class="flex items-center gap-3">
                        <h2 class="flex items-center gap-2 font-bold"><Newspaper class="size-4 text-ink-soft" />{{ $t('Dernières actualités') }}</h2>
                        <Link :href="route('feeds.index')" class="ml-auto text-sm font-semibold text-ink-soft hover:text-ink">{{
                            $t('Tous les fils')
                        }}</Link>
                    </div>
                    <ul v-if="posts.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="n in posts" :key="n.id">
                            <Link :href="route('posts.show', [n.feed.id, n.id])" class="block py-2.5 hover:opacity-80">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide" :style="{ color: n.feed.color }">
                                    <ProjectAvatar :project="n.feed" size="xs" />
                                    <span class="truncate">{{ n.feed.name }}</span>
                                    <span class="ml-auto shrink-0 font-semibold normal-case tracking-normal text-ink-soft">{{
                                        dueLabel(n.published_on)
                                    }}</span>
                                </div>
                                <h3 class="mt-1 font-semibold">{{ n.title }}</h3>
                                <p v-if="n.excerpt" class="mt-0.5 line-clamp-2 text-sm text-muted-foreground">{{ n.excerpt }}</p>
                                <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                    <UserAvatar v-if="n.user" :user="n.user" size="xs" />
                                    <span class="truncate">{{ n.user?.name }}</span>
                                    <span v-if="n.attachments_count" class="flex items-center gap-0.5"
                                        ><Paperclip class="size-3" />{{ n.attachments_count }}</span
                                    >
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-ink-soft">{{ $t('Aucune actualité ces 5 derniers jours ouvrés.') }}</p>
                </section>

                <!-- Commentaires de collègues qui mentionnent l'utilisateur (moins de 48 h ouvrées). -->
                <section class="rounded-2xl border border-app-border p-4">
                    <h2 class="flex items-center gap-2 font-bold"><AtSign class="size-4 text-ink-soft" />{{ $t('On parle de vous') }}</h2>
                    <ul v-if="mentions.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="m in mentions" :key="m.id">
                            <Link :href="m.task.url" class="flex gap-3 py-2.5 hover:opacity-80">
                                <UserAvatar v-if="m.user" :user="m.user" size="md" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        <span class="font-bold">{{ m.user?.name }}</span>
                                        <span class="text-ink-soft"> · {{ m.task.title }}</span>
                                    </p>
                                    <p class="line-clamp-2 text-sm text-muted-foreground">{{ m.excerpt }}</p>
                                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-soft">
                                        <ProjectAvatar :project="m.project" size="xs" />{{ m.project.name }} · {{ dateTime(m.created_at) }}
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-ink-soft">{{ $t('Aucune mention ces dernières 48 h.') }}</p>
                </section>
            </div>
        </div>

        <!-- Alerte du matin : première ouverture de la journée, tâches en retard ou urgentes affectées depuis la dernière visite. -->
        <Dialog v-if="morningAlert" v-model:open="alertOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><AlertTriangle class="size-5 text-brand-active" />{{ $t('Attention') }}</DialogTitle>
                    <DialogDescription class="text-base text-ink">
                        <template v-if="morningAlert.overdue && morningAlert.urgent">
                            {{
                                $t('Ce matin, vous avez :overdue et :urgent :', {
                                    overdue: $tc(':count tâche en retard|:count tâches en retard', morningAlert.overdue),
                                    urgent: $tc(':count tâche urgente|:count tâches urgentes', morningAlert.urgent),
                                })
                            }}
                        </template>
                        <template v-else-if="morningAlert.overdue">
                            {{
                                $t('Ce matin, vous avez :overdue :', {
                                    overdue: $tc(':count tâche en retard|:count tâches en retard', morningAlert.overdue),
                                })
                            }}
                        </template>
                        <template v-else>
                            {{
                                $t('Ce matin, vous avez :overdue :', {
                                    overdue: $tc(':count tâche urgente|:count tâches urgentes', morningAlert.urgent),
                                })
                            }}
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <ul class="divide-y divide-app-border">
                    <li v-for="t in morningAlert.tasks" :key="t.id">
                        <Link :href="t.url" class="flex items-center gap-3 py-2 hover:opacity-80" @click="alertOpen = false">
                            <AlertTriangle class="size-4 shrink-0 text-brand-active" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold underline-offset-2 hover:underline">{{ t.title }}</span>
                                <span class="block truncate text-xs text-ink-soft">{{ t.project.name }}</span>
                            </span>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="t.overdue ? 'bg-brand text-white' : 'bg-warning-tint text-warning-text'"
                            >
                                {{ t.overdue ? $t('En retard') : $t('Urgent') }} · {{ dueLabel(t.due_date) }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <DialogFooter>
                    <Button @click="alertOpen = false">{{ $t('Compris') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
