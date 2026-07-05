<script setup lang="ts">
import StorefrontLayout from '@/layouts/storefront/StorefrontLayout.vue';

type Category = {
    id: number;
    name: string;
    slug: string;
};

defineProps<{
    title?: string;
    categories?: Category[];
}>();
</script>

<template>
    <StorefrontLayout :title="title" :categories="categories">
        <slot />
    </StorefrontLayout>
</template>
