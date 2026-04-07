<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    lancoreUrl: string;
}>();
</script>

<template>
    <div>
        <Head title="Access Restricted" />

        <div
            class="flex h-screen flex-col items-center justify-center bg-[#0f172a] px-4"
        >
            <div class="max-w-md text-center">
                <div class="mb-6 text-6xl text-gray-600">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="mx-auto h-16 w-16"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"
                        />
                    </svg>
                </div>

                <h1 class="mb-3 text-2xl font-bold text-white">
                    Access Restricted
                </h1>

                <p class="mb-6 text-gray-400">
                    This application requires authentication via LanCore. Please
                    log in to LanCore and navigate to the competition from
                    there.
                </p>

                <a
                    :href="lancoreUrl"
                    class="inline-block rounded bg-blue-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-blue-500"
                >
                    Go to LanCore
                </a>
            </div>
        </div>
    </div>
</template>
