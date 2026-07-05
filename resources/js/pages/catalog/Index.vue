<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ShoppingBag, Eye, SlidersHorizontal, Layers, RotateCcw } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

type Category = {
    id: number;
    name: string;
    slug: string;
    children?: Category[];
};

type Variant = {
    id: number;
    price: number;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    summary: string | null;
    thumbnail: string | null;
    category: { name: string } | null;
    variants: Variant[];
};

type Brand = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    products: {
        data: Product[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: Category[];
    brands: Brand[];
    filters: {
        category?: string;
        brand?: string;
        search?: string;
        price_min?: string;
        price_max?: string;
        in_stock?: string;
        sort?: string;
    };
}>();

// Form states
const search = ref(props.filters.search || '');
const brand = ref(props.filters.brand || '');
const priceMin = ref(props.filters.price_min || '');
const priceMax = ref(props.filters.price_max || '');
const inStock = ref(props.filters.in_stock === 'true' || props.filters.in_stock === '1');
const sort = ref(props.filters.sort || 'latest');

// Submit filters to Laravel backend
const applyFilters = () => {
    router.get('/catalog', {
        category: props.filters.category,
        brand: brand.value,
        search: search.value,
        price_min: priceMin.value,
        price_max: priceMax.value,
        in_stock: inStock.value ? 'true' : '',
        sort: sort.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    brand.value = '';
    priceMin.value = '';
    priceMax.value = '';
    inStock.value = false;
    sort.value = 'latest';
    router.get('/catalog');
};

// Apply automatically when check status changes
watch([inStock, sort, brand], () => {
    applyFilters();
});

// Quick add to cart logic
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

const getPriceRange = (product: Product) => {
    if (product.variants.length === 0) return 'N/A';
    const prices = product.variants.map(v => parseFloat(v.price as any));
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    return min === max ? `$${min.toFixed(2)}` : `$${min.toFixed(2)} - $${max.toFixed(2)}`;
};
</script>

<template>
    <StorefrontLayout title="Product Catalog" :categories="categories">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col gap-6 lg:flex-row">
                <!-- Sidebar Filters -->
                <aside class="w-full lg:w-64 shrink-0 space-y-6">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-4">
                        <h2 class="text-lg font-bold flex items-center gap-2">
                            <SlidersHorizontal class="h-4.5 w-4.5" />
                            <span>Filters</span>
                        </h2>
                        <button 
                            class="text-xs text-zinc-400 hover:text-orange-500 flex items-center gap-1 transition-colors"
                            @click="resetFilters"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>Reset</span>
                        </button>
                    </div>

                    <!-- Category Hierarchy Filters -->
                    <div class="space-y-2">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-400">Categories</h3>
                        <div class="flex flex-col gap-1 text-sm font-medium">
                            <Link 
                                href="/catalog" 
                                class="py-1 hover:text-orange-500 transition-colors text-left"
                                :class="{ 'text-orange-500 font-bold': !filters.category }"
                            >
                                All Products
                            </Link>
                            <div v-for="category in categories" :key="category.id" class="flex flex-col ml-2">
                                <Link 
                                    :href="`/catalog?category=${category.slug}`"
                                    class="py-1 hover:text-orange-500 transition-colors text-left"
                                    :class="{ 'text-orange-500 font-bold': filters.category === category.slug }"
                                >
                                    {{ category.name }}
                                </Link>
                                <Link 
                                    v-for="child in category.children" 
                                    :key="child.id"
                                    :href="`/catalog?category=${child.slug}`"
                                    class="py-0.5 ml-3 text-xs text-zinc-500 hover:text-orange-500 transition-colors text-left"
                                    :class="{ 'text-orange-500 font-bold': filters.category === child.slug }"
                                >
                                    — {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="space-y-2 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-left">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-400">Brands</h3>
                        <div class="flex flex-col gap-1 text-sm font-medium">
                            <button 
                                class="text-left py-1 hover:text-orange-500 transition-colors"
                                :class="{ 'text-orange-500 font-bold': !brand }"
                                @click="brand = ''"
                            >
                                All Brands
                            </button>
                            <button 
                                v-for="b in brands" 
                                :key="b.id"
                                class="text-left py-1 hover:text-orange-500 transition-colors flex items-center justify-between"
                                :class="{ 'text-orange-500 font-bold': brand === b.slug }"
                                @click="brand = b.slug"
                            >
                                <span>{{ b.name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Price Boundary Limits -->
                    <div class="space-y-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-400">Price Range</h3>
                        <div class="flex items-center gap-2">
                            <Input 
                                v-model="priceMin"
                                type="number" 
                                placeholder="Min" 
                                class="h-9 bg-background"
                                @keyup.enter="applyFilters"
                            />
                            <span class="text-zinc-400 text-xs">to</span>
                            <Input 
                                v-model="priceMax"
                                type="number" 
                                placeholder="Max" 
                                class="h-9 bg-background"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <Button variant="secondary" size="sm" class="w-full mt-2" @click="applyFilters">
                            Apply Price
                        </Button>
                    </div>

                    <!-- Stock Availability Check -->
                    <div class="flex items-center gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-sm">
                        <input 
                            id="in_stock" 
                            v-model="inStock"
                            type="checkbox" 
                            class="h-4 w-4 rounded border-zinc-300 text-orange-500 focus:ring-orange-500"
                        />
                        <label for="in_stock" class="font-medium cursor-pointer select-none">
                            Exclude Out of Stock
                        </label>
                    </div>
                </aside>

                <!-- Products Catalog Grid Area -->
                <div class="flex-1 space-y-6">
                    <!-- Sorting & Result Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-zinc-200 dark:border-zinc-800 pb-4 gap-4">
                        <div class="text-sm text-zinc-500 text-left">
                            We found <span class="font-bold text-zinc-900 dark:text-zinc-50">{{ products.total }}</span> product{{ products.total === 1 ? '' : 's' }} for you.
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <span class="text-xs text-zinc-400">Sort by:</span>
                            <select 
                                v-model="sort"
                                class="h-9 text-xs rounded-md border border-zinc-200 dark:border-zinc-800 bg-background px-3 py-1 shadow-xs focus-visible:outline-hidden"
                            >
                                <option value="latest">Newest Arrivals</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid of Cards -->
                    <div v-if="products.data.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3">
                        <div 
                            v-for="product in products.data" 
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

                                <!-- Hover Actions -->
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

                    <!-- Catalog Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <ShoppingBag class="h-12 w-12 text-zinc-400 opacity-40 mb-4 animate-pulse" />
                        <h3 class="font-bold text-lg">No Products Match Filters</h3>
                        <p class="text-sm text-zinc-500 max-w-sm mt-1">
                            Try resetting your sorting, price boundaries, or search filters to find products.
                        </p>
                        <Button variant="outline" class="mt-4" @click="resetFilters">
                            Clear All Filters
                        </Button>
                    </div>

                    <!-- Pagination Navigation -->
                    <div v-if="products.last_page > 1" class="flex items-center justify-between border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <div class="text-xs text-zinc-400">
                            Page {{ products.current_page }} of {{ products.last_page }}
                        </div>
                        <div class="flex items-center gap-1">
                            <Link 
                                v-for="link in products.links" 
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-3 py-1.5 text-xs rounded-md border border-zinc-200 dark:border-zinc-800 bg-background transition-all hover:bg-zinc-50 dark:hover:bg-zinc-900"
                                :class="{
                                    'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-black pointer-events-none font-bold': link.active,
                                    'opacity-50 pointer-events-none': !link.url && !link.active
                                }"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
