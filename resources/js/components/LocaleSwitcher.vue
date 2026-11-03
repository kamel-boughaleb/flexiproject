<script setup lang="ts">
import { locale } from '@/lib/i18n';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';

// Choix de la langue avant connexion : la langue retenue sera celle du compte à l'inscription.
const page = usePage<SharedData>();

const choose = (value: string) => {
    if (value === locale.value) return;
    router.post(route('locale.update'), { locale: value }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div class="inline-flex rounded-full bg-app-hover p-1 text-xs font-bold" role="group" :aria-label="$t('Langue')">
        <button
            v-for="(label, code) in page.props.locales"
            :key="code"
            type="button"
            :lang="code"
            class="rounded-full px-3 py-1.5 transition-colors"
            :class="code === locale ? 'bg-background text-app-strong shadow-soft' : 'text-ink-soft hover:text-app-strong'"
            :aria-pressed="code === locale"
            @click="choose(code)"
        >
            {{ label }}
        </button>
    </div>
</template>
