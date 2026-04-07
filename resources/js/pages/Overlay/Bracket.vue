<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useBracketCanvas, type Stage } from '@/composables/useBracketCanvas';

interface Competition {
    id: number;
    name: string;
    slug: string | null;
    type: string;
    status: string;
}

const props = defineProps<{
    competition: Competition;
    stages: Stage[];
    token: string;
}>();

const activeStageIndex = ref(0);
const stagesData = ref<Stage[]>(props.stages);
const stagesRef = computed(() => stagesData.value);

// Parse query params for stage selection and background
const urlParams = new URLSearchParams(window.location.search);
const stageParam = urlParams.get('stage');
if (stageParam !== null) {
    const idx = parseInt(stageParam, 10);
    if (!isNaN(idx) && idx >= 0 && idx < props.stages.length) {
        activeStageIndex.value = idx;
    }
}

const bgColor =
    urlParams.get('bg') === 'transparent' ? 'rgba(0,0,0,0)' : undefined;

const {
    canvasContainer,
    canvas,
    activeStage,
    isDragging,
    fitToScreen,
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
    bgColor,
});

// ─── Auto-polling for live updates ───

let pollInterval: ReturnType<typeof setInterval> | null = null;

async function pollData() {
    try {
        const response = await fetch(
            `/api/v1/overlay/competitions/${props.competition.id}?token=${encodeURIComponent(props.token)}`,
        );
        if (!response.ok) return;

        const data = await response.json();
        stagesData.value = data.stages;
    } catch {
        // Silently ignore polling errors
    }
}

onMounted(() => {
    pollInterval = setInterval(pollData, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div
        class="h-screen w-screen overflow-hidden"
        :style="
            bgColor === 'rgba(0,0,0,0)'
                ? 'background: transparent'
                : 'background: #0f172a'
        "
    >
        <div ref="canvasContainer" class="h-full w-full">
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
        </div>

        <!-- Empty state -->
        <div
            v-if="!activeStage || activeStage.matches.length === 0"
            class="absolute inset-0 flex items-center justify-center"
        >
            <p class="text-gray-500">No matches generated.</p>
        </div>
    </div>
</template>
