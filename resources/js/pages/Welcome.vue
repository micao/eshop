<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ShoppingBag, Eye, Layers } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';

type Category = {
    id: number;
    name: string;
    slug: string;
    products_count: number;
};

type Variant = {
    id: number;
    price: number;
    compare_at_price: number | null;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    summary: string | null;
    thumbnail: string | null;
    category: Category | null;
    variants: Variant[];
};

defineProps<{
    categories: Category[];
    newArrivals: Product[];
}>();

const getPriceRange = (product: Product) => {
    if (product.variants.length === 0) {
return 'N/A';
}

    const prices = product.variants.map(v => parseFloat(v.price as any));
    const min = Math.min(...prices);
    const max = Math.max(...prices);

    return min === max ? `$${min.toFixed(2)}` : `$${min.toFixed(2)} - $${max.toFixed(2)}`;
};

const quickAddToCart = async (product: Product) => {
    if (!product.variants || product.variants.length === 0) {
        toast.error('No variants available for this product.');

        return;
    }

    const defaultVariant = product.variants[0];
    const isMember = usePage().props.auth.user;
    
    try {
        if (isMember) {
            await axios.post('/api/cart', {
                variant_id: defaultVariant.id,
                quantity: 1
            });
        } else {
            const localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
            const existing = localCart.find((item: any) => item.variant_id === defaultVariant.id);

            if (existing) {
                existing.quantity += 1;
            } else {
                localCart.push({ variant_id: defaultVariant.id, quantity: 1 });
            }

            localStorage.setItem('eshop_cart', JSON.stringify(localCart));
            window.dispatchEvent(new CustomEvent('cart-updated'));
        }

        toast.success(`Quick added 1x ${product.name} to cart!`);
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Failed to quick add to cart.');
    }
};
</script>

<template>
    <StorefrontLayout title="Welcome to our Store" :categories="categories">
        <!-- Hero Banner section -->
        <section class="relative bg-zinc-900 overflow-hidden py-24 sm:py-32">
            <div class="absolute inset-0 opacity-20">
                <img 
                    src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1920&q=80" 
                    alt="E-commerce store background" 
                    class="h-full w-full object-cover object-center"
                />
            </div>
            
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center sm:text-left space-y-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    Summer Collection 2026 is Live!
                </span>
                
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-6xl max-w-2xl leading-none">
                    Discover Modern Electronics & Essentials
                </h1>
                
                <p class="text-lg text-zinc-300 max-w-md">
                    Upgrade your lifestyle with our premium, high-quality, fully guaranteed tech gear and accessories.
                </p>
                
                <div class="flex flex-col sm:flex-row items-center gap-4 pt-2">
                    <Link 
                        href="/catalog" 
                        class="inline-flex items-center gap-2 px-8 py-3.5 rounded-full bg-orange-500 text-white font-bold hover:bg-orange-600 transition-colors shadow-lg shadow-orange-500/20"
                    >
                        <span>Shop Catalog</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Categories grids section -->
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Shop by Category</h2>
                    <p class="text-sm text-zinc-500">Explore items grouped by departments.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <Link 
                    v-for="category in categories" 
                    :key="category.id"
                    :href="`/catalog?category=${category.slug}`"
                    class="group relative flex flex-col justify-end overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 h-36 hover:shadow-md transition-all text-left"
                >
                    <span class="block text-sm font-bold text-zinc-900 dark:text-zinc-50 group-hover:text-orange-500 transition-colors">
                        {{ category.name }}
                    </span>
                    <span class="block text-xs text-zinc-400 mt-1">
                        {{ category.products_count || 0 }} product{{ (category.products_count || 0) === 1 ? '' : 's' }}
                    </span>
                </Link>
            </div>
        </section>

        <!-- New Arrivals products section -->
        <section class="bg-zinc-100 dark:bg-zinc-900/50 py-16 transition-colors">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">New Arrivals</h2>
                        <p class="text-sm text-zinc-500">Our latest products catalog additions.</p>
                    </div>
                    <Link href="/catalog" class="text-sm font-semibold text-orange-500 hover:text-orange-600 flex items-center gap-1">
                        <span>See All</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <div 
                        v-for="product in newArrivals" 
                        :key="product.id"
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 shadow-sm hover:shadow-md transition-all duration-300 text-left"
                    >
                        <!-- Thumbnail -->
                        <div class="relative aspect-square w-full overflow-hidden bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-100 dark:border-zinc-800/50">
                            <img 
                                v-if="product.thumbnail" 
                                :src="product.thumbnail" 
                                :alt="product.name" 
                                class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-zinc-300">
                                <ShoppingBag class="h-12 w-12 opacity-30" />
                            </div>

                            <!-- Overlay -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/60 opacity-0 backdrop-blur-xs transition-opacity duration-300 group-hover:opacity-100">
                                <Link 
                                    :href="`/catalog/products/${product.slug}`"
                                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 w-44 rounded-full bg-white text-zinc-900 text-xs font-bold hover:bg-zinc-100 transition-colors shadow-lg"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>View Details</span>
                                </Link>
                                <button 
                                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 w-44 rounded-full bg-orange-500 text-white text-xs font-bold hover:bg-orange-600 transition-colors shadow-lg"
                                    @click="quickAddToCart(product)"
                                >
                                    <ShoppingBag class="h-3.5 w-3.5" />
                                    <span>Quick Add</span>
                                </button>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="flex flex-1 flex-col p-4 gap-2">
                            <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                                <span v-if="product.category" class="font-medium text-orange-500">
                                    {{ product.category.name }}
                                </span>
                            </div>
                            
                            <h3 class="font-bold text-zinc-900 dark:text-zinc-50 line-clamp-1">
                                <Link :href="`/catalog/products/${product.slug}`" class="hover:text-orange-500 transition-colors">
                                    {{ product.name }}
                                </Link>
                            </h3>
                            
                            <p class="text-xs text-zinc-400 line-clamp-2 min-h-[32px]">
                                {{ product.summary || 'No description summary available.' }}
                            </p>

                            <div class="mt-auto flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800/50 pt-3">
                                <div class="flex items-center gap-1 text-[10px] text-zinc-400">
                                    <Layers class="h-3.5 w-3.5" />
                                    <span>{{ product.variants.length }} Option{{ product.variants.length > 1 ? 's' : '' }}</span>
                                </div>
                                <span class="font-bold text-zinc-900 dark:text-zinc-50">
                                    {{ getPriceRange(product) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
