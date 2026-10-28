<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateRange } from '@/lib/labels';
import type { MiniUser, Project, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    projects: (Project & { members: MiniUser[]; tasks_total: number; tasks_done: number })[];
    colors: string[];
}>();

const user = usePage<SharedData>().props.auth.user;
const creating = ref(new URLSearchParams(window.location.search).has('new'));
const form = useForm({ name: '', description: '', color: '#f97316', start_date: '', end_date: '' });
const submit = () => form.post(route('projects.store'));
const pct = (p: { tasks_total: number; tasks_done: number }) => (p.tasks_total ? Math.round((p.tasks_done / p.tasks_total) * 100) : 0);
</script>

<template>
    <Head title="Accueil" />
    <AppLayout :breadcrumbs="[{ title: 'Projets', href: route('dashboard') }]">
        <div class="p-6">
            <div class="flex items-center">
                <div>
                    <h1 class="text-2xl font-semibold">Bonjour {{ user.name.split(' ')[0] }} 👋</h1>
                    <p class="text-sm text-muted-foreground">Vos projets Flexilivre</p>
                </div>
                <Button class="ml-auto" @click="creating = true"><Plus class="size-4" /> Nouveau projet</Button>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link v-for="p in projects" :key="p.id" :href="route('projects.show', p.id)" class="rounded-xl border p-4 transition hover:shadow-md">
                    <div class="flex items-center gap-2">
                        <span class="size-3 rounded" :style="{ backgroundColor: p.color }" />
                        <h2 class="font-semibold">{{ p.name }}</h2>
                    </div>
                    <p v-if="p.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ p.description }}</p>
                    <div class="mt-4 h-1.5 rounded-full bg-muted">
                        <div class="h-1.5 rounded-full" :style="{ width: `${pct(p)}%`, backgroundColor: p.color }" />
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                        <span>{{ p.tasks_done }}/{{ p.tasks_total }} tâches terminées</span>
                        <span>{{ dateRange(p.start_date, p.end_date) }}</span>
                    </div>
                    <div class="mt-3 flex -space-x-1.5">
                        <span v-for="m in p.members.slice(0, 5)" :key="m.id" class="rounded-full ring-2 ring-background"
                            ><UserAvatar :user="m"
                        /></span>
                    </div>
                </Link>
                <button
                    v-if="!projects.length"
                    class="rounded-xl border border-dashed p-8 text-sm text-muted-foreground hover:bg-muted/40"
                    @click="creating = true"
                >
                    Créez votre premier projet
                </button>
            </div>
        </div>

        <Dialog v-model:open="creating">
            <DialogContent>
                <DialogHeader><DialogTitle>Nouveau projet</DialogTitle></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nom du projet</Label>
                        <Input id="name" v-model="form.name" required placeholder="Ex. Catalogue de Noël 2026" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea id="description" v-model="form.description" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="start">Début</Label>
                            <Input id="start" v-model="form.start_date" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end">Fin</Label>
                            <Input id="end" v-model="form.end_date" type="date" />
                            <InputError :message="form.errors.end_date" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-for="c in colors"
                            :key="c"
                            type="button"
                            :aria-label="`Couleur ${c}`"
                            class="size-6 rounded-full ring-offset-2 ring-offset-background"
                            :class="{ 'ring-2 ring-neutral-400': form.color === c }"
                            :style="{ backgroundColor: c }"
                            @click="form.color = c"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">Le projet démarre avec les sections « À faire », « En cours » et « Terminé ».</p>
                    <Button type="submit" :disabled="form.processing">Créer le projet</Button>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
