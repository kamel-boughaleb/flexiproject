<script setup lang="ts">
import { toDate } from '@/lib/labels';
import type { Project, Section, Task } from '@/types';
import { router } from '@inertiajs/vue3';
import { addDays, differenceInCalendarDays, eachDayOfInterval, format, isToday, isWeekend, min, startOfDay, startOfWeek } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{ project: Project; sections: Section[]; tasks: Task[]; canEdit: boolean }>();
const emit = defineEmits<{ open: [id: number] }>();

const DAY = 32;
const SPAN = 56;

const firstDate = min([new Date(), toDate(props.project.start_date) ?? new Date()]);
const start = ref(startOfWeek(addDays(firstDate, -3), { weekStartsOn: 1 }));
const days = computed(() => eachDayOfInterval({ start: start.value, end: addDays(start.value, SPAN - 1) }));
const offset = (d: Date) => differenceInCalendarDays(startOfDay(d), start.value);

// Glisser une barre pour décaler la tâche, ou sa poignée droite pour changer l'échéance.
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
    const right = Math.min(SPAN - 1, Math.max(a, b));
    if (right < 0 || left > SPAN - 1) return null;
    return { left: left * DAY + 2, width: Math.max((right - left + 1) * DAY - 4, 20) };
}

function startDrag(event: PointerEvent, task: Task, mode: 'move' | 'resize') {
    if (!props.canEdit) return;
    drag.value = { task, mode, x: event.clientX, delta: 0 };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', endDrag, { once: true });
}
function onMove(event: PointerEvent) {
    if (drag.value) drag.value.delta = Math.round((event.clientX - drag.value.x) / DAY);
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
    return d && offset(d) >= 0 && offset(d) < SPAN ? offset(d) * DAY : null;
};
const todayX = computed(() => {
    const o = offset(new Date());
    return o >= 0 && o < SPAN ? o * DAY + DAY / 2 : null;
});
const forSection = (id: number) => props.tasks.filter((t) => t.section_id === id);
</script>

<template>
    <div class="p-6">
        <div class="mb-3 flex items-center gap-2">
            <button class="rounded p-1.5 hover:bg-muted" aria-label="Période précédente" @click="start = addDays(start, -14)">
                <ChevronLeft class="size-4" />
            </button>
            <span class="text-sm font-medium"
                >{{ format(days[0], 'd MMM', { locale: fr }) }} – {{ format(days[SPAN - 1], 'd MMM yyyy', { locale: fr }) }}</span
            >
            <button class="rounded p-1.5 hover:bg-muted" aria-label="Période suivante" @click="start = addDays(start, 14)">
                <ChevronRight class="size-4" />
            </button>
            <button class="rounded px-2 py-1 text-xs hover:bg-muted" @click="start = startOfWeek(addDays(new Date(), -3), { weekStartsOn: 1 })">
                Aujourd'hui
            </button>
            <span v-if="canEdit" class="ml-auto text-xs text-muted-foreground"
                >Glissez une barre pour la décaler, ou son bord droit pour changer l'échéance.</span
            >
        </div>

        <div class="select-none overflow-x-auto rounded-lg border">
            <div class="flex" :style="{ minWidth: `${256 + SPAN * DAY}px` }">
                <div class="w-64 shrink-0 border-r">
                    <div class="h-12 border-b bg-muted/50" />
                    <template v-for="section in sections" :key="section.id">
                        <div class="h-8 border-b bg-muted/30 px-3 py-1.5 text-xs font-semibold">{{ section.name }}</div>
                        <button
                            v-for="t in forSection(section.id)"
                            :key="t.id"
                            class="flex h-9 w-full items-center gap-2 border-b px-3 text-left text-sm hover:bg-muted/40"
                            @click="emit('open', t.id)"
                        >
                            <UserAvatar :user="t.assignee" />
                            <span class="truncate" :class="{ 'text-muted-foreground line-through': t.completed }">{{ t.title }}</span>
                        </button>
                    </template>
                </div>

                <div class="relative" :style="{ width: `${SPAN * DAY}px` }">
                    <div class="flex h-12 border-b bg-muted/50">
                        <div
                            v-for="(d, i) in days"
                            :key="d.toISOString()"
                            :style="{ width: `${DAY}px` }"
                            class="flex shrink-0 flex-col items-center justify-center text-[10px]"
                            :class="isWeekend(d) ? 'text-muted-foreground/50' : 'text-muted-foreground'"
                        >
                            <span v-if="i === 0 || d.getDate() === 1" class="font-semibold capitalize text-foreground">{{
                                format(d, 'MMM', { locale: fr })
                            }}</span>
                            <span v-else>{{ format(d, 'EEEEE', { locale: fr }) }}</span>
                            <span :class="{ 'rounded bg-orange-500 px-1 font-semibold text-white': isToday(d) }">{{ format(d, 'd') }}</span>
                        </div>
                    </div>
                    <div class="pointer-events-none absolute inset-0 top-12 flex">
                        <div
                            v-for="d in days"
                            :key="d.toISOString()"
                            :style="{ width: `${DAY}px` }"
                            class="shrink-0 border-r border-border/40"
                            :class="{ 'bg-muted/40': isWeekend(d) }"
                        />
                    </div>
                    <div
                        v-if="todayX !== null"
                        class="pointer-events-none absolute bottom-0 top-12 w-0.5 bg-orange-500/60"
                        :style="{ left: `${todayX}px` }"
                    />
                    <div
                        v-if="marker(project.start_date) !== null"
                        title="Début du projet"
                        class="pointer-events-none absolute bottom-0 top-12 border-l-2 border-dashed border-emerald-400"
                        :style="{ left: `${marker(project.start_date)}px` }"
                    />
                    <div
                        v-if="marker(project.end_date) !== null"
                        title="Fin du projet"
                        class="pointer-events-none absolute bottom-0 top-12 border-l-2 border-dashed border-rose-400"
                        :style="{ left: `${marker(project.end_date)! + DAY}px` }"
                    />

                    <template v-for="section in sections" :key="section.id">
                        <div class="h-8 border-b" />
                        <div v-for="t in forSection(section.id)" :key="t.id" class="relative h-9 border-b">
                            <div
                                v-if="bar(t)"
                                :title="t.title"
                                class="absolute top-1.5 flex h-6 items-center rounded text-xs font-medium text-white shadow-sm hover:brightness-110"
                                :class="[{ 'opacity-50': t.completed }, canEdit ? 'cursor-grab' : 'cursor-pointer']"
                                :style="{
                                    left: `${bar(t)!.left}px`,
                                    width: `${bar(t)!.width}px`,
                                    backgroundColor: t.assignee?.color ?? project.color,
                                }"
                                @pointerdown.prevent="startDrag($event, t, 'move')"
                                @click="!canEdit && emit('open', t.id)"
                            >
                                <span class="truncate px-2">{{ t.title }}</span>
                                <span
                                    v-if="canEdit"
                                    class="absolute inset-y-0 right-0 w-2 cursor-ew-resize rounded-r bg-black/10"
                                    @pointerdown.stop.prevent="startDrag($event, t, 'resize')"
                                />
                            </div>
                            <span v-else class="absolute left-2 top-2.5 text-[11px] text-muted-foreground/60">Non planifiée</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
