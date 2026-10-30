<script setup lang="ts">
import PriorityPicker from '@/components/design/PriorityPicker.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import type { Member, Priority, Section } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { CalendarDays, UserRound } from 'lucide-vue-next';
import { watch } from 'vue';

/** Création d'une tâche sur mobile : feuille qui monte du bas de l'écran (maquette « M-Ajout »). */
const props = defineProps<{ projectId: number; sections: Section[]; members: Member[] }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm<{
    title: string;
    description: string;
    assignee_id: number | '';
    due_date: string;
    section_id: number;
    priority: Priority;
}>({ title: '', description: '', assignee_id: '', due_date: '', section_id: props.sections[0]?.id ?? 0, priority: 'low' });

watch(open, (v) => v && form.clearErrors());

const escape = (text: string) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function submit() {
    form.transform((data) => ({
        ...data,
        assignee_id: data.assignee_id || null,
        due_date: data.due_date || null,
        description: data.description.trim()
            ? data.description
                  .trim()
                  .split(/\n+/)
                  .map((l) => `<p>${escape(l)}</p>`)
                  .join('')
            : null,
    })).post(route('tasks.store', props.projectId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="max-h-[92svh] overflow-y-auto rounded-t-[28px] border-0 px-5 pb-6 pt-3 shadow-product">
            <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-ink-medium" aria-hidden="true" />
            <SheetTitle class="mb-1 text-xl font-bold">Nouvelle tâche</SheetTitle>
            <SheetDescription class="sr-only">Créer une tâche dans ce projet.</SheetDescription>
            <form class="space-y-4" @submit.prevent="submit">
                <input
                    v-model="form.title"
                    required
                    aria-label="Nom de la tâche"
                    placeholder="Nom de la tâche"
                    class="h-12 w-full border-b-2 border-app-border bg-transparent text-lg font-bold outline-none focus:border-info"
                />
                <p v-if="form.errors.title" class="text-xs text-brand-active">{{ form.errors.title }}</p>
                <textarea
                    v-model="form.description"
                    rows="2"
                    aria-label="Description"
                    placeholder="Description (facultatif)"
                    class="w-full resize-none bg-transparent text-sm outline-none"
                />
                <div class="flex flex-wrap gap-2">
                    <label class="relative flex h-11 items-center gap-2 rounded-full border-2 border-app-border-strong px-3.5 text-sm font-bold">
                        <UserRound class="size-4 text-ink-soft" />
                        <select v-model="form.assignee_id" aria-label="Responsable" class="appearance-none bg-transparent pr-1 outline-none">
                            <option value="">Responsable</option>
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </label>
                    <label class="flex h-11 items-center gap-2 rounded-full border-2 border-app-border-strong px-3.5 text-sm font-bold">
                        <CalendarDays class="size-4 text-ink-soft" />
                        <span class="sr-only">Échéance</span>
                        <input v-model="form.due_date" type="date" class="bg-transparent outline-none" />
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold uppercase tracking-[.04em] text-ink-soft">Section</span>
                    <select
                        v-model="form.section_id"
                        class="h-11 w-full rounded-field border-2 border-app-border-strong bg-background px-3 text-sm font-semibold"
                    >
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <div>
                    <span class="mb-1.5 block text-xs font-bold uppercase tracking-[.04em] text-ink-soft">Priorité</span>
                    <PriorityPicker v-model="form.priority" />
                </div>
                <Button type="submit" size="lg" class="w-full" :disabled="form.processing">Créer la tâche</Button>
            </form>
        </SheetContent>
    </Sheet>
</template>
