<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
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
    <Head :title="$t('dashboard.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $t('dashboard.welcomeNamed', { name: user?.name }) }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ $t('dashboard.description') }}
            </p>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- User info -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    {{ $t('dashboard.yourAccount') }}
                </h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('landing.email') }}
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium">
                            {{ user?.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('landing.role') }}
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium capitalize">
                            {{ user?.role }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('landing.provider') }}
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium">
                            {{
                                user?.external
                                    ? $t('dashboard.providerLanCoreSso')
                                    : $t('landing.providerLocal')
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Competitions -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    {{ $t('navigation.competitions') }}
                </h2>
                <p
                    v-if="canManageBrackets"
                    class="text-sm leading-relaxed text-muted-foreground"
                >
                    {{ $t('dashboard.competitionsCanManage') }}
                </p>
                <p v-else class="text-sm leading-relaxed text-muted-foreground">
                    {{ $t('dashboard.competitionsRestricted') }}
                </p>
                <div class="mt-5">
                    <Link
                        v-if="canManageBrackets"
                        :href="competitionsIndex()"
                        class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                    >
                        <Trophy class="mr-2 h-4 w-4" />
                        {{ $t('landing.openCompetitions') }}
                    </Link>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">
                    {{ $t('dashboard.quickActions') }}
                </h2>
                <div class="space-y-3">
                    <a
                        href="/auth/redirect"
                        class="flex w-full items-center rounded-lg border border-border px-4 py-2.5 text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                    >
                        {{ $t('dashboard.refreshSsoSession') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
