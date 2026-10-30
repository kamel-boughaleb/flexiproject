<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import type { MiniUser } from '@/types';
import { Camera } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/** Choix d'une photo de profil, avec aperçu à la place de l'avatar par défaut. */
const props = defineProps<{ user: MiniUser; hint?: string }>();
const photo = defineModel<File | null>({ default: null });
const input = ref<HTMLInputElement>();
const preview = ref<string | null>(null);

watch(photo, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
    if (!file && input.value) input.value.value = '';
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

const shown = computed(() => ({ ...props.user, avatar: null }));
const pick = (e: Event) => (photo.value = (e.target as HTMLInputElement).files?.[0] ?? null);
</script>

<template>
    <div class="flex items-center gap-4">
        <button type="button" class="group relative rounded-full" title="Choisir une photo" @click="input?.click()">
            <UserAvatar :user="shown" :src="preview" size="xl" />
            <span
                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition group-hover:opacity-100"
            >
                <Camera class="size-6" />
            </span>
        </button>
        <div class="space-y-1.5">
            <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" @click="input?.click()">{{ photo ? 'Changer' : 'Ajouter ma photo' }}</Button>
                <Button v-if="photo" type="button" variant="ghost" size="sm" @click="photo = null">Retirer</Button>
            </div>
            <p class="text-xs text-muted-foreground">{{ hint ?? 'Facultatif. Sans photo, cet avatar vous représente.' }}</p>
        </div>
        <input ref="input" type="file" accept="image/*" class="hidden" @change="pick" />
    </div>
</template>
