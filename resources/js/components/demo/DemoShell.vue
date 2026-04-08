<script setup lang="ts">
import DemoBanner from '@/components/demo/DemoBanner.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <DemoBanner />
        <slot />
    </div>
</template>
