<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const features = [
    'Kanban, liste, calendrier et planning',
    'Règles automatiques « quand… alors… »',
    'Collaborateurs, commentaires et notifications',
    'Dates de début et de fin pour chaque tâche',
];

// Aperçu décoratif d'un tableau Kanban, dans le style de l'application.
const columns = [
    {
        name: 'À faire',
        cards: [
            { w: 'w-4/5', tag: 'bg-brand-tint text-brand-active', label: 'Haute' },
            { w: 'w-3/5', tag: 'bg-warning-tint text-warning-text', label: 'Moyenne' },
        ],
    },
    { name: 'En cours', cards: [{ w: 'w-full', tag: 'bg-info-tint text-info-deep', label: 'Basse' }] },
    { name: 'Terminé', cards: [{ w: 'w-2/3', tag: 'bg-success-tint text-success-text', label: 'Terminée', done: true }] },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:p-3">
        <!-- Panneau de marque (écrans larges) -->
        <aside class="relative hidden overflow-hidden rounded-[28px] bg-brand-tint p-12 lg:flex lg:flex-col">
            <div class="pointer-events-none absolute -right-20 -top-24 size-80 rounded-full bg-brand-disabled" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-28 -left-20 size-96 rounded-full bg-info-tint" aria-hidden="true" />

            <Link :href="route('home')" class="relative flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand text-xl font-bold text-white">F</div>
                <div class="leading-tight">
                    <div class="text-[17px] font-bold">Flexi Projets</div>
                    <div class="text-[13px] text-ink-soft">Flexilivre</div>
                </div>
            </Link>

            <div class="relative mt-auto max-w-lg">
                <p class="font-script text-lg text-brand-active">Vos projets, bien rangés</p>
                <h2 class="mt-3 text-[35px] font-bold leading-tight">Tous les projets Flexilivre, au même endroit.</h2>
                <ul class="mt-6 space-y-3">
                    <li v-for="f in features" :key="f" class="flex items-center gap-3 text-[15px] font-semibold">
                        <span class="flex size-6 items-center justify-center rounded-full bg-success text-white"
                            ><Check class="size-3.5" :stroke-width="3"
                        /></span>
                        {{ f }}
                    </li>
                </ul>
            </div>

            <div class="relative mt-10 grid grid-cols-3 gap-3 rounded-card bg-background p-3 shadow-product" aria-hidden="true">
                <div v-for="col in columns" :key="col.name" class="rounded-2xl bg-app-hover p-2.5">
                    <div class="mb-2 text-xs font-bold">
                        {{ col.name }} <span class="font-semibold text-ink-soft">{{ col.cards.length }}</span>
                    </div>
                    <div v-for="(card, i) in col.cards" :key="i" class="mb-2 rounded-xl bg-background p-2.5 shadow-soft last:mb-0">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="size-3 shrink-0 rounded-full border-[1.5px]"
                                :class="card.done ? 'border-success bg-success' : 'border-app-muted'"
                            />
                            <span class="h-1.5 rounded-full bg-ink-medium" :class="card.w" />
                        </div>
                        <span class="mt-2 inline-block rounded-full px-1.5 text-[9px] font-bold" :class="card.tag">{{ card.label }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Formulaire -->
        <main class="flex flex-col lg:items-center lg:justify-center lg:p-10">
            <!-- Mobile : en-tête de marque arrondi en bas -->
            <div class="rounded-b-[28px] bg-brand-tint px-6 pb-7 pt-8 lg:hidden">
                <Link :href="route('home')" class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-icon bg-brand text-lg font-bold text-white">F</div>
                    <div class="leading-tight">
                        <div class="text-[15px] font-bold">Flexi Projets</div>
                        <div class="text-xs text-ink-soft">Flexilivre</div>
                    </div>
                </Link>
                <p class="mt-6 font-script text-sm text-brand-active">Vos projets, bien rangés</p>
                <p class="mt-1.5 text-[26px] font-bold leading-tight">Tous les projets Flexilivre, au même endroit.</p>
            </div>

            <div class="w-full max-w-[420px] px-6 py-8 lg:p-0">
                <div class="mb-7 space-y-1.5">
                    <h1 v-if="title" class="text-[26px] font-bold leading-tight lg:text-[32px]">{{ title }}</h1>
                    <p v-if="description" class="text-[15px] text-ink-soft">{{ description }}</p>
                </div>

                <div class="flex flex-col gap-5">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
