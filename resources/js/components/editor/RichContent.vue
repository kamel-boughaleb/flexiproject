<script setup lang="ts">
/** Affiche un texte riche déjà nettoyé par le serveur (App\Support\RichText). */
import { openRichLink } from '@/lib/links';

defineProps<{ html: string | null }>();
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html : HTML filtré à l'enregistrement par App\Support\RichText -->
    <div class="rich-text" @click="openRichLink" v-html="html ?? ''" />
</template>
