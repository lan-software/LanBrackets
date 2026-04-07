<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as competitionsIndex } from '@/routes/competitions';

defineOptions({
    layout: AppLayout,
});

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
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold">Welcome back, {{ user?.name }}.</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Manage your tournament brackets and competitions.
            </p>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- User info -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    Your Account
                </h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Email</dt>
                        <dd class="mt-0.5 text-sm font-medium">
                            {{ user?.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Role</dt>
                        <dd class="mt-0.5 text-sm font-medium capitalize">
                            {{ user?.role }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Provider</dt>
                        <dd class="mt-0.5 text-sm font-medium">
                            {{ user?.external ? 'LanCore SSO' : 'Local' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Competitions -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    Competitions
                </h2>
                <p
                    v-if="canManageBrackets"
                    class="text-sm leading-relaxed text-muted-foreground"
                >
                    Your role grants access to competition management. Create
                    brackets, report results, and manage standings.
                </p>
                <p v-else class="text-sm leading-relaxed text-muted-foreground">
                    Competition management is restricted to moderator, admin,
                    and superadmin roles.
                </p>
                <div class="mt-5">
                    <Link
                        v-if="canManageBrackets"
                        :href="competitionsIndex()"
                        class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                    >
                        <Trophy class="mr-2 h-4 w-4" />
                        Open Competitions
                    </Link>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    Quick Actions
                </h2>
                <div class="space-y-3">
                    <a
                        href="/auth/redirect"
                        class="flex w-full items-center rounded-lg border border-border px-4 py-2.5 text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                    >
                        Refresh LanCore Session
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
