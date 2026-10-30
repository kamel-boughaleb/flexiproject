<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Key = 'assigned' | 'mentions' | 'comments' | 'updates' | 'daily_digest';
type Preferences = Record<Key, boolean>;

const props = defineProps<{ preferences: Preferences }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Notifications', href: '/settings/notifications' }];

const OPTIONS: { key: Key; label: string; description: string }[] = [
    { key: 'assigned', label: 'Une tâche m’est assignée', description: 'Un e-mail dès qu’un collaborateur (ou une règle) vous confie une tâche.' },
    {
        key: 'mentions',
        label: 'On me mentionne avec « @ »',
        description: 'Quand un collaborateur vous cite dans la description ou un commentaire d’une tâche.',
    },
    {
        key: 'comments',
        label: 'Nouveaux commentaires',
        description: 'Sur les tâches dont vous êtes responsable, que vous avez créées, modifiées ou commentées.',
    },
    {
        key: 'updates',
        label: 'Modifications de tâches',
        description: 'Changement de titre, d’échéance, de section, de priorité… sur ces mêmes tâches.',
    },
    {
        key: 'daily_digest',
        label: 'Récapitulatif quotidien',
        description: 'Chaque matin à 8 h, la liste de vos tâches en cours : en retard, du jour et à venir.',
    },
];

/** Niveaux prédéfinis : on peut ensuite affiner case par case. */
const LEVELS: { label: string; values: Preferences }[] = [
    { label: 'Tout', values: { assigned: true, mentions: true, comments: true, updates: true, daily_digest: true } },
    { label: 'L’essentiel', values: { assigned: true, mentions: true, comments: true, updates: false, daily_digest: true } },
    { label: 'Aucun e-mail', values: { assigned: false, mentions: false, comments: false, updates: false, daily_digest: false } },
];

const form = useForm<Preferences>({ ...props.preferences });

const currentLevel = computed(() => LEVELS.find((l) => OPTIONS.every((o) => l.values[o.key] === form[o.key]))?.label ?? 'Personnalisé');

function save() {
    form.patch(route('notifications.settings.update'), { preserveScroll: true });
}

function toggle(key: Key, value: boolean) {
    form[key] = value;
    save();
}

function applyLevel(values: Preferences) {
    Object.assign(form, values);
    save();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notifications" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Notifications par e-mail"
                    description="Choisissez les e-mails que vous recevez. Les notifications restent toujours visibles dans la cloche de l’application."
                />

                <div class="space-y-2">
                    <Label>Niveau</Label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="level in LEVELS"
                            :key="level.label"
                            type="button"
                            size="sm"
                            :variant="currentLevel === level.label ? 'default' : 'outline'"
                            @click="applyLevel(level.values)"
                        >
                            {{ level.label }}
                        </Button>
                        <span v-if="currentLevel === 'Personnalisé'" class="self-center text-sm text-muted-foreground">Personnalisé</span>
                    </div>
                </div>

                <ul class="divide-y rounded-lg border">
                    <li v-for="option in OPTIONS" :key="option.key" class="flex items-start gap-3 p-4">
                        <Checkbox
                            :id="option.key"
                            class="mt-0.5"
                            :checked="form[option.key]"
                            @update:checked="(value: boolean) => toggle(option.key, value)"
                        />
                        <Label :for="option.key" class="grid cursor-pointer gap-1 font-normal">
                            <span class="font-medium">{{ option.label }}</span>
                            <span class="text-sm text-muted-foreground">{{ option.description }}</span>
                        </Label>
                    </li>
                </ul>

                <TransitionRoot
                    :show="form.recentlySuccessful"
                    enter="transition ease-in-out"
                    enter-from="opacity-0"
                    leave="transition ease-in-out"
                    leave-to="opacity-0"
                >
                    <p class="text-sm text-neutral-600">Enregistré</p>
                </TransitionRoot>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
