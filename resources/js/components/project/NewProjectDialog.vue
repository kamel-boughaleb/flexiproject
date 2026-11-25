<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ProjectAvatarField from '@/components/project/ProjectAvatarField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/** Création d'un projet (bouton « + » du menu de gauche). */
const creating = defineModel<boolean>('open', { required: true });
const colors = computed(() => usePage<SharedData>().props.projectColors);
const form = useForm({ name: '', description: '', color: '#f97316', icon: '', image: null as File | null, start_date: '', end_date: '' });
watch(creating, (value) => value && (form.reset(), form.clearErrors()));
const submit = () => form.post(route('projects.store'), { forceFormData: true, onSuccess: () => (creating.value = false) });
</script>

<template>
    <Dialog v-model:open="creating">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader
                ><DialogTitle>{{ $t('Nouveau projet') }}</DialogTitle></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('Nom du projet') }}</Label>
                    <Input id="name" v-model="form.name" required :placeholder="$t('Ex. Catalogue de Noël 2026')" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">{{ $t('Description') }}</Label>
                    <textarea id="description" v-model="form.description" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="start">{{ $t('Début') }}</Label>
                        <Input id="start" v-model="form.start_date" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="end">{{ $t('Fin') }}</Label>
                        <Input id="end" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('Avatar') }}</Label>
                    <ProjectAvatarField v-model:icon="form.icon" v-model:image="form.image" :name="form.name" :color="form.color" />
                    <InputError :message="form.errors.image" />
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="c in colors"
                        :key="c"
                        type="button"
                        :aria-label="$t('Couleur :color', { color: c })"
                        class="size-6 rounded-full ring-offset-2 ring-offset-background"
                        :class="{ 'ring-2 ring-neutral-400': form.color === c }"
                        :style="{ backgroundColor: c }"
                        @click="form.color = c"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ $t('Le projet démarre avec les sections « À faire », « En cours » et « Terminé ».') }}
                </p>
                <Button type="submit" :disabled="form.processing">{{ $t('Créer le projet') }}</Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
