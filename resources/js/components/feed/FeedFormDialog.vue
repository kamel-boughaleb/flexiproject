<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Feed } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** Création ou modification d'un fil d'actualité (nom, description, couleur). */
const props = defineProps<{ feed?: Feed | null; colors: string[] }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ name: '', description: '', color: props.colors[1] ?? '#6366f1' });
watch(
    open,
    (value) => {
        if (!value) return;
        form.clearErrors();
        form.name = props.feed?.name ?? '';
        form.description = props.feed?.description ?? '';
        form.color = props.feed?.color ?? props.colors[1] ?? '#6366f1';
    },
    { immediate: true },
);

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (props.feed) form.patch(route('feeds.update', props.feed.id), options);
    else form.post(route('feeds.store'), options);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ feed ? $t('Modifier le fil') : $t("Nouveau fil d'actualité") }}</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="feed-name">{{ $t('Nom du fil') }}</Label>
                    <Input id="feed-name" v-model="form.name" required :placeholder="$t('Ex. Vie de l’entreprise')" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="feed-description">{{ $t('Description') }}</Label>
                    <textarea
                        id="feed-description"
                        v-model="form.description"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        :placeholder="$t('De quoi parle ce fil ?')"
                    />
                    <InputError :message="form.errors.description" />
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
                <p v-if="!feed" class="text-xs text-muted-foreground">
                    {{ $t('Vous êtes abonné automatiquement. Toute l’équipe peut voir ce fil, s’y abonner et y publier.') }}
                </p>
                <Button type="submit" :disabled="form.processing">{{ feed ? $t('Enregistrer') : $t('Créer le fil') }}</Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
