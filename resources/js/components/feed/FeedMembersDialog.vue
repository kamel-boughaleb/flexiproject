<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import type { MiniUser, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Check, Search, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Abonnés d'un fil : tout collaborateur peut y ajouter d'autres personnes.
 * Chacun peut se retirer ; le créateur du fil (ou un administrateur) peut retirer n'importe qui.
 */
const props = defineProps<{
    feedId: number;
    subscribers: MiniUser[];
    team: (MiniUser & { email: string })[];
    canManage: boolean;
}>();
const open = defineModel<boolean>('open', { required: true });
const me = usePage<SharedData>().props.auth.user;

const query = ref('');
const selected = ref<number[]>([]);
const saving = ref(false);
watch(open, (value) => {
    if (!value) return;
    query.value = '';
    selected.value = [];
});

const subscribedIds = computed(() => new Set(props.subscribers.map((s) => s.id)));
const normalize = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const candidates = computed(() => {
    const q = normalize(query.value.trim());
    return props.team.filter((u) => !subscribedIds.value.has(u.id) && (!q || normalize(`${u.name} ${u.email}`).includes(q)));
});

const toggle = (id: number) => (selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id]);

function add() {
    if (!selected.value.length) return;
    saving.value = true;
    router.post(
        route('feeds.members.store', props.feedId),
        { user_ids: selected.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []), onFinish: () => (saving.value = false) },
    );
}

const canRemove = (u: MiniUser) => props.canManage || u.id === me.id;
const remove = (u: MiniUser) =>
    (u.id === me.id || confirm(t('Désabonner :name de ce fil ?', { name: u.name }))) &&
    router.delete(route('feeds.members.destroy', [props.feedId, u.id]), { preserveScroll: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[90vh] flex-col gap-5 sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('Membres du fil') }}</DialogTitle>
                <DialogDescription>{{ $t('Les membres reçoivent chaque nouvelle actualité dans la cloche et par e-mail.') }}</DialogDescription>
            </DialogHeader>

            <div class="min-h-0 space-y-5 overflow-y-auto">
                <section>
                    <h3 class="mb-2 text-sm font-bold">{{ $t('Ajouter des membres') }}</h3>
                    <label class="relative block">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            v-model="query"
                            type="search"
                            :placeholder="$t('Rechercher un collaborateur')"
                            class="h-10 w-full rounded-field border-2 border-app-border-strong bg-background pl-9 pr-3 text-sm font-semibold outline-none focus:border-info focus:ring-4 focus:ring-info-tint"
                        />
                    </label>
                    <ul class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-app-border">
                        <li v-for="u in candidates" :key="u.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-app-hover"
                                :aria-pressed="selected.includes(u.id)"
                                @click="toggle(u.id)"
                            >
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded border-2"
                                    :class="selected.includes(u.id) ? 'border-brand-active bg-brand-active text-white' : 'border-app-border-strong'"
                                >
                                    <Check v-if="selected.includes(u.id)" class="size-3.5 stroke-[3]" />
                                </span>
                                <UserAvatar :user="u" size="xs" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold">{{ u.name }}</span>
                                    <span class="block truncate text-xs text-ink-soft">{{ u.email }}</span>
                                </span>
                            </button>
                        </li>
                        <li v-if="!candidates.length" class="px-3 py-4 text-center text-sm text-muted-foreground">
                            {{ query ? $t('Aucun collaborateur trouvé.') : $t('Toute l’équipe est déjà abonnée.') }}
                        </li>
                    </ul>
                    <div class="mt-3 flex justify-end">
                        <Button :disabled="!selected.length || saving" @click="add">
                            <UserPlus class="size-4" />
                            {{ selected.length ? $tc('Ajouter :count personne|Ajouter :count personnes', selected.length) : $t('Ajouter') }}
                        </Button>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-bold">{{ $tc(':count abonné|:count abonnés', subscribers.length) }}</h3>
                    <ul class="divide-y divide-app-border rounded-xl border border-app-border">
                        <li v-for="s in subscribers" :key="s.id" class="flex items-center gap-3 px-3 py-2 text-sm">
                            <UserAvatar :user="s" size="xs" />
                            <span class="min-w-0 flex-1 truncate font-semibold">
                                {{ s.name }}<span v-if="s.id === me.id" class="font-normal text-ink-soft"> ({{ $t('vous') }})</span>
                            </span>
                            <button
                                v-if="canRemove(s)"
                                type="button"
                                class="flex size-7 items-center justify-center rounded-md text-ink-soft hover:bg-app-hover hover:text-ink"
                                :title="s.id === me.id ? $t('Se désabonner') : $t('Retirer du fil')"
                                :aria-label="s.id === me.id ? $t('Se désabonner') : $t('Retirer du fil')"
                                @click="remove(s)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                        <li v-if="!subscribers.length" class="px-3 py-4 text-center text-sm text-muted-foreground">
                            {{ $t('Personne n’est encore abonné à ce fil.') }}
                        </li>
                    </ul>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
