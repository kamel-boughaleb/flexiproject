<script setup lang="ts">
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { t, tc } from '@/lib/i18n';
import { dateTime, dueLabel } from '@/lib/labels';
import type { FeedBadge, MiniUser, ProjectBadge, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, AtSign, Newspaper, Paperclip } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

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

type MorningAlert = { overdue: number; urgent: number; tasks: TaskRow[] };
const props = defineProps<{
    posts: LatestPost[];
    alertTasks: TaskRow[];
    mentions: Mention[];
    /** Première ouverture de l'accueil de la journée : animation d'accueil. */
    intro: boolean;
    morningAlert: MorningAlert | null;
}>();

const user = usePage<SharedData>().props.auth.user;
const firstName = user.name.split(' ')[0];

// Les rafraîchissements automatiques renvoient intro = false et morningAlert = null : on garde la première valeur.
const morning: MorningAlert | null = props.morningAlert;
const alertOpen = ref(false);

/*
 * Animation du matin :
 *  1. « Hello X ! » apparaît au centre de l'écran ;
 *  2. le titre remonte à sa place ;
 *  3. le résumé de la journée s'affiche au centre ;
 *  4. quelques secondes après (ou au clic), le résumé disparaît et les blocs apparaissent un à un.
 */
type Phase = 'hello' | 'title' | 'recap' | 'done';
const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const phase = ref<Phase>(props.intro && !reduceMotion ? 'hello' : 'done');
const titleEl = ref<HTMLElement | null>(null);
const helloEl = ref<HTMLElement | null>(null);
const helloBox = ref<{ left: number; top: number } | null>(null);
const timers: ReturnType<typeof setTimeout>[] = [];
const later = (ms: number, fn: () => void) => timers.push(setTimeout(fn, ms));

const overdueCount = computed(() => props.alertTasks.filter((t) => t.overdue).length);
const urgentCount = computed(() => props.alertTasks.filter((t) => !t.overdue).length);
const todayPosts = computed(() => props.posts.filter((p) => p.published_on === new Date().toLocaleDateString('sv-SE')).length);
const recap = computed(() => {
    const parts = [
        overdueCount.value ? tc(':count tâche en retard|:count tâches en retard', overdueCount.value) : null,
        urgentCount.value ? tc(':count tâche à finir|:count tâches à finir', urgentCount.value) : null,
        props.mentions.length ? tc(':count commentaire à consulter|:count commentaires à consulter', props.mentions.length) : null,
        todayPosts.value ? tc(':count nouvelle actualité|:count nouvelles actualités', todayPosts.value) : null,
    ].filter((p): p is string => !!p);
    if (!parts.length) return t("Aujourd'hui, rien d'urgent. Belle journée !");
    const list = parts.length > 1 ? `${parts.slice(0, -1).join(', ')} ${t('et')} ${parts[parts.length - 1]}` : parts[0];
    return t("Aujourd'hui tu as :list.", { list });
});

/** Décalage du titre centré (et agrandi) par rapport à sa place définitive dans la page. */
function centerTransform(scale: number) {
    const r = titleEl.value!.getBoundingClientRect();
    const dx = window.innerWidth / 2 - (r.left + r.width / 2);
    const dy = window.innerHeight / 2 - (r.top + r.height / 2) - 40;
    return `translate(${dx}px, ${dy}px) scale(${scale})`;
}

function finishIntro() {
    if (phase.value === 'done') return;
    timers.forEach(clearTimeout);
    phase.value = 'done';
    if (morning) later(900, () => (alertOpen.value = true));
}

onMounted(() => {
    if (phase.value === 'done') {
        if (morning) alertOpen.value = true;
        return;
    }
    const r = titleEl.value!.getBoundingClientRect();
    helloBox.value = { left: r.left, top: r.top };
    nextTick(() => {
        const el = helloEl.value!;
        const center = centerTransform(2);
        el.animate(
            [
                { opacity: 0, transform: `${centerTransform(1.6)}` },
                { opacity: 1, transform: center },
            ],
            { duration: 700, easing: 'cubic-bezier(.2,.7,.2,1)', fill: 'forwards' },
        );
        later(1600, () => {
            phase.value = 'title';
            el.animate([{ transform: center }, { transform: 'none' }], { duration: 750, easing: 'cubic-bezier(.6,0,.2,1)', fill: 'forwards' });
        });
        later(2500, () => (phase.value = 'recap'));
        later(7500, finishIntro);
    });
});
onUnmounted(() => timers.forEach(clearTimeout));

/** Délai d'apparition de chaque bloc de l'accueil. */
const enter = (i: number) => ({ animationDelay: `${i * 120}ms` });
</script>

<template>
    <Head :title="$t('Accueil')" />
    <AppLayout>
        <div class="w-full max-w-6xl p-6">
            <h1 ref="titleEl" class="w-fit text-2xl font-semibold" :class="{ invisible: phase !== 'done' }">
                {{ $t('Hello :name !', { name: firstName }) }}
            </h1>

            <div v-if="phase === 'done'" class="mt-6 grid gap-6 lg:grid-cols-2">
                <!-- Tâches en retard ou urgentes (échéance d'ici 2 jours ouvrés). -->
                <section
                    class="dash-in rounded-2xl border border-app-border p-4 lg:col-span-2"
                    :style="enter(0)"
                    :class="alertTasks.length ? 'bg-brand-tint/40 border-brand' : ''"
                >
                    <h2 class="flex items-center gap-2 font-bold">
                        <AlertTriangle class="size-4" :class="alertTasks.length ? 'text-brand-active' : 'text-ink-soft'" />
                        {{ $t('Tâches en retard ou urgentes') }}
                    </h2>
                    <ul v-if="alertTasks.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="t in alertTasks" :key="t.id" v-live="t">
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
                <section class="dash-in rounded-2xl border border-app-border p-4" :style="enter(1)">
                    <div class="flex items-center gap-3">
                        <h2 class="flex items-center gap-2 font-bold"><Newspaper class="size-4 text-ink-soft" />{{ $t('Dernières actualités') }}</h2>
                        <Link :href="route('feeds.index')" class="ml-auto text-sm font-semibold text-ink-soft hover:text-ink">{{
                            $t('Tous les fils')
                        }}</Link>
                    </div>
                    <ul v-if="posts.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="n in posts" :key="n.id" v-live="n" class="rounded-lg">
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
                <section class="dash-in rounded-2xl border border-app-border p-4" :style="enter(2)">
                    <h2 class="flex items-center gap-2 font-bold"><AtSign class="size-4 text-ink-soft" />{{ $t('On parle de vous') }}</h2>
                    <ul v-if="mentions.length" class="mt-3 divide-y divide-app-border">
                        <li v-for="m in mentions" :key="m.id" v-live="m" class="rounded-lg">
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

        <!-- Animation du matin (première ouverture de la journée) ; un clic la passe. -->
        <Transition leave-active-class="transition-opacity duration-500" leave-to-class="opacity-0">
            <div v-if="phase !== 'done'" class="fixed inset-0 z-50 cursor-pointer bg-background" @click="finishIntro">
                <h1
                    v-if="helloBox"
                    ref="helloEl"
                    class="pointer-events-none fixed w-fit origin-center whitespace-nowrap text-2xl font-semibold opacity-0"
                    :style="{ left: `${helloBox.left}px`, top: `${helloBox.top}px` }"
                >
                    {{ $t('Hello :name !', { name: firstName }) }}
                </h1>
                <Transition enter-active-class="transition duration-700 ease-out" enter-from-class="translate-y-4 opacity-0">
                    <div v-if="phase === 'recap'" class="flex h-full items-center justify-center p-8">
                        <div class="max-w-2xl text-center">
                            <p class="text-2xl font-semibold leading-snug text-ink sm:text-3xl">{{ recap }}</p>
                            <p class="mt-6 text-sm text-ink-soft">{{ $t('Cliquez pour continuer') }}</p>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Alerte du matin : première ouverture de la journée, tâches en retard ou urgentes affectées depuis la dernière visite. -->
        <Dialog v-if="morning" v-model:open="alertOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><AlertTriangle class="size-5 text-brand-active" />{{ $t('Attention') }}</DialogTitle>
                    <DialogDescription class="text-base text-ink">
                        <template v-if="morning.overdue && morning.urgent">
                            {{
                                $t('Ce matin, vous avez :overdue et :urgent :', {
                                    overdue: $tc(':count tâche en retard|:count tâches en retard', morning.overdue),
                                    urgent: $tc(':count tâche urgente|:count tâches urgentes', morning.urgent),
                                })
                            }}
                        </template>
                        <template v-else-if="morning.overdue">
                            {{
                                $t('Ce matin, vous avez :overdue :', {
                                    overdue: $tc(':count tâche en retard|:count tâches en retard', morning.overdue),
                                })
                            }}
                        </template>
                        <template v-else>
                            {{
                                $t('Ce matin, vous avez :overdue :', {
                                    overdue: $tc(':count tâche urgente|:count tâches urgentes', morning.urgent),
                                })
                            }}
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <ul class="divide-y divide-app-border">
                    <li v-for="t in morning.tasks" :key="t.id">
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
