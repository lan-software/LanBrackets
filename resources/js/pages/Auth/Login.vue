<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <Head title="Login" />

    <div
        class="relative min-h-screen overflow-hidden bg-stone-950 text-stone-100"
    >
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.2),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(56,189,248,0.16),_transparent_24%)]"
        />
        <div
            class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12"
        >
            <div class="grid w-full gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <section class="max-w-2xl">
                    <p
                        class="text-sm tracking-[0.28em] text-amber-300/80 uppercase"
                    >
                        LanBrackets
                    </p>
                    <h1
                        class="mt-4 max-w-xl text-4xl leading-tight font-semibold text-white sm:text-5xl"
                    >
                        Sign in to manage brackets and match flow.
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-stone-300">
                        This workspace now uses the local Laravel auth flow,
                        with access restricted to moderator, admin, and
                        superadmin accounts.
                    </p>
                </section>

                <section
                    class="rounded-3xl border border-white/10 bg-white/8 p-6 backdrop-blur sm:p-8"
                >
                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label
                                class="mb-2 block text-sm font-medium text-stone-200"
                                for="email"
                            >
                                Email
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-sm text-white transition outline-none focus:border-amber-300/70"
                            />
                            <p
                                v-if="form.errors.email"
                                class="mt-2 text-sm text-rose-300"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-2 block text-sm font-medium text-stone-200"
                                for="password"
                            >
                                Password
                            </label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-sm text-white transition outline-none focus:border-amber-300/70"
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-2 text-sm text-rose-300"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <label
                            class="flex items-center gap-3 text-sm text-stone-300"
                        >
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-white/20 bg-stone-950/70 text-amber-400 focus:ring-amber-300"
                            />
                            <span>Remember me</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-2xl bg-amber-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            Sign in
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>
