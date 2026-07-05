<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ShoppingBag, Search, User, Menu, X, ChevronDown } from '@lucide/vue';
import axios from 'axios';
import { ref, onMounted } from 'vue';
import { login, register, dashboard } from '@/routes';

type Category = {
    id: number;
    name: string;
    slug: string;
};

defineProps<{
    title?: string;
    categories?: Category[];
}>();

const searchQuery = ref('');
const isMobileMenuOpen = ref(false);
const isUserDropdownOpen = ref(false);
const cartCount = ref(0);

// Load cart count from guest LocalStorage dynamically
const updateCartCount = () => {
    try {
        const cart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
        cartCount.value = cart.reduce((total: number, item: any) => total + (item.quantity || 0), 0);
    } catch {
        cartCount.value = 0;
    }
};

onMounted(async () => {
    updateCartCount();
    // Listen for custom events to update cart count reactively
    window.addEventListener('cart-updated', updateCartCount);

    // Auto-merge guest cart if user is logged in and guest items exist
    const pageProps = usePage().props;

    if (pageProps.auth.user) {
        try {
            const localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');

            if (localCart.length > 0) {
                await axios.post('/api/cart/merge', { items: localCart });
                localStorage.removeItem('eshop_cart');
                updateCartCount();
                window.dispatchEvent(new CustomEvent('cart-updated'));
            }
        } catch (e) {
            console.error('Failed to auto-merge guest cart:', e);
        }
    }
});

const handleSearch = () => {
    router.get('/catalog', { search: searchQuery.value });
};

// Cart hover preview states
const cartPreview = ref<any>(null);
const isHoveringCart = ref(false);
const isLoadingPreview = ref(false);

const handleMouseEnterCart = async () => {
    isHoveringCart.value = true;
    isLoadingPreview.value = true;

    try {
        const pageProps = usePage().props;

        if (pageProps.auth.user) {
            const response = await axios.get('/api/cart');
            cartPreview.value = response.data;
        } else {
            const localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
            const response = await axios.post('/api/cart/details', { items: localCart });
            cartPreview.value = response.data;
        }
    } catch {
        cartPreview.value = null;
    } finally {
        isLoadingPreview.value = false;
    }
};

const handleMouseLeaveCart = () => {
    isHoveringCart.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-50 flex flex-col font-sans">
        <Head :title="title ? `${title} - eShop` : 'eShop - Modern E-Commerce Store'" />

        <!-- Top Header Navigation -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800 transition-colors">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between gap-4">
                    <!-- Brand Logo -->
                    <div class="flex items-center gap-6">
                        <Link href="/" class="flex items-center gap-2 text-xl font-bold tracking-tight">
                            <span class="bg-gradient-to-r from-orange-500 to-amber-500 text-transparent bg-clip-text font-black">eShop</span>
                        </Link>
                        
                        <!-- Desktop Category Menu Links -->
                        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                            <Link href="/catalog" class="hover:text-orange-500 transition-colors">All Products</Link>
                            <Link 
                                v-for="category in categories?.slice(0, 4)" 
                                :key="category.id" 
                                :href="`/catalog?category=${category.slug}`"
                                class="hover:text-orange-500 transition-colors text-zinc-600 dark:text-zinc-300"
                            >
                                {{ category.name }}
                            </Link>
                        </nav>
                    </div>

                    <!-- Search field -->
                    <div class="hidden sm:flex flex-1 max-w-md relative">
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Search products, categories..." 
                            class="w-full h-9 pl-9 pr-4 rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-950 text-sm focus:outline-none focus:ring-1 focus:ring-orange-500 transition-all"
                            @keyup.enter="handleSearch"
                        />
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4">
                        <!-- Search Icon for Mobile -->
                        <Link href="/catalog" class="sm:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:text-orange-500">
                            <Search class="h-5 w-5" />
                        </Link>

                        <!-- Member Auth Portal -->
                        <div class="relative">
                            <template v-if="$page.props.auth.user">
                                <button 
                                    class="flex items-center gap-1.5 p-1 rounded-full text-zinc-600 dark:text-zinc-300 hover:text-orange-500 focus:outline-none"
                                    @click="isUserDropdownOpen = !isUserDropdownOpen"
                                >
                                    <User class="h-5 w-5" />
                                    <span class="hidden md:inline text-xs font-semibold max-w-[100px] truncate">
                                        {{ $page.props.auth.user.name }}
                                    </span>
                                    <ChevronDown class="h-3 w-3 opacity-60" />
                                </button>
                                
                                <div 
                                    v-if="isUserDropdownOpen" 
                                    class="absolute right-0 mt-2 w-48 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2 shadow-lg z-50 text-sm"
                                >
                                    <Link v-if="$page.props.auth.user.role === 'admin'" :href="dashboard()" class="block w-full px-4 py-2 text-left rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        Admin Dashboard
                                    </Link>
                                    <Link href="/settings/profile" class="block w-full px-4 py-2 text-left rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        My Profile
                                    </Link>
                                    <hr class="border-zinc-200 dark:border-zinc-800 my-1" />
                                    <Link href="/logout" method="post" as="button" class="block w-full px-4 py-2 text-left rounded-md hover:bg-red-50 dark:hover:bg-red-950/20 text-red-600 dark:text-red-400">
                                        Log Out
                                    </Link>
                                </div>
                            </template>
                            <template v-else>
                                <Link :href="login()" class="hidden md:inline-block text-sm font-semibold hover:text-orange-500 transition-colors">
                                    Log in
                                </Link>
                                <Link :href="register()" class="hidden md:inline-block text-sm font-semibold bg-orange-500 text-white px-4 py-1.5 rounded-full hover:bg-orange-600 transition-colors">
                                    Register
                                </Link>
                                <Link :href="login()" class="md:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:text-orange-500">
                                    <User class="h-5 w-5" />
                                </Link>
                            </template>
                        </div>

                        <!-- Shopping Cart Dropdown Trigger -->
                        <div 
                            class="relative"
                            @mouseenter="handleMouseEnterCart"
                            @mouseleave="handleMouseLeaveCart"
                        >
                            <Link href="/cart" class="relative p-2 block text-zinc-600 dark:text-zinc-300 hover:text-orange-500 transition-all">
                                <ShoppingBag class="h-5 w-5" />
                                <span 
                                    v-if="cartCount > 0" 
                                    class="absolute top-0 right-0 h-4 min-w-4 px-1 flex items-center justify-center rounded-full bg-orange-500 text-[10px] font-black text-white"
                                >
                                    {{ cartCount }}
                                </span>
                            </Link>

                            <!-- Hover Dropdown Preview Panel -->
                            <div 
                                v-if="isHoveringCart"
                                class="absolute right-0 mt-1 w-80 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-xl z-50 text-sm space-y-4"
                            >
                                <div class="font-bold border-b border-zinc-100 dark:border-zinc-800/80 pb-2 text-zinc-900 dark:text-zinc-50">
                                    Shopping Bag ({{ cartCount }} items)
                                </div>
                                
                                <div v-if="isLoadingPreview" class="flex justify-center py-6">
                                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-orange-500 border-t-transparent"></div>
                                </div>
                                <div v-else-if="cartPreview && cartPreview.items.length > 0" class="max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/50 pr-1">
                                    <div v-for="item in cartPreview.items.slice(0, 3)" :key="item.variant_id" class="py-3 flex gap-3">
                                        <div class="h-10 w-10 rounded bg-zinc-50 dark:bg-zinc-950 border border-zinc-100 dark:border-zinc-800 overflow-hidden shrink-0 flex items-center justify-center">
                                            <img v-if="item.product && item.product.thumbnail" :src="item.product.thumbnail" class="h-full w-full object-cover" />
                                            <ShoppingBag v-else class="h-5 w-5 text-zinc-300 dark:text-zinc-700" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold text-xs truncate text-zinc-900 dark:text-zinc-100">{{ item.name }}</div>
                                            <div class="text-[10px] text-zinc-400 truncate">SKU: {{ item.sku }}</div>
                                            <div class="text-[10px] text-zinc-500 mt-0.5">{{ item.quantity }}x ${{ item.price.toFixed(2) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="cartPreview.items.length > 3" class="text-center text-[10px] text-zinc-400 py-1.5 font-semibold">
                                        + {{ cartPreview.items.length - 3 }} more item(s)
                                    </div>
                                </div>
                                <div v-else class="text-center text-xs text-zinc-400 py-8">
                                    Your cart is empty.
                                </div>
                                
                                <div v-if="cartPreview && cartPreview.items.length > 0" class="border-t border-zinc-100 dark:border-zinc-800/80 pt-3 space-y-3">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500">Subtotal:</span>
                                        <span class="font-bold text-zinc-900 dark:text-zinc-50">${{ cartPreview.summary.subtotal.toFixed(2) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <Link href="/cart" class="w-full text-center py-2 text-xs font-semibold rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                                            View Cart
                                        </Link>
                                        <Link href="/cart" class="w-full text-center py-2 text-xs font-bold rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors">
                                            Checkout
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Menu Button -->
                        <button 
                            class="md:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:text-orange-500"
                            @click="isMobileMenuOpen = !isMobileMenuOpen"
                        >
                            <Menu v-if="!isMobileMenuOpen" class="h-5 w-5" />
                            <X v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div v-if="isMobileMenuOpen" class="md:hidden border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 space-y-3">
                <nav class="flex flex-col gap-2.5 text-sm font-medium">
                    <Link href="/catalog" class="hover:text-orange-500 py-1 transition-colors">All Products</Link>
                    <Link 
                        v-for="category in categories" 
                        :key="category.id" 
                        :href="`/catalog?category=${category.slug}`"
                        class="hover:text-orange-500 py-1 transition-colors text-zinc-600 dark:text-zinc-300"
                    >
                        {{ category.name }}
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Storefront Footer -->
        <footer class="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 transition-colors py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center sm:text-left">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div class="md:col-span-2 space-y-4">
                        <span class="text-xl font-bold bg-gradient-to-r from-orange-500 to-amber-500 text-transparent bg-clip-text font-black">eShop</span>
                        <p class="text-sm text-zinc-500 max-w-sm">
                            A high-fidelity modern e-commerce storefront system built with Laravel, Inertia, Vue, and TailwindCSS.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-3">Links</h4>
                        <ul class="text-sm text-zinc-500 space-y-2">
                            <li><Link href="/catalog" class="hover:text-orange-500">Shop Catalog</Link></li>
                            <li><Link href="/" class="hover:text-orange-500">Back to Home</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-3">Tech Stack</h4>
                        <ul class="text-sm text-zinc-500 space-y-2">
                            <li>Laravel 11+</li>
                            <li>Inertia.js & Vue 3</li>
                            <li>Tailwind CSS</li>
                            <li>RabbitMQ Processing</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-zinc-100 dark:border-zinc-800/50 text-center text-xs text-zinc-400">
                    © 2026 eShop Storefront Inc. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
