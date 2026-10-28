<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import { dateTime } from '@/lib/labels';
import type { Activity, ProjectPageProps } from '@/types';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps<ProjectPageProps & { colors: string[]; activities: Activity[] }>();

const form = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    color: props.project.color,
    start_date: props.project.start_date ?? '',
    end_date: props.project.end_date ?? '',
});
const submit = () => form.patch(route('projects.update', props.project.id), { preserveScroll: true });
const destroy = () =>
    confirm(`Supprimer définitivement « ${props.project.name} » et toutes ses tâches ?`) &&
    router.delete(route('projects.destroy', props.project.id));
</script>

<template>
    <ProjectLayout :project="project" :members="members" title="Paramètres">
        <div class="mx-auto grid max-w-5xl gap-6 p-6 lg:grid-cols-2">
            <form class="space-y-3 rounded-lg border p-4" @submit.prevent="submit">
                <h2 class="font-semibold">Informations du projet</h2>
                <fieldset :disabled="!can.manage" class="space-y-3 text-sm">
                    <label class="block">
                        <span class="mb-1 block text-xs text-muted-foreground">Nom</span>
                        <input v-model="form.name" required class="w-full rounded-md border bg-background px-3 py-1.5" />
                        <InputError :message="form.errors.name" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs text-muted-foreground">Description</span>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-md border bg-background px-3 py-2" />
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="mb-1 block text-xs text-muted-foreground">Début</span>
                            <input v-model="form.start_date" type="date" class="w-full rounded-md border bg-background px-2 py-1" />
                        </label>
                        <label>
                            <span class="mb-1 block text-xs text-muted-foreground">Fin</span>
                            <input v-model="form.end_date" type="date" class="w-full rounded-md border bg-background px-2 py-1" />
                            <InputError :message="form.errors.end_date" />
                        </label>
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
                    <Button v-if="can.manage" type="submit" :disabled="form.processing">Enregistrer</Button>
                    <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600">Enregistré.</p>
                </fieldset>
            </form>

            <section class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Activité récente</h2>
                <ul class="space-y-1.5 text-sm text-muted-foreground">
                    <li v-for="a in activities" :key="a.id">
                        <span class="text-xs">{{ dateTime(a.created_at) }}</span> ·
                        <strong v-if="a.user" class="text-foreground">{{ a.user.name }}</strong
                        ><span v-else>⚙️</span> {{ a.message }}
                    </li>
                </ul>
            </section>

            <div v-if="can.manage" class="rounded-lg border border-rose-200 p-4 dark:border-rose-900 lg:col-span-2">
                <h2 class="font-semibold text-rose-700">Zone dangereuse</h2>
                <p class="mb-3 text-sm text-muted-foreground">Supprimer le projet efface définitivement ses tâches, règles et commentaires.</p>
                <Button variant="destructive" @click="destroy">Supprimer le projet</Button>
            </div>
        </div>
    </ProjectLayout>
</template>
