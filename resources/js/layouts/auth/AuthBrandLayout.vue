<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarRange, KanbanSquare, Users, Workflow } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const features = [
    { icon: KanbanSquare, label: 'Kanban, liste, calendrier et planning' },
    { icon: Workflow, label: 'Règles automatiques « quand… alors… »' },
    { icon: Users, label: 'Collaborateurs, commentaires et notifications' },
    { icon: CalendarRange, label: 'Dates de début et de fin pour chaque tâche' },
];

// Aperçu décoratif d'un tableau Kanban, dans le style de l'application.
const columns = [
    {
        name: 'À faire',
        cards: [
            { w: 'w-4/5', tag: 'bg-red-100 text-red-700', label: 'Haute', color: 'bg-orange-500' },
            { w: 'w-3/5', tag: 'bg-amber-100 text-amber-700', label: 'Moyenne', color: 'bg-emerald-500' },
        ],
    },
    { name: 'En cours', cards: [{ w: 'w-full', tag: 'bg-amber-100 text-amber-700', label: 'Moyenne', color: 'bg-indigo-500' }] },
    { name: 'Terminé', cards: [{ w: 'w-2/3', tag: 'bg-red-100 text-red-700', label: 'Haute', color: 'bg-pink-500', done: true }] },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <!-- Panneau de marque (écrans larges) -->
        <aside
            class="relative hidden overflow-hidden bg-gradient-to-br from-orange-500 via-orange-500 to-amber-500 p-10 text-white lg:flex lg:flex-col"
        >
            <div class="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-white/10" />
            <div class="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-black/5" />

            <Link :href="route('home')" class="relative flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-white text-lg font-bold text-orange-500 shadow-sm">F</div>
                <div class="leading-tight">
                    <div class="font-semibold">Flexi Projets</div>
                    <div class="text-xs text-white/80">Flexilivre</div>
                </div>
            </Link>

            <div class="relative mt-auto max-w-md">
                <h2 class="text-3xl font-semibold leading-tight">Tous les projets Flexilivre, au même endroit.</h2>
                <ul class="mt-6 space-y-3">
                    <li v-for="f in features" :key="f.label" class="flex items-center gap-3 text-sm text-white/90">
                        <span class="flex size-7 items-center justify-center rounded-md bg-white/15"><component :is="f.icon" class="size-4" /></span>
                        {{ f.label }}
                    </li>
                </ul>
            </div>

            <div
                class="relative mt-10 grid grid-cols-3 gap-3 rounded-xl bg-white/95 p-3 text-foreground shadow-2xl shadow-orange-900/20"
                aria-hidden="true"
            >
                <div v-for="col in columns" :key="col.name" class="rounded-lg bg-zinc-100 p-2">
                    <div class="mb-2 text-[11px] font-semibold text-zinc-700">
                        {{ col.name }} <span class="font-normal text-zinc-400">{{ col.cards.length }}</span>
                    </div>
                    <div v-for="(card, i) in col.cards" :key="i" class="mb-2 rounded-md border border-zinc-200 bg-white p-2 last:mb-0">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="size-2.5 shrink-0 rounded-full border"
                                :class="card.done ? 'border-emerald-500 bg-emerald-500' : 'border-zinc-300'"
                            />
                            <span class="h-1.5 rounded-full bg-zinc-300" :class="card.w" />
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="rounded px-1 text-[9px]" :class="card.tag">{{ card.label }}</span>
                            <span class="size-3.5 rounded-full" :class="card.color" />
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Formulaire -->
        <main class="flex items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-sm">
                <Link :href="route('home')" class="mb-10 flex items-center gap-3 lg:hidden">
                    <div class="flex size-9 items-center justify-center rounded-md bg-orange-500 font-bold text-white">F</div>
                    <div class="leading-tight">
                        <div class="text-sm font-semibold">Flexi Projets</div>
                        <div class="text-xs text-muted-foreground">Flexilivre</div>
                    </div>
                </Link>

                <div class="mb-8 space-y-1.5">
                    <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <div class="flex flex-col gap-6">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
