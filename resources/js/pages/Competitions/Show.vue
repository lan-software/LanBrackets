<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { index } from '@/actions/App/Http/Controllers/CompetitionController';
import { useBracketCanvas } from '@/composables/useBracketCanvas';
import type { Stage } from '@/composables/useBracketCanvas';

interface Competition {
    id: number;
    name: string;
    slug: string | null;
    type: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
}

const props = defineProps<{
    competition: Competition;
    stages: Stage[];
}>();

const activeStageIndex = ref(0);
const stagesRef = computed(() => props.stages);

const {
    canvasContainer,
    canvas,
    activeStage,
    isDragging,
    stats,
    zoomPercent,
    fitToScreen,
    resetView,
    zoomIn,
    zoomOut,
    onMouseDown,
    onMouseMove,
    onMouseUp,
    onWheel,
    onTouchStart,
    onTouchMove,
    onTouchEnd,
} = useBracketCanvas({
    stages: stagesRef,
    activeStageIndex,
});
</script>

<template>
    <div>
        <Head :title="competition.name" />

        <div class="flex h-screen flex-col bg-[#0f172a]">
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-gray-800 px-4 py-3"
            >
                <div class="flex items-center gap-4">
                    <Link
                        :href="index.url()"
                        class="text-sm text-gray-400 hover:text-white"
                    >
                        {{ $t('competitions.backToList') }}
                    </Link>
                    <h1 class="text-lg font-bold text-white">
                        {{ competition.name }}
                    </h1>
                    <span
                        class="rounded bg-gray-700 px-2 py-0.5 text-xs text-gray-300"
                    >
                        {{ competition.type }}
                    </span>
                </div>

                <div class="flex items-center gap-3 text-sm text-gray-400">
                    <span>{{
                        $t('competitions.matchesProgress', {
                            finished: stats.finished,
                            total: stats.total,
                        })
                    }}</span>
                    <span v-if="stats.ready > 0" class="text-yellow-400">
                        {{
                            $t('competitions.matchesReady', {
                                count: stats.ready,
                            })
                        }}
                    </span>
                </div>
            </div>

            <!-- Stage tabs -->
            <div
                v-if="stages.length > 1"
                class="flex gap-1 border-b border-gray-800 px-4 py-2"
            >
                <button
                    v-for="(stage, idx) in stages"
                    :key="stage.id"
                    @click="
                        activeStageIndex = idx;
                        $nextTick(fitToScreen);
                    "
                    class="rounded px-3 py-1 text-sm transition"
                    :class="
                        idx === activeStageIndex
                            ? 'bg-blue-600 text-white'
                            : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                    "
                >
                    {{ stage.name }}
                </button>
            </div>

            <!-- Canvas -->
            <div ref="canvasContainer" class="relative flex-1 overflow-hidden">
                <canvas
                    ref="canvas"
                    class="h-full w-full"
                    :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
                    @mousedown="onMouseDown"
                    @mousemove="onMouseMove"
                    @mouseup="onMouseUp"
                    @mouseleave="onMouseUp"
                    @wheel="onWheel"
                    @touchstart.passive="onTouchStart"
                    @touchmove="onTouchMove"
                    @touchend="onTouchEnd"
                />

                <!-- Controls -->
                <div class="absolute right-4 bottom-4 flex flex-col gap-1">
                    <button
                        @click="zoomIn"
                        class="rounded bg-gray-800/80 px-3 py-1.5 text-sm text-white backdrop-blur hover:bg-gray-700"
                    >
                        {{ $t('bracket.zoomIn') }}
                    </button>
                    <div
                        class="rounded bg-gray-800/80 px-2 py-1 text-center text-xs text-gray-400 backdrop-blur"
                    >
                        {{ zoomPercent }}%
                    </div>
                    <button
                        @click="zoomOut"
                        class="rounded bg-gray-800/80 px-3 py-1.5 text-sm text-white backdrop-blur hover:bg-gray-700"
                    >
                        {{ $t('bracket.zoomOut') }}
                    </button>
                    <button
                        @click="fitToScreen"
                        class="mt-1 rounded bg-gray-800/80 px-3 py-1.5 text-xs text-gray-400 backdrop-blur hover:bg-gray-700 hover:text-white"
                    >
                        {{ $t('bracket.fitToScreen') }}
                    </button>
                    <button
                        @click="resetView"
                        class="rounded bg-gray-800/80 px-3 py-1.5 text-xs text-gray-400 backdrop-blur hover:bg-gray-700 hover:text-white"
                    >
                        {{ $t('bracket.resetView') }}
                    </button>
                </div>

                <!-- Legend -->
                <div
                    class="absolute bottom-4 left-4 flex flex-col gap-1.5 rounded bg-gray-800/80 p-3 text-xs text-gray-400 backdrop-blur"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block h-3 w-3 rounded border-2 border-yellow-400"
                        ></span>
                        {{ $t('bracket.legendReadyToPlay') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block h-3 w-3 rounded bg-green-500"
                        ></span>
                        {{ $t('bracket.legendWinner') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <span>🥇🥈🥉</span>
                        {{ $t('bracket.legendPlacements') }}
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="!activeStage || activeStage.matches.length === 0"
                    class="absolute inset-0 flex items-center justify-center"
                >
                    <p class="text-gray-500">
                        {{ $t('competitions.noMatchesForStage') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
