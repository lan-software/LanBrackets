import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { Ref, ComputedRef } from 'vue';

// ─── Types ───

export interface MatchParticipant {
    slot: number;
    score: number | null;
    result: string | null;
    competition_participant_id: number;
    name: string;
}

export interface BracketMatch {
    id: number;
    round_number: number;
    sequence: number;
    status: string;
    bracket_side: string | null;
    is_ready: boolean;
    winner_participant_id: number | null;
    participants: MatchParticipant[];
}

export interface Stage {
    id: number;
    name: string;
    stage_type: string;
    status: string;
    matches: BracketMatch[];
}

export interface LayoutMatch {
    match: BracketMatch;
    x: number;
    y: number;
    section: string;
}

// ─── Constants ───

const MATCH_WIDTH = 220;
const MATCH_HEIGHT = 60;
const ROUND_GAP_X = 60;
const MATCH_GAP_Y = 20;
const SECTION_GAP_Y = 60;

const COLORS = {
    bg: '#0f172a',
    matchBg: '#1e293b',
    matchBorder: '#334155',
    matchReadyBorder: '#facc15',
    matchReadyGlow: 'rgba(250, 204, 21, 0.2)',
    matchFinishedBorder: '#475569',
    text: '#e2e8f0',
    textDim: '#94a3b8',
    textScore: '#cbd5e1',
    winner: '#22c55e',
    loser: '#ef4444',
    connection: '#475569',
    connectionActive: '#60a5fa',
    sectionLabel: '#64748b',
    gold: '#fbbf24',
    silver: '#9ca3af',
    bronze: '#d97706',
    readyPulse: '#facc15',
};

// ─── Layout Functions ───

function groupByRound(matches: BracketMatch[]): Record<number, BracketMatch[]> {
    const groups: Record<number, BracketMatch[]> = {};

    for (const m of matches) {
        if (!groups[m.round_number]) {
            groups[m.round_number] = [];
        }

        groups[m.round_number].push(m);
    }

    for (const key of Object.keys(groups)) {
        groups[Number(key)].sort((a, b) => a.sequence - b.sequence);
    }

    return groups;
}

function layoutSingleElimination(matches: BracketMatch[]): LayoutMatch[] {
    const rounds = groupByRound(matches);
    const roundNumbers = Object.keys(rounds)
        .map(Number)
        .sort((a, b) => a - b);
    const layout: LayoutMatch[] = [];

    const maxMatchesInRound = Math.max(
        ...roundNumbers.map((r) => rounds[r].length),
    );

    for (const roundNum of roundNumbers) {
        const roundIdx = roundNumbers.indexOf(roundNum);
        const roundMatches = rounds[roundNum];
        const totalHeight =
            maxMatchesInRound * (MATCH_HEIGHT + MATCH_GAP_Y) - MATCH_GAP_Y;
        const roundHeight =
            roundMatches.length * (MATCH_HEIGHT + MATCH_GAP_Y) - MATCH_GAP_Y;
        const startY = (totalHeight - roundHeight) / 2;

        for (let i = 0; i < roundMatches.length; i++) {
            const spacing = totalHeight / roundMatches.length;
            layout.push({
                match: roundMatches[i],
                x: roundIdx * (MATCH_WIDTH + ROUND_GAP_X),
                y: startY + i * spacing + (spacing - MATCH_HEIGHT) / 2,
                section: 'main',
            });
        }
    }

    return layout;
}

function layoutDoubleElimination(matches: BracketMatch[]): LayoutMatch[] {
    const wb = matches.filter((m) => m.bracket_side === 'winners');
    const lb = matches.filter((m) => m.bracket_side === 'losers');
    const gf = matches.filter((m) => m.bracket_side === 'grand_final');

    const layout: LayoutMatch[] = [];

    const wbRounds = groupByRound(wb);
    const wbRoundNumbers = Object.keys(wbRounds)
        .map(Number)
        .sort((a, b) => a - b);
    const wbMaxMatches = Math.max(
        ...wbRoundNumbers.map((r) => wbRounds[r].length),
        1,
    );
    const wbTotalHeight =
        wbMaxMatches * (MATCH_HEIGHT + MATCH_GAP_Y) - MATCH_GAP_Y;

    for (const roundNum of wbRoundNumbers) {
        const roundIdx = wbRoundNumbers.indexOf(roundNum);
        const roundMatches = wbRounds[roundNum];
        const spacing = wbTotalHeight / roundMatches.length;

        for (let i = 0; i < roundMatches.length; i++) {
            layout.push({
                match: roundMatches[i],
                x: roundIdx * (MATCH_WIDTH + ROUND_GAP_X),
                y: i * spacing + (spacing - MATCH_HEIGHT) / 2,
                section: 'winners',
            });
        }
    }

    const lbRounds = groupByRound(lb);
    const lbRoundNumbers = Object.keys(lbRounds)
        .map(Number)
        .sort((a, b) => a - b);
    const lbMaxMatches = Math.max(
        ...lbRoundNumbers.map((r) => lbRounds[r].length),
        1,
    );
    const lbTotalHeight =
        lbMaxMatches * (MATCH_HEIGHT + MATCH_GAP_Y) - MATCH_GAP_Y;
    const lbOffsetY = wbTotalHeight + SECTION_GAP_Y;

    for (const roundNum of lbRoundNumbers) {
        const roundIdx = lbRoundNumbers.indexOf(roundNum);
        const roundMatches = lbRounds[roundNum];
        const spacing = Math.max(
            MATCH_HEIGHT + MATCH_GAP_Y,
            lbTotalHeight / roundMatches.length,
        );

        for (let i = 0; i < roundMatches.length; i++) {
            layout.push({
                match: roundMatches[i],
                x: roundIdx * (MATCH_WIDTH + ROUND_GAP_X),
                y: lbOffsetY + i * spacing + (spacing - MATCH_HEIGHT) / 2,
                section: 'losers',
            });
        }
    }

    const gfX =
        Math.max(wbRoundNumbers.length, lbRoundNumbers.length) *
        (MATCH_WIDTH + ROUND_GAP_X);
    const gfY =
        (wbTotalHeight + lbOffsetY + lbTotalHeight) / 2 - MATCH_HEIGHT / 2;

    for (const m of gf) {
        layout.push({
            match: m,
            x: gfX,
            y: gfY,
            section: 'grand_final',
        });
    }

    return layout;
}

// ─── Placement Detection ───

function getPlacement(match: BracketMatch, stage: Stage): number | null {
    if (match.status !== 'finished' || !match.winner_participant_id) {
        return null;
    }

    const isDE = stage.stage_type === 'double_elimination';
    const isSE = stage.stage_type === 'single_elimination';

    if (isDE && match.bracket_side === 'grand_final') {
        return 1;
    }

    if (isSE) {
        const rounds = groupByRound(stage.matches);
        const roundNumbers = Object.keys(rounds)
            .map(Number)
            .sort((a, b) => a - b);
        const lastRound = roundNumbers[roundNumbers.length - 1];

        if (match.round_number === lastRound) {
            return 1;
        }
    }

    return null;
}

function getLoserPlacement(match: BracketMatch, stage: Stage): number | null {
    if (match.status !== 'finished') {
        return null;
    }

    const isDE = stage.stage_type === 'double_elimination';
    const isSE = stage.stage_type === 'single_elimination';

    if (isDE && match.bracket_side === 'grand_final') {
        return 2;
    }

    if (isDE && match.bracket_side === 'losers') {
        const lbMatches = stage.matches.filter(
            (m) => m.bracket_side === 'losers',
        );
        const lbRounds = [
            ...new Set(lbMatches.map((m) => m.round_number)),
        ].sort((a, b) => a - b);
        const lastLBRound = lbRounds[lbRounds.length - 1];

        if (match.round_number === lastLBRound) {
            return 3;
        }
    }

    if (isSE) {
        const rounds = groupByRound(stage.matches);
        const roundNumbers = Object.keys(rounds)
            .map(Number)
            .sort((a, b) => a - b);
        const lastRound = roundNumbers[roundNumbers.length - 1];

        if (match.round_number === lastRound) {
            return 2;
        }

        const semiRound = roundNumbers[roundNumbers.length - 2];

        if (semiRound && match.round_number === semiRound) {
            return 3;
        }
    }

    return null;
}

// ─── Drawing Functions ───

function roundRect(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    w: number,
    h: number,
    r: number,
) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
}

function truncate(text: string, maxLen: number): string {
    return text.length > maxLen ? text.slice(0, maxLen - 1) + '…' : text;
}

function drawConnections(ctx: CanvasRenderingContext2D, layout: LayoutMatch[]) {
    const sections = new Map<string, LayoutMatch[]>();

    for (const item of layout) {
        const key = item.section;

        if (!sections.has(key)) {
            sections.set(key, []);
        }

        sections.get(key)!.push(item);
    }

    for (const [, items] of sections) {
        const rounds = new Map<number, LayoutMatch[]>();

        for (const item of items) {
            const r = item.match.round_number;

            if (!rounds.has(r)) {
                rounds.set(r, []);
            }

            rounds.get(r)!.push(item);
        }

        const roundNums = [...rounds.keys()].sort((a, b) => a - b);

        for (let ri = 0; ri < roundNums.length - 1; ri++) {
            const current = rounds.get(roundNums[ri])!;
            const next = rounds.get(roundNums[ri + 1])!;

            for (let i = 0; i < current.length; i++) {
                const targetIdx = Math.floor(
                    i / Math.max(1, Math.ceil(current.length / next.length)),
                );
                const target = next[Math.min(targetIdx, next.length - 1)];

                if (!target) {
                    continue;
                }

                const fromX = current[i].x + MATCH_WIDTH;
                const fromY = current[i].y + MATCH_HEIGHT / 2;
                const toX = target.x;
                const toY = target.y + MATCH_HEIGHT / 2;

                ctx.beginPath();
                ctx.strokeStyle =
                    current[i].match.status === 'finished'
                        ? COLORS.connectionActive
                        : COLORS.connection;
                ctx.lineWidth = 1.5;
                const midX = (fromX + toX) / 2;
                ctx.moveTo(fromX, fromY);
                ctx.bezierCurveTo(midX, fromY, midX, toY, toX, toY);
                ctx.stroke();
            }
        }
    }
}

function drawParticipantRow(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    participant: MatchParticipant | undefined,
    match: BracketMatch,
    placement: number | null,
) {
    const rowH = MATCH_HEIGHT / 2;
    const isWinner =
        participant &&
        match.winner_participant_id === participant.competition_participant_id;

    if (placement !== null) {
        const medalColor =
            placement === 1
                ? COLORS.gold
                : placement === 2
                  ? COLORS.silver
                  : COLORS.bronze;
        ctx.fillStyle = medalColor;
        ctx.font = 'bold 10px system-ui';
        const medalText =
            placement === 1 ? '🥇' : placement === 2 ? '🥈' : '🥉';
        ctx.fillText(medalText, x + 4, y + rowH / 2 + 4);
    }

    const nameX = placement !== null ? x + 22 : x + 8;

    ctx.font = `${isWinner ? 'bold ' : ''}11px system-ui`;
    ctx.fillStyle = isWinner
        ? COLORS.winner
        : participant
          ? COLORS.text
          : COLORS.textDim;
    const name =
        participant?.name ?? (match.status === 'pending' ? 'TBD' : '---');
    ctx.fillText(truncate(name, 22), nameX, y + rowH / 2 + 4);

    if (participant?.score !== null && participant?.score !== undefined) {
        ctx.font = 'bold 12px monospace';
        ctx.fillStyle = isWinner ? COLORS.winner : COLORS.textScore;
        ctx.textAlign = 'right';
        ctx.fillText(
            String(participant.score),
            x + MATCH_WIDTH - 8,
            y + rowH / 2 + 4,
        );
        ctx.textAlign = 'left';
    }
}

function drawMatch(
    ctx: CanvasRenderingContext2D,
    item: LayoutMatch,
    stage: Stage,
    pulsePhase: number,
) {
    const { match, x, y } = item;
    const placement = getPlacement(match, stage);
    const loserPlacement = getLoserPlacement(match, stage);

    if (match.is_ready) {
        const glow = 3 + Math.sin(pulsePhase) * 2;
        ctx.save();
        ctx.shadowColor = COLORS.matchReadyGlow;
        ctx.shadowBlur = glow * 4;
        ctx.strokeStyle = COLORS.matchReadyBorder;
        ctx.lineWidth = 2.5;
        roundRect(ctx, x - 3, y - 3, MATCH_WIDTH + 6, MATCH_HEIGHT + 6, 8);
        ctx.stroke();
        ctx.restore();
    }

    ctx.fillStyle = COLORS.matchBg;
    roundRect(ctx, x, y, MATCH_WIDTH, MATCH_HEIGHT, 6);
    ctx.fill();

    ctx.strokeStyle = match.is_ready
        ? COLORS.matchReadyBorder
        : match.status === 'finished'
          ? COLORS.matchFinishedBorder
          : COLORS.matchBorder;
    ctx.lineWidth = match.is_ready ? 2 : 1;
    roundRect(ctx, x, y, MATCH_WIDTH, MATCH_HEIGHT, 6);
    ctx.stroke();

    const p1 = match.participants.find((p) => p.slot === 1);
    const p2 = match.participants.find((p) => p.slot === 2);
    const rowH = MATCH_HEIGHT / 2;

    drawParticipantRow(ctx, x, y, p1, match, placement);

    ctx.beginPath();
    ctx.strokeStyle = COLORS.matchBorder;
    ctx.lineWidth = 0.5;
    ctx.moveTo(x + 4, y + rowH);
    ctx.lineTo(x + MATCH_WIDTH - 4, y + rowH);
    ctx.stroke();

    drawParticipantRow(ctx, x, y + rowH, p2, match, loserPlacement);
}

function drawSectionLabels(
    ctx: CanvasRenderingContext2D,
    layout: LayoutMatch[],
) {
    const sections: Record<string, { minY: number }> = {};

    for (const item of layout) {
        if (!sections[item.section]) {
            sections[item.section] = { minY: Infinity };
        }

        sections[item.section].minY = Math.min(
            sections[item.section].minY,
            item.y,
        );
    }

    ctx.font = 'bold 13px system-ui';
    ctx.fillStyle = COLORS.sectionLabel;

    const labels: Record<string, string> = {
        winners: 'WINNERS BRACKET',
        losers: 'LOSERS BRACKET',
        grand_final: 'GRAND FINAL',
    };

    for (const [section, { minY }] of Object.entries(sections)) {
        if (labels[section]) {
            ctx.fillText(labels[section], 0, minY - 10);
        }
    }
}

// ─── Composable ───

export interface UseBracketCanvasOptions {
    stages: Ref<Stage[]> | ComputedRef<Stage[]>;
    activeStageIndex: Ref<number>;
    bgColor?: string;
}

export function useBracketCanvas(options: UseBracketCanvasOptions) {
    const { stages, activeStageIndex, bgColor } = options;

    const canvasContainer = ref<HTMLDivElement | null>(null);
    const canvas = ref<HTMLCanvasElement | null>(null);

    const offsetX = ref(40);
    const offsetY = ref(40);
    const scale = ref(1);
    const isDragging = ref(false);
    const dragStartX = ref(0);
    const dragStartY = ref(0);
    const dragOffsetX = ref(0);
    const dragOffsetY = ref(0);

    const activeStage = computed(() => stages.value[activeStageIndex.value]);

    let animFrame = 0;
    let pulsePhase = 0;

    function computeLayout(): LayoutMatch[] {
        if (!activeStage.value) {
            return [];
        }

        const matches = activeStage.value.matches;
        const isDE = activeStage.value.stage_type === 'double_elimination';

        if (isDE) {
            return layoutDoubleElimination(matches);
        }

        return layoutSingleElimination(matches);
    }

    function draw() {
        const ctx = canvas.value?.getContext('2d');

        if (!ctx || !canvas.value) {
            return;
        }

        const dpr = window.devicePixelRatio || 1;
        const w = canvas.value.clientWidth;
        const h = canvas.value.clientHeight;
        canvas.value.width = w * dpr;
        canvas.value.height = h * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        ctx.fillStyle = bgColor ?? COLORS.bg;
        ctx.fillRect(0, 0, w, h);

        ctx.save();
        ctx.translate(offsetX.value, offsetY.value);
        ctx.scale(scale.value, scale.value);

        const layoutItems = computeLayout();

        drawConnections(ctx, layoutItems);

        pulsePhase += 0.03;

        for (const item of layoutItems) {
            drawMatch(ctx, item, activeStage.value!, pulsePhase);
        }

        if (activeStage.value?.stage_type === 'double_elimination') {
            drawSectionLabels(ctx, layoutItems);
        }

        ctx.restore();

        animFrame = requestAnimationFrame(draw);
    }

    // ─── Pan & Zoom ───

    function onMouseDown(e: MouseEvent) {
        isDragging.value = true;
        dragStartX.value = e.clientX;
        dragStartY.value = e.clientY;
        dragOffsetX.value = offsetX.value;
        dragOffsetY.value = offsetY.value;
    }

    function onMouseMove(e: MouseEvent) {
        if (!isDragging.value) {
            return;
        }

        offsetX.value = dragOffsetX.value + (e.clientX - dragStartX.value);
        offsetY.value = dragOffsetY.value + (e.clientY - dragStartY.value);
    }

    function onMouseUp() {
        isDragging.value = false;
    }

    function onWheel(e: WheelEvent) {
        e.preventDefault();
        const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
        const newScale = Math.min(3, Math.max(0.2, scale.value * zoomFactor));

        const rect = canvas.value!.getBoundingClientRect();
        const mx = e.clientX - rect.left;
        const my = e.clientY - rect.top;

        offsetX.value = mx - (mx - offsetX.value) * (newScale / scale.value);
        offsetY.value = my - (my - offsetY.value) * (newScale / scale.value);
        scale.value = newScale;
    }

    let touchStartDist = 0;
    let touchStartScale = 1;

    function onTouchStart(e: TouchEvent) {
        if (e.touches.length === 1) {
            isDragging.value = true;
            dragStartX.value = e.touches[0].clientX;
            dragStartY.value = e.touches[0].clientY;
            dragOffsetX.value = offsetX.value;
            dragOffsetY.value = offsetY.value;
        } else if (e.touches.length === 2) {
            touchStartDist = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY,
            );
            touchStartScale = scale.value;
        }
    }

    function onTouchMove(e: TouchEvent) {
        e.preventDefault();

        if (e.touches.length === 1 && isDragging.value) {
            offsetX.value =
                dragOffsetX.value + (e.touches[0].clientX - dragStartX.value);
            offsetY.value =
                dragOffsetY.value + (e.touches[0].clientY - dragStartY.value);
        } else if (e.touches.length === 2) {
            const dist = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY,
            );
            scale.value = Math.min(
                3,
                Math.max(0.2, touchStartScale * (dist / touchStartDist)),
            );
        }
    }

    function onTouchEnd() {
        isDragging.value = false;
    }

    function resetView() {
        offsetX.value = 40;
        offsetY.value = 40;
        scale.value = 1;
    }

    function zoomIn() {
        scale.value = Math.min(3, scale.value * 1.2);
    }

    function zoomOut() {
        scale.value = Math.max(0.2, scale.value / 1.2);
    }

    function fitToScreen() {
        if (!canvas.value) {
            return;
        }

        const layout = computeLayout();

        if (layout.length === 0) {
            return;
        }

        const minX = Math.min(...layout.map((l) => l.x));
        const maxX = Math.max(...layout.map((l) => l.x + MATCH_WIDTH));
        const minY = Math.min(...layout.map((l) => l.y));
        const maxY = Math.max(...layout.map((l) => l.y + MATCH_HEIGHT));

        const contentW = maxX - minX + 80;
        const contentH = maxY - minY + 80;
        const canvasW = canvas.value.clientWidth;
        const canvasH = canvas.value.clientHeight;

        scale.value = Math.min(canvasW / contentW, canvasH / contentH, 2);
        offsetX.value =
            (canvasW - contentW * scale.value) / 2 - minX * scale.value + 40;
        offsetY.value =
            (canvasH - contentH * scale.value) / 2 - minY * scale.value + 40;
    }

    // ─── Stats ───

    const stats = computed(() => {
        if (!activeStage.value) {
            return { total: 0, finished: 0, ready: 0 };
        }

        const matches = activeStage.value.matches;

        return {
            total: matches.length,
            finished: matches.filter((m) => m.status === 'finished').length,
            ready: matches.filter((m) => m.is_ready).length,
        };
    });

    const zoomPercent = computed(() => Math.round(scale.value * 100));

    // ─── Lifecycle ───

    onMounted(() => {
        animFrame = requestAnimationFrame(draw);
        setTimeout(fitToScreen, 100);
    });

    onUnmounted(() => {
        cancelAnimationFrame(animFrame);
    });

    return {
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
    };
}
