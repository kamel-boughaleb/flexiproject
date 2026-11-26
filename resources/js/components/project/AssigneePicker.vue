<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Member, MiniUser } from '@/types';
import { router } from '@inertiajs/vue3';
import { Check, ChevronLeft, Plus, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserAvatar from './UserAvatar.vue';

/**
 * Choix du responsable : liste des membres avec leur avatar, « Personne », et « + Nouveau » pour confier la tâche
 * à un collègue de l'équipe qui n'est pas encore membre du projet (il y est ajouté automatiquement).
 */
const props = defineProps<{ projectId: number; members: Member[]; modelValue: number | null; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>();

const open = ref(false);
const adding = ref(false);
const candidates = ref<MiniUser[] | null>(null);
const query = ref('');

const current = computed(() => props.members.find((m) => m.id === props.modelValue) ?? null);
const choices = computed(() => props.members.filter((m) => !m.pending && !m.deactivated));
const filtered = computed(() => {
    const q = query.value.trim().toLocaleLowerCase();
    return (candidates.value ?? []).filter((c) => !q || c.name.toLocaleLowerCase().includes(q));
});

function onOpen(value: boolean) {
    open.value = value;
    if (!value) {
        adding.value = false;
        query.value = '';
    }
}

async function showCandidates(e: Event) {
    // Reste ouvert : la liste passe aux collègues hors du projet.
    e.preventDefault();
    adding.value = true;
    candidates.value = null;
    try {
        const response = await fetch(route('members.candidates', props.projectId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        candidates.value = response.ok ? await response.json() : [];
    } catch {
        candidates.value = [];
    }
}

function back(e: Event) {
    e.preventDefault();
    adding.value = false;
    query.value = '';
}

function pick(id: number | null) {
    if (id !== props.modelValue) emit('update:modelValue', id);
}

function addAndAssign(user: MiniUser) {
    router.post(
        route('members.join', props.projectId),
        { user_id: user.id },
        { preserveScroll: true, preserveState: true, onSuccess: () => emit('update:modelValue', user.id) },
    );
}
</script>

<template>
    <DropdownMenu :open="open" @update:open="onOpen">
        <DropdownMenuTrigger as-child :disabled="disabled">
            <button
                type="button"
                class="inline-flex max-w-full items-center gap-2 rounded-full py-0.5 pl-0.5 pr-3 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring enabled:hover:bg-app-hover disabled:cursor-default"
                :aria-label="$t('Responsable')"
            >
                <UserAvatar :user="current" size="sm" />
                <span class="truncate text-sm font-bold" :class="{ 'font-semibold text-ink-soft': !current }">{{
                    current?.name ?? $t('Personne')
                }}</span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="z-[60] w-64">
            <template v-if="!adding">
                <div class="max-h-72 overflow-y-auto">
                    <DropdownMenuItem class="gap-2" @select="pick(null)">
                        <span class="flex size-6 items-center justify-center rounded-full bg-app-hover text-ink-soft"
                            ><UserX class="size-3.5"
                        /></span>
                        <span class="flex-1 text-ink-soft">{{ $t('Personne') }}</span>
                        <Check v-if="modelValue === null" class="size-4 text-brand-active" />
                    </DropdownMenuItem>
                    <DropdownMenuItem v-for="m in choices" :key="m.id" class="gap-2" @select="pick(m.id)">
                        <UserAvatar :user="m" size="xs" />
                        <span class="flex-1 truncate font-semibold">{{ m.name }}</span>
                        <Check v-if="m.id === modelValue" class="size-4 text-brand-active" />
                    </DropdownMenuItem>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem class="gap-2 font-bold text-brand-active" @select="showCandidates">
                    <span class="flex size-6 items-center justify-center rounded-full border-[1.5px] border-dashed border-current"
                        ><Plus class="size-3.5"
                    /></span>
                    {{ $t('Nouveau') }}
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem class="gap-1.5 text-xs font-bold text-ink-soft" @select="back">
                    <ChevronLeft class="size-3.5" /> {{ $t('Ajouter au projet et confier la tâche') }}
                </DropdownMenuItem>
                <input
                    v-model="query"
                    type="search"
                    :placeholder="$t('Rechercher un collègue')"
                    class="mx-1 my-1 w-[calc(100%-0.5rem)] rounded-md border border-app-border bg-background px-2 py-1.5 text-sm outline-none focus:border-info"
                    @keydown.stop
                />
                <div class="max-h-64 overflow-y-auto">
                    <p v-if="candidates === null" class="px-2 py-1.5 text-sm text-ink-soft">{{ $t('Chargement…') }}</p>
                    <DropdownMenuItem v-for="c in filtered" :key="c.id" class="gap-2" @select="addAndAssign(c)">
                        <UserAvatar :user="c" size="xs" />
                        <span class="truncate font-semibold">{{ c.name }}</span>
                    </DropdownMenuItem>
                    <p v-if="candidates !== null && !filtered.length" class="px-2 py-1.5 text-sm text-ink-soft">
                        {{ candidates.length ? $t('Aucun collègue trouvé.') : $t("Toute l'équipe fait déjà partie du projet.") }}
                    </p>
                </div>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
