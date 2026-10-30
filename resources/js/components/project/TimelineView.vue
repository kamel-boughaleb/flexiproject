<script setup lang="ts">
import SegmentedControl from '@/components/design/SegmentedControl.vue';
import { PRIORITIES, isOverdue, toDate } from '@/lib/labels';
import type { Priority, Project, Section, Task } from '@/types';
import { router } from '@inertiajs/vue3';
import { addDays, differenceInCalendarDays, eachDayOfInterval, format, isMonday, isToday, isWeekend, min, startOfDay, startOfWeek } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Check, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{ project: Project; sections: Section[]; tasks: Task[]; canEdit: boolean }>();
const emit = defineEmits<{ open: [id: number] }>();

// Échelles : largeur d'un jour et nombre de jours affichés.
const SCALES = { days: { day: 34, span: 56, step: 14 }, weeks: { day: 14, span: 140, step: 28 }, months: { day: 5, span: 365, step: 91 } };
const scale = ref<keyof typeof SCALES>('days');
const DAY = computed(() => SCALES[scale.value].day);
const SPAN = computed(() => SCALES[scale.value].span);
const ROW = 46;
const NAME_COL = 260;

const firstDate = min([new Date(), toDate(props.project.start_date) ?? new Date()]);
const start = ref(startOfWeek(addDays(firstDate, -3), { weekStartsOn: 1 }));
const days = computed(() => eachDayOfInterval({ start: start.value, end: addDays(start.value, SPAN.value - 1) }));
const offset = (d: Date) => differenceInCalendarDays(startOfDay(d), start.value);
const goToday = () => (start.value = startOfWeek(addDays(new Date(), -3), { weekStartsOn: 1 }));

/** Bandeau des mois au-dessus des jours. */
const months = computed(() => {
    const result: { label: string; left: number; width: number }[] = [];
    days.value.forEach((d, i) => {
        if (i === 0 || d.getDate() === 1)
            result.push({ label: format(d, scale.value === 'months' ? 'MMM yyyy' : 'MMMM', { locale: fr }), left: i * DAY.value, width: 0 });
        result[result.length - 1].width += DAY.value;
    });
    return result;
});

// Glisser une barre pour décaler la tâche, ou son bord droit pour changer l'échéance.
const drag = ref<{ task: Task; mode: 'move' | 'resize'; x: number; delta: number } | null>(null);

function bar(task: Task) {
    const s = toDate(task.start_date) ?? toDate(task.due_date);
    const e = toDate(task.due_date) ?? s;
    if (!s || !e) return null;
    let a = offset(s);
    let b = offset(e);
    if (drag.value?.task.id === task.id) {
        if (drag.value.mode === 'move') a += drag.value.delta;
        b += drag.value.delta;
    }
    const left = Math.max(0, Math.min(a, b));
    const right = Math.min(SPAN.value - 1, Math.max(a, b));
    if (right < 0 || left > SPAN.value - 1) return null;
    // Dépassement d'échéance : hachures de l'échéance jusqu'à aujourd'hui.
    const late = isOverdue(task.due_date, task.completed) ? Math.min(SPAN.value - 1, offset(new Date())) - Math.max(a, b) : 0;
    return {
        left: left * DAY.value + 3,
        width: Math.max((right - left + 1) * DAY.value - 6, 24),
        lateFrom: (right + 1) * DAY.value - 3,
        lateWidth: late * DAY.value,
    };
}

function startDrag(event: PointerEvent, task: Task, mode: 'move' | 'resize') {
    if (!props.canEdit) return;
    drag.value = { task, mode, x: event.clientX, delta: 0 };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', endDrag, { once: true });
}
function onMove(event: PointerEvent) {
    if (drag.value) drag.value.delta = Math.round((event.clientX - drag.value.x) / DAY.value);
}
function endDrag() {
    window.removeEventListener('pointermove', onMove);
    const d = drag.value;
    drag.value = null;
    if (!d) return;
    if (d.delta === 0) return emit('open', d.task.id);
    const shift = (v: string | null) => (v ? format(addDays(toDate(v)!, d.delta), 'yyyy-MM-dd') : null);
    const data =
        d.mode === 'move'
            ? { start_date: shift(d.task.start_date), due_date: shift(d.task.due_date) }
            : { due_date: shift(d.task.due_date ?? d.task.start_date) };
    router.patch(route('tasks.update', d.task.id), data, { preserveScroll: true, preserveState: true });
}

const marker = (v: string | null) => {
    const d = toDate(v);
    return d && offset(d) >= 0 && offset(d) < SPAN.value ? offset(d) * DAY.value : null;
};
const todayX = computed(() => {
    const o = offset(new Date());
    return o >= 0 && o < SPAN.value ? o * DAY.value + DAY.value / 2 - 1 : null;
});
const forSection = (id: number) => props.tasks.filter((t) => t.section_id === id);

/** Barres en pilule colorées selon la priorité (gris-vert si terminée). */
function tone(t: Task) {
    if (t.completed) return 'bg-app-border text-ink-soft border-success';
    const p: Priority = t.priority === 'none' ? 'low' : t.priority;
    return [PRIORITIES[p].class, PRIORITIES[p].border];
}
const showDay = (d: Date) => scale.value === 'days' || (scale.value === 'weeks' && isMonday(d));
</script>

<template>
    <div class="flex min-h-full flex-col">
        <div class="flex flex-wrap items-center gap-2.5 px-4 py-3.5 md:px-7">
            <button
                type="button"
                class="h-9 rounded-full border border-app-border-strong px-4 text-sm font-bold text-app-strong hover:bg-app-hover"
                @click="goToday"
            >
                Aujourd'hui
            </button>
            <div class="flex gap-0.5">
                <button
                    class="flex size-9 items-center justify-center rounded-full hover:bg-app-hover"
                    aria-label="Période précédente"
                    @click="start = addDays(start, -SCALES[scale].step)"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    class="flex size-9 items-center justify-center rounded-full hover:bg-app-hover"
                    aria-label="Période suivante"
                    @click="start = addDays(start, SCALES[scale].step)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </div>
            <h2 class="text-lg font-bold">
                {{ format(days[0], 'd MMM', { locale: fr }) }} – {{ format(days[days.length - 1], 'd MMM yyyy', { locale: fr }) }}
            </h2>
            <div class="ml-auto flex flex-wrap items-center gap-3">
                <span v-for="p in ['low', 'medium', 'high'] as const" :key="p" class="flex items-center gap-1.5 text-xs font-bold text-ink-soft">
                    <span class="size-2.5 rounded-[3px]" :class="PRIORITIES[p].dot" />{{ PRIORITIES[p].label }}
                </span>
                <SegmentedControl
                    v-model="scale"
                    label="Échelle"
                    size="sm"
                    :options="[
                        { value: 'days', label: 'Jours' },
                        { value: 'weeks', label: 'Semaines' },
                        { value: 'months', label: 'Mois' },
                    ]"
                />
            </div>
        </div>
        <p v-if="canEdit" class="-mt-1 px-4 pb-2 text-xs text-ink-soft md:px-7">
            Glissez une barre pour la décaler, ou son bord droit pour changer l'échéance.
        </p>

        <div class="mx-4 mb-6 select-none overflow-x-auto rounded-card border border-app-border md:mx-7">
            <div class="relative" :style="{ width: `${NAME_COL + SPAN * DAY}px` }">
                <!-- En-tête -->
                <div class="sticky top-0 z-20 flex border-b border-app-border bg-background">
                    <div
                        class="sticky left-0 z-10 flex shrink-0 items-end border-r border-app-border bg-background px-4 pb-3 text-xs font-bold uppercase tracking-[.04em] text-ink-soft"
                        :style="{ width: `${NAME_COL}px` }"
                    >
                        Tâche
                    </div>
                    <div class="relative" :style="{ width: `${SPAN * DAY}px` }">
                        <div class="relative h-7">
                            <span
                                v-for="m in months"
                                :key="m.left"
                                class="absolute top-0 h-full overflow-hidden whitespace-nowrap border-l border-app-border px-2 pt-1.5 text-xs font-bold capitalize text-ink"
                                :style="{ left: `${m.left}px`, width: `${m.width}px` }"
                                >{{ m.label }}</span
                            >
                        </div>
                        <div class="flex h-10">
                            <div
                                v-for="d in days"
                                :key="d.toISOString()"
                                :style="{ width: `${DAY}px` }"
                                class="relative flex shrink-0 flex-col items-center justify-center"
                                :class="isWeekend(d) && scale === 'days' ? 'bg-app-bg text-app-muted' : 'text-ink-soft'"
                            >
                                <template v-if="showDay(d)">
                                    <span v-if="scale === 'days'" class="text-[10px] font-bold uppercase">{{
                                        format(d, 'EEEEE', { locale: fr })
                                    }}</span>
                                    <span
                                        class="flex size-[22px] items-center justify-center whitespace-nowrap rounded-full text-xs font-bold"
                                        :class="isToday(d) ? 'bg-brand-active text-white' : 'text-app-strong'"
                                        >{{ format(d, 'd') }}</span
                                    >
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fond : week-ends, aujourd'hui, début et fin du projet -->
                <div class="pointer-events-none absolute bottom-0 top-[69px]" :style="{ left: `${NAME_COL}px`, width: `${SPAN * DAY}px` }">
                    <template v-if="scale === 'days'">
                        <div
                            v-for="(d, i) in days"
                            v-show="isWeekend(d)"
                            :key="d.toISOString()"
                            class="absolute inset-y-0 bg-app-bg"
                            :style="{ left: `${i * DAY}px`, width: `${DAY}px` }"
                        />
                    </template>
                    <div v-if="todayX !== null" class="absolute inset-y-0 z-10 w-0.5 bg-brand-active" :style="{ left: `${todayX}px` }" />
                    <div
                        v-if="marker(project.start_date) !== null"
                        title="Début du projet"
                        class="absolute inset-y-0 border-l-2 border-dashed border-success"
                        :style="{ left: `${marker(project.start_date)}px` }"
                    />
                    <div
                        v-if="marker(project.end_date) !== null"
                        title="Fin du projet"
                        class="absolute inset-y-0 border-l-2 border-dashed border-brand"
                        :style="{ left: `${marker(project.end_date)! + DAY}px` }"
                    />
                </div>

                <template v-for="section in sections" :key="section.id">
                    <div class="bg-app-surface/90 relative flex h-10 border-b border-app-border">
                        <div class="sticky left-0 flex items-center gap-2 bg-app-surface px-4 text-sm font-bold" :style="{ width: `${NAME_COL}px` }">
                            {{ section.name }} <span class="text-xs font-semibold text-ink-soft">{{ forSection(section.id).length }}</span>
                        </div>
                    </div>
                    <div
                        v-for="t in forSection(section.id)"
                        :key="t.id"
                        class="relative flex border-b border-app-border"
                        :style="{ height: `${ROW}px` }"
                    >
                        <button
                            type="button"
                            class="sticky left-0 z-10 flex shrink-0 items-center gap-2.5 border-r border-app-border bg-background px-4 text-left hover:bg-app-surface"
                            :style="{ width: `${NAME_COL}px` }"
                            @click="emit('open', t.id)"
                        >
                            <span
                                class="flex size-[18px] shrink-0 items-center justify-center rounded-full"
                                :class="t.completed ? 'bg-success text-white' : 'border-[1.5px] border-app-muted text-transparent'"
                                ><Check class="size-3" :stroke-width="3"
                            /></span>
                            <span class="truncate text-sm font-semibold" :class="{ 'text-ink-soft line-through': t.completed }">{{ t.title }}</span>
                        </button>
                        <div class="relative" :style="{ width: `${SPAN * DAY}px` }">
                            <template v-if="bar(t)">
                                <span
                                    v-if="bar(t)!.lateWidth > 0"
                                    title="Échéance dépassée"
                                    class="absolute top-3.5 h-[18px] rounded-r-full border-2 border-l-0 border-dashed border-brand-active bg-[repeating-linear-gradient(135deg,var(--primary-tint)_0_6px,var(--white)_6px_10px)]"
                                    :style="{ left: `${bar(t)!.lateFrom}px`, width: `${bar(t)!.lateWidth}px` }"
                                />
                                <div
                                    :title="t.title"
                                    class="absolute top-2 flex h-[30px] items-center gap-1.5 overflow-hidden whitespace-nowrap rounded-full border-2 py-0 pl-[3px] pr-2.5 text-xs font-bold hover:shadow-hover"
                                    :class="[tone(t), canEdit ? 'cursor-grab' : 'cursor-pointer']"
                                    :style="{ left: `${bar(t)!.left}px`, width: `${bar(t)!.width}px` }"
                                    @pointerdown.prevent="startDrag($event, t, 'move')"
                                    @click="!canEdit && emit('open', t.id)"
                                >
                                    <UserAvatar v-if="t.assignee" :user="t.assignee" size="xs" class="!size-[22px]" />
                                    <span class="truncate" :class="{ 'line-through': t.completed }">{{ t.title }}</span>
                                    <span v-if="t.subtasks_count" class="shrink-0 opacity-80"
                                        >· {{ t.subtasks_done_count ?? 0 }}/{{ t.subtasks_count }}</span
                                    >
                                    <span
                                        v-if="canEdit"
                                        class="absolute inset-y-0 right-0 w-2.5 cursor-ew-resize"
                                        @pointerdown.stop.prevent="startDrag($event, t, 'resize')"
                                    />
                                </div>
                            </template>
                            <span v-else class="absolute left-3 top-3.5 text-xs font-semibold text-app-muted">Non planifiée</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
