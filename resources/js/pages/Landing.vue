<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
</script>

<template>
    <Head title="LanBrackets — Tournament Management" />

    <div
        class="relative min-h-screen overflow-hidden bg-stone-950 text-stone-100"
    >
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.15),_transparent_40%),radial-gradient(circle_at_bottom_right,_rgba(56,189,248,0.1),_transparent_35%)]"
        />

        <header
            class="relative z-10 mx-auto flex max-w-6xl items-center justify-between px-6 py-6"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-300"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-stone-950"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                        <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                        <path d="M4 22h16" />
                        <path
                            d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"
                        />
                        <path
                            d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"
                        />
                        <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                    </svg>
                </div>
                <span class="text-lg font-semibold tracking-tight text-white"
                    >LanBrackets</span
                >
            </div>
            <div class="flex items-center gap-3">
                <Link
                    v-if="user"
                    href="/dashboard"
                    class="rounded-full bg-amber-300 px-5 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-200"
                >
                    Dashboard
                </Link>
                <Link
                    v-else
                    href="/login"
                    class="rounded-full border border-white/15 px-5 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/50 hover:text-white"
                >
                    Sign in
                </Link>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-6xl px-6">
            <div
                class="flex min-h-[calc(100vh-10rem)] flex-col items-center justify-center text-center"
            >
                <p class="text-sm tracking-[0.3em] text-amber-300/70 uppercase">
                    Tournament Platform
                </p>
                <h1
                    class="mt-5 max-w-3xl text-4xl leading-tight font-semibold text-white sm:text-6xl"
                >
                    Brackets, matches &amp; standings — all in one place.
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-stone-400">
                    LanBrackets manages tournament formats from single
                    elimination to swiss rounds. Generate brackets, report match
                    results, and track live standings for your LAN competitions.
                </p>

                <div
                    class="mt-10 flex flex-wrap items-center justify-center gap-4"
                >
                    <Link
                        href="/login"
                        class="rounded-2xl bg-amber-300 px-7 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-200"
                    >
                        Sign in to manage
                    </Link>
                    <a
                        href="/auth/redirect"
                        class="rounded-2xl border border-white/15 px-7 py-3 text-sm font-medium text-stone-200 transition hover:border-white/30 hover:text-white"
                    >
                        Login via LanCore
                    </a>
                </div>

                <div class="mt-20 grid max-w-4xl gap-4 sm:grid-cols-3">
                    <div
                        class="rounded-2xl border border-white/8 bg-white/5 p-6 backdrop-blur"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-300/10"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-amber-300"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 5v14" />
                                <path d="M5 12h14" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-white">
                            Multiple Formats
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-400">
                            Single &amp; double elimination, round robin, swiss,
                            and group stages.
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-white/8 bg-white/5 p-6 backdrop-blur"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-300/10"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-amber-300"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"
                                />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-white">
                            Live Results
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-400">
                            Report scores, advance winners, and display bracket
                            overlays in real-time.
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-white/8 bg-white/5 p-6 backdrop-blur"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-300/10"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-amber-300"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-white">
                            LanCore Integration
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-400">
                            Participants and roles synced from LanCore. No
                            duplicate sign-ups.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="relative z-10 py-8 text-center text-xs text-stone-600">
            Powered by LanBrackets &mdash; Part of the Lan-Software ecosystem
        </footer>
    </div>
</template>
