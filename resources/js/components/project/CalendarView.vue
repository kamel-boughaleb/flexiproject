<script setup lang="ts">
import SegmentedControl from '@/components/design/SegmentedControl.vue';
import { PRIORITIES, isOverdue, toDate } from '@/lib/labels';
import type { Project, Task } from '@/types';
import {
    addDays,
    addMonths,
    addWeeks,
    differenceInCalendarDays,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    isSameMonth,
    isToday,
    isWeekend,
    startOfDay,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import { fr } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PriorityBadge from './PriorityBadge.vue';

const props = defineProps<{ project: Project; tasks: Task[] }>();
const emit = defineEmits<{ open: [id: number] }>();

const WEEKDAYS = ['LUN.', 'MAR.', 'MER.', 'JEU.', 'VEN.', 'SAM.', 'DIM.'];
const MAX_LANES = 3;
const week = { weekStartsOn: 1 } as const;

const scale = ref<'week' | 'month'>('month');
const showDone = ref(true);
const cursor = ref(startOfDay(new Date()));

function move(step: number) {
    cursor.value = scale.value === 'month' ? addMonths(cursor.value, step) : addWeeks(cursor.value, step);
}
const title = computed(() =>
    scale.value === 'month'
        ? format(cursor.value, 'MMMM yyyy', { locale: fr })
        : `${format(startOfWeek(cursor.value, week), 'd MMM', { locale: fr })} – ${format(endOfWeek(cursor.value, week), 'd MMM yyyy', { locale: fr })}`,
);

const visible = computed(() => props.tasks.filter((t) => showDone.value || !t.completed));
/** Période d'une tâche : de sa date de début à son échéance (une seule des deux suffit). */
function span(t: Task) {
    const start = toDate(t.start_date) ?? toDate(t.due_date);
    const end = toDate(t.due_date) ?? start;
    if (!start || !end) return null;
    return start <= end ? { start, end } : { start: end, end };
}

/** Couleurs des barres : terminée, en retard, puis selon la priorité. */
function tone(t: Task) {
    if (t.completed) return 'bg-app-hover text-ink-soft line-through';
    if (isOverdue(t.due_date, t.completed)) return 'bg-brand-tint text-brand-active';
    return t.priority === 'none' ? 'bg-app-hover text-app-strong' : PRIORITIES[t.priority].class;
}
function dot(t: Task) {
    if (t.completed) return 'bg-success';
    if (isOverdue(t.due_date, t.completed)) return 'bg-brand-active';
    return PRIORITIES[t.priority].dot;
}

/** Semaines affichées, avec les barres des tâches coupées à la semaine et rangées sur des lignes libres. */
const weeks = computed(() => {
    const first = scale.value === 'month' ? startOfWeek(startOfMonth(cursor.value), week) : startOfWeek(cursor.value, week);
    const last = scale.value === 'month' ? endOfWeek(endOfMonth(cursor.value), week) : endOfWeek(cursor.value, week);
    const result = [];
    for (let monday = first; monday <= last; monday = addDays(monday, 7)) {
        const sunday = addDays(monday, 6);
        const lanes: number[][] = [];
        const bars = visible.value
            .map((t) => ({ t, s: span(t) }))
            .filter(({ s }) => s && s.start <= sunday && s.end >= monday)
            .sort((a, b) => a.s!.start.getTime() - b.s!.start.getTime() || b.s!.end.getTime() - a.s!.end.getTime())
            .map(({ t, s }) => {
                const from = Math.max(0, differenceInCalendarDays(s!.start, monday));
                const to = Math.min(6, differenceInCalendarDays(s!.end, monday));
                let lane = lanes.findIndex((cells) => cells.every((c) => c < from || c > to));
                if (lane === -1) lane = lanes.push([]) - 1;
                for (let c = from; c <= to; c++) lanes[lane].push(c);
                return { t, from, to, lane, cutLeft: s!.start < monday, cutRight: s!.end > sunday };
            });
        const hidden = Array.from({ length: 7 }, (_, i) => bars.filter((b) => b.lane >= MAX_LANES && b.from <= i && b.to >= i).length);
        result.push({
            key: monday.toISOString(),
            days: eachDayOfInterval({ start: monday, end: sunday }),
            bars: bars.filter((b) => b.lane < MAX_LANES),
            hidden,
        });
    }
    return result;
});

const EDGES = { none: 'var(--app-icon-muted)', low: 'var(--secondary-500)', medium: 'var(--warning)', high: 'var(--primary-500)' };
const edge = (t: Task) => (t.completed ? 'var(--success)' : EDGES[t.priority]);

// Mobile : mini-mois avec points et agenda du jour sélectionné.
const selectedDay = ref(startOfDay(new Date()));
const monthDays = computed(() =>
    eachDayOfInterval({ start: startOfWeek(startOfMonth(cursor.value), week), end: endOfWeek(endOfMonth(cursor.value), week) }),
);
const tasksOn = (day: Date) =>
    visible.value.filter((t) => {
        const s = span(t);
        return s && s.start <= day && s.end >= day;
    });
const agenda = computed(() => tasksOn(selectedDay.value));
</script>

<template>
    <div class="flex min-h-full flex-col">
        <div class="flex flex-wrap items-center gap-2.5 px-4 py-3.5 md:px-7">
            <button
                type="button"
                class="h-9 rounded-full border border-app-border-strong px-4 text-sm font-bold text-app-strong hover:bg-app-hover"
                @click="((cursor = startOfDay(new Date())), (selectedDay = startOfDay(new Date())))"
            >
                Aujourd'hui
            </button>
            <div class="flex gap-0.5">
                <button
                    class="flex size-9 items-center justify-center rounded-full hover:bg-app-hover"
                    aria-label="Période précédente"
                    @click="move(-1)"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    class="flex size-9 items-center justify-center rounded-full hover:bg-app-hover"
                    aria-label="Période suivante"
                    @click="move(1)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </div>
            <h2 class="text-[22px] font-bold first-letter:uppercase" aria-live="polite">{{ title }}</h2>
            <div class="ml-auto flex flex-wrap items-center gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-[13px] font-semibold text-ink-soft">
                    <input v-model="showDone" type="checkbox" class="size-4 rounded accent-[var(--primary-active)]" />
                    Afficher les tâches terminées
                </label>
                <SegmentedControl
                    v-model="scale"
                    class="max-md:hidden"
                    label="Échelle"
                    size="sm"
                    :options="[
                        { value: 'week', label: 'Semaine' },
                        { value: 'month', label: 'Mois' },
                    ]"
                />
            </div>
        </div>

        <!-- Desktop : grille du mois (ou de la semaine) -->
        <div class="flex-1 overflow-auto px-7 pb-7 max-md:hidden">
            <div class="min-w-[760px] overflow-hidden rounded-card border border-app-border">
                <div class="grid grid-cols-7 border-b border-app-border bg-app-surface">
                    <div v-for="d in WEEKDAYS" :key="d" class="px-3 py-2.5 text-xs font-bold tracking-[.03em] text-ink-soft">{{ d }}</div>
                </div>
                <div
                    v-for="w in weeks"
                    :key="w.key"
                    class="grid grid-cols-7 border-b border-app-border last:border-b-0"
                    :style="{
                        gridTemplateRows: `36px repeat(${MAX_LANES}, 28px) minmax(18px, 1fr)`,
                        minHeight: scale === 'week' ? '420px' : '134px',
                    }"
                >
                    <div
                        v-for="(day, i) in w.days"
                        :key="day.toISOString()"
                        class="px-2.5 py-2"
                        :class="[
                            i < 6 ? 'border-r border-app-border' : '',
                            isToday(day) ? 'bg-brand-wash' : isWeekend(day) ? 'bg-app-surface' : 'bg-background',
                        ]"
                        :style="{ gridRow: '1 / -1', gridColumn: i + 1 }"
                    >
                        <span
                            class="inline-flex h-[26px] min-w-[26px] items-center justify-center rounded-full px-1.5 text-[13px] font-bold"
                            :class="
                                isToday(day)
                                    ? 'bg-brand-active text-white'
                                    : !isSameMonth(day, cursor) && scale === 'month'
                                      ? 'text-app-muted'
                                      : 'text-app-strong'
                            "
                        >
                            {{ day.getDate() === 1 ? format(day, 'd MMM', { locale: fr }) : format(day, 'd') }}
                        </span>
                        <span v-if="w.hidden[i]" class="mt-[94px] block text-xs font-bold text-ink-soft">+ {{ w.hidden[i] }} autre(s)</span>
                    </div>
                    <button
                        v-for="b in w.bars"
                        :key="b.t.id"
                        type="button"
                        :title="b.t.title"
                        class="relative z-[1] my-0.5 flex h-6 items-center gap-1.5 overflow-hidden whitespace-nowrap px-2.5 text-left text-xs font-bold hover:brightness-95"
                        :class="[
                            tone(b.t),
                            b.cutLeft ? 'ml-0 rounded-l-none' : 'ml-1.5 rounded-l-lg',
                            b.cutRight ? 'mr-0 rounded-r-none' : 'mr-1.5 rounded-r-lg',
                        ]"
                        :style="{ gridColumn: `${b.from + 1} / ${b.to + 2}`, gridRow: b.lane + 2 }"
                        @click="emit('open', b.t.id)"
                    >
                        <span class="size-2 shrink-0 rounded-full" :class="dot(b.t)" />
                        <span class="truncate">{{ b.t.title }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile : mini-mois et agenda -->
        <div class="px-4 pb-6 md:hidden">
            <div class="grid grid-cols-7 gap-y-1 rounded-card bg-app-surface p-2 text-center">
                <span v-for="d in WEEKDAYS" :key="d" class="py-1 text-[11px] font-bold text-ink-soft">{{ d.charAt(0) }}</span>
                <button
                    v-for="day in monthDays"
                    :key="day.toISOString()"
                    type="button"
                    :aria-pressed="isSameDay(day, selectedDay)"
                    :aria-label="format(day, 'EEEE d MMMM', { locale: fr })"
                    class="mx-auto flex size-11 flex-col items-center justify-center rounded-full text-sm font-bold"
                    :class="
                        isSameDay(day, selectedDay)
                            ? 'bg-brand-active text-white'
                            : isToday(day)
                              ? 'text-brand-active'
                              : isSameMonth(day, cursor)
                                ? 'text-app-strong'
                                : 'text-app-muted'
                    "
                    @click="selectedDay = day"
                >
                    {{ format(day, 'd') }}
                    <span class="mt-0.5 flex h-1 gap-0.5">
                        <span
                            v-for="t in tasksOn(day).slice(0, 3)"
                            :key="t.id"
                            class="size-1 rounded-full"
                            :class="isSameDay(day, selectedDay) ? 'bg-white' : dot(t)"
                        />
                    </span>
                </button>
            </div>
            <h3 class="mb-2 mt-5 text-base font-bold first-letter:uppercase">{{ format(selectedDay, 'EEEE d MMMM', { locale: fr }) }}</h3>
            <ul class="space-y-2.5">
                <li v-for="t in agenda" :key="t.id">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center gap-3 rounded-task border-l-4 bg-background p-3.5 text-left shadow-soft"
                        :style="{ borderLeftColor: edge(t) }"
                        @click="emit('open', t.id)"
                    >
                        <span class="min-w-0 flex-1 truncate text-sm font-bold" :class="{ 'text-ink-soft line-through': t.completed }">{{
                            t.title
                        }}</span>
                        <PriorityBadge :priority="t.priority" />
                    </button>
                </li>
                <li v-if="!agenda.length" class="text-sm text-ink-soft">Aucune tâche ce jour-là.</li>
            </ul>
        </div>
    </div>
</template>
