<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            role: string;
            external: boolean;
        } | null;
    };
}>();

const user = computed(() => page.props.auth.user);
const canManageBrackets = computed(() =>
    ['moderator', 'admin', 'superadmin'].includes(user.value?.role ?? ''),
);
</script>

<template>
    <Head title="Welcome" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top_left,_#fbefe2,_transparent_35%),linear-gradient(180deg,_#f7f3eb_0%,_#efe7db_100%)] px-6 py-10 text-stone-900"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-8">
            <header
                class="flex flex-col gap-4 rounded-3xl border border-stone-300/70 bg-white/80 p-8 shadow-[0_24px_80px_rgba(66,44,24,0.08)] backdrop-blur md:flex-row md:items-end md:justify-between"
            >
                <div class="space-y-3">
                    <p
                        class="text-sm font-medium tracking-[0.24em] text-stone-500 uppercase"
                    >
                        LanBrackets
                    </p>
                    <h1
                        class="max-w-2xl font-serif text-4xl leading-tight text-stone-950 md:text-5xl"
                    >
                        LanCore SSO login is working.
                    </h1>
                    <p class="max-w-2xl text-base leading-7 text-stone-600">
                        This page is the generic authenticated landing page for
                        LanBrackets. Any user who is signed into LanCore can be
                        logged into this app and verified here.
                    </p>
                </div>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="inline-flex items-center justify-center rounded-full border border-stone-400 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:border-stone-700 hover:text-stone-950"
                >
                    Sign out
                </Link>
            </header>

            <div class="grid gap-6 lg:grid-cols-[1.3fr_0.9fr]">
                <section
                    class="rounded-3xl border border-stone-300/70 bg-white/85 p-8 shadow-[0_24px_80px_rgba(66,44,24,0.08)] backdrop-blur"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-stone-950">
                            Authenticated User
                        </h2>
                        <span
                            class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-emerald-700 uppercase"
                        >
                            Active Session
                        </span>
                    </div>

                    <dl class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Name
                            </dt>
                            <dd class="mt-2 text-lg font-medium text-stone-950">
                                {{ user?.name }}
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Email
                            </dt>
                            <dd class="mt-2 text-lg font-medium text-stone-950">
                                {{ user?.email }}
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Role
                            </dt>
                            <dd
                                class="mt-2 text-lg font-medium text-stone-950 capitalize"
                            >
                                {{ user?.role }}
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Provider
                            </dt>
                            <dd class="mt-2 text-lg font-medium text-stone-950">
                                {{ user?.external ? 'LanCore' : 'Local' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <aside
                    class="rounded-3xl border border-stone-300/70 bg-[#23160f] p-8 text-stone-100 shadow-[0_24px_80px_rgba(66,44,24,0.18)]"
                >
                    <h2 class="text-xl font-semibold">Next step</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        Use this page to confirm that LanCore authentication
                        completed successfully. Privileged users can continue
                        into the bracket management interface.
                    </p>

                    <div
                        class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4"
                    >
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-stone-400 uppercase"
                        >
                            Bracket Access
                        </p>
                        <p class="mt-3 text-sm leading-7 text-stone-200">
                            <span v-if="canManageBrackets">
                                Your role allows access to competitions and
                                bracket administration.
                            </span>
                            <span v-else>
                                Your LanCore account is logged in, but
                                competition management remains restricted to
                                moderator, admin, and superadmin roles.
                            </span>
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            v-if="canManageBrackets"
                            href="/competitions"
                            class="inline-flex items-center justify-center rounded-full bg-[#f08a43] px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-[#ff9c59]"
                        >
                            Open Competitions
                        </Link>
                        <a
                            href="/auth/redirect"
                            class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-2.5 text-sm font-semibold text-stone-100 transition hover:border-white/40"
                        >
                            Refresh LanCore Login
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
