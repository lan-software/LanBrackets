<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { show } from '@/actions/App/Http/Controllers/CompetitionController';


interface Competition {
    id: number;
    name: string;
    slug: string | null;
    type: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    participants_count: number;
    stages_count: number;
    created_at: string;
}

interface PaginatedCompetitions {
    data: Competition[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

defineProps<{
    competitions: PaginatedCompetitions;
}>();

const statusColor: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    planned: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    registration_open:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    registration_closed:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    running:
        'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    completed:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    archived: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
};

const statusLabel: Record<string, string> = {
    draft: 'competitions.statusDraft',
    planned: 'competitions.statusPlanned',
    registration_open: 'competitions.statusRegistrationOpen',
    registration_closed: 'competitions.statusRegistrationClosed',
    running: 'competitions.statusRunning',
    completed: 'competitions.statusCompleted',
    cancelled: 'competitions.statusCancelled',
    archived: 'competitions.statusArchived',
};

const typeLabel: Record<string, string> = {
    tournament: 'competitions.typeTournament',
    league: 'competitions.typeLeague',
    race: 'competitions.typeRace',
};
</script>

<template>
    <div>
        <Head :title="$t('competitions.title')" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ $t('competitions.title') }}
                        </h1>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{
                                competitions.total === 1
                                    ? $t('competitions.countOne', {
                                          count: competitions.total,
                                      })
                                    : $t('competitions.countMany', {
                                          count: competitions.total,
                                      })
                            }}
                        </p>
                    </div>
                    <Link
                        href="/"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        {{ $t('competitions.backToHome') }}
                    </Link>
                </div>

                <div
                    v-if="competitions.data.length === 0"
                    class="rounded-lg border border-dashed border-gray-300 p-12 text-center dark:border-gray-700"
                >
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ $t('competitions.empty') }}
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="competition in competitions.data"
                        :key="competition.id"
                        :href="show.url({ competition: competition.id })"
                        class="group rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="mb-3 flex items-start justify-between gap-3"
                        >
                            <h2
                                class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400"
                            >
                                {{ competition.name }}
                            </h2>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    statusColor[competition.status] ??
                                    'bg-gray-100 text-gray-700'
                                "
                            >
                                {{
                                    statusLabel[competition.status]
                                        ? $t(statusLabel[competition.status])
                                        : competition.status
                                }}
                            </span>
                        </div>

                        <div
                            class="mb-4 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <span class="inline-flex items-center gap-1">
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                {{ competition.participants_count }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 10h16M4 14h16M4 18h16"
                                    />
                                </svg>
                                {{
                                    competition.stages_count === 1
                                        ? $t('competitions.stageCountOne', {
                                              count: competition.stages_count,
                                          })
                                        : $t('competitions.stageCountMany', {
                                              count: competition.stages_count,
                                          })
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500"
                        >
                            <span
                                class="rounded bg-gray-100 px-2 py-0.5 font-medium dark:bg-gray-700"
                            >
                                {{
                                    typeLabel[competition.type]
                                        ? $t(typeLabel[competition.type])
                                        : competition.type
                                }}
                            </span>
                            <span v-if="competition.starts_at">
                                {{
                                    new Date(
                                        competition.starts_at,
                                    ).toLocaleDateString()
                                }}
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div
                    v-if="competitions.last_page > 1"
                    class="mt-8 flex items-center justify-center gap-2"
                >
                    <Link
                        v-if="competitions.prev_page_url"
                        :href="competitions.prev_page_url"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800"
                    >
                        {{ $t('competitions.previousPage') }}
                    </Link>
                    <span
                        class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{
                            $t('competitions.pageOf', {
                                current: competitions.current_page,
                                total: competitions.last_page,
                            })
                        }}
                    </span>
                    <Link
                        v-if="competitions.next_page_url"
                        :href="competitions.next_page_url"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800"
                    >
                        {{ $t('competitions.nextPage') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
