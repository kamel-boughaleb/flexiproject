<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Camera } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ProjectAvatar from './ProjectAvatar.vue';

/**
 * Avatar d'un projet : une image envoyée, sinon l'initiale du projet sur sa couleur.
 * (Les emojis déjà choisis auparavant restent affichés jusqu'à ce qu'on les retire.)
 */
const props = defineProps<{ name: string; color: string; currentImage?: string | null; disabled?: boolean }>();
const icon = defineModel<string>('icon', { default: '' });
const image = defineModel<File | null>('image', { default: null });
const removeImage = defineModel<boolean>('removeImage', { default: false });

const input = ref<HTMLInputElement>();
const preview = ref<string | null>(null);
watch(image, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
    if (!file && input.value) input.value.value = '';
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

const shownImage = computed(() => preview.value ?? (removeImage.value ? null : (props.currentImage ?? null)));
const hasImage = computed(() => !!shownImage.value);
const removable = computed(() => hasImage.value || !!icon.value);

function pick(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    image.value = file;
    removeImage.value = false;
}
function clearImage() {
    image.value = null;
    icon.value = '';
    if (props.currentImage) removeImage.value = true;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-4">
            <button type="button" class="group relative rounded-2xl" title="Choisir une image" :disabled="disabled" @click="input?.click()">
                <ProjectAvatar :project="{ name: name || '?', color, icon, image: null }" :src="shownImage" size="xl" />
                <span
                    v-if="!disabled"
                    class="absolute inset-0 flex items-center justify-center rounded-2xl bg-black/40 text-white opacity-0 transition group-hover:opacity-100"
                >
                    <Camera class="size-6" />
                </span>
            </button>
            <div class="space-y-1.5">
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="input?.click()">
                        {{ hasImage ? "Changer l'image" : 'Ajouter une image' }}
                    </Button>
                    <Button v-if="removable" type="button" variant="ghost" size="sm" :disabled="disabled" @click="clearImage">Retirer</Button>
                </div>
                <p class="text-xs text-ink-soft">Sans image, l'initiale du projet sur sa couleur.</p>
            </div>
        </div>
        <input ref="input" type="file" accept="image/*" class="hidden" @change="pick" />
    </div>
</template>
