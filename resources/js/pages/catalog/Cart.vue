<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { ShoppingBag, Trash2, ArrowLeft, Plus, Minus, CreditCard, ShieldCheck } from '@lucide/vue';
import axios from 'axios';
import { ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';

type CartProduct = {
    name: string;
    slug: string;
    thumbnail: string | null;
};

type CartItem = {
    cart_item_id: number | null;
    variant_id: number;
    sku: string;
    name: string;
    price: number;
    quantity: number;
    available_stock: number;
    subtotal: number;
    product: CartProduct | null;
};

type CartSummary = {
    item_count: number;
    subtotal: number;
    discount: number;
    grand_total: number;
};

type CartData = {
    cart_id: number | null;
    items: CartItem[];
    summary: CartSummary;
};

const page = usePage();
const isLoading = ref(true);
const cartData = ref<CartData>({
    cart_id: null,
    items: [],
    summary: {
        item_count: 0,
        subtotal: 0.00,
        discount: 0.00,
        grand_total: 0.00,
    }
});

// Load cart depending on auth status
const loadCartDetails = async () => {
    isLoading.value = true;

    try {
        if (page.props.auth.user) {
            // Member: Fetch from DB
            const response = await axios.get('/api/cart');
            cartData.value = response.data;
        } else {
            // Guest: Post LocalStorage items to compile details
            const localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
            const response = await axios.post('/api/cart/details', { items: localCart });
            cartData.value = response.data;
        }
    } catch {
        toast.error('Failed to load cart details.');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    loadCartDetails();
});

const updateQty = async (item: CartItem, newQty: number) => {
    if (newQty <= 0) {
        removeCartItem(item);

        return;
    }
    
    // Check stock threshold
    if (newQty > item.available_stock) {
        toast.error(`Only ${item.available_stock} units available in inventory.`);

        return;
    }

    try {
        if (page.props.auth.user) {
            // Member: Sync via API
            await axios.put(`/api/cart/items/${item.cart_item_id}`, { quantity: newQty });
        } else {
            // Guest: Sync LocalStorage
            const localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
            const found = localCart.find((i: any) => i.variant_id === item.variant_id);

            if (found) {
                found.quantity = newQty;
                localStorage.setItem('eshop_cart', JSON.stringify(localCart));
                window.dispatchEvent(new CustomEvent('cart-updated'));
            }
        }

        await loadCartDetails();
        toast.success('Cart updated.');
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Failed to update quantity.');
    }
};

const removeCartItem = async (item: CartItem) => {
    try {
        if (page.props.auth.user) {
            // Member: Delete via API
            await axios.delete(`/api/cart/items/${item.cart_item_id}`);
        } else {
            // Guest: Delete from LocalStorage
            let localCart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
            localCart = localCart.filter((i: any) => i.variant_id !== item.variant_id);
            localStorage.setItem('eshop_cart', JSON.stringify(localCart));
            window.dispatchEvent(new CustomEvent('cart-updated'));
        }

        await loadCartDetails();
        toast.success('Item removed from cart.');
    } catch {
        toast.error('Failed to remove item.');
    }
};

const clearCart = async () => {
    try {
        if (page.props.auth.user) {
            // Member: Clear API
            await axios.delete('/api/cart');
        } else {
            // Guest: Clear LocalStorage
            localStorage.removeItem('eshop_cart');
            window.dispatchEvent(new CustomEvent('cart-updated'));
        }

        await loadCartDetails();
        toast.success('Cart cleared.');
    } catch {
        toast.error('Failed to clear cart.');
    }
};

const checkout = () => {
    router.visit('/checkout');
};
</script>

<template>
    <StorefrontLayout title="Shopping Cart" :categories="$page.props.categories">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 text-left">
            <h1 class="text-3xl font-extrabold tracking-tight mb-8">Shopping Cart</h1>

            <div v-if="isLoading" class="flex items-center justify-center py-20">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-orange-500 border-t-transparent"></div>
            </div>

            <div v-else-if="cartData.items.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Cart items list (2/3 columns) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800 shadow-xs">
                        <div v-for="item in cartData.items" :key="item.variant_id" class="p-6 flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                            <!-- Thumbnail -->
                            <div class="h-20 w-20 rounded-lg bg-zinc-100 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 overflow-hidden flex items-center justify-center shrink-0">
                                <img v-if="item.product && item.product.thumbnail" :src="item.product.thumbnail" :alt="item.name" class="h-full w-full object-cover" />
                                <ShoppingBag v-else class="h-8 w-8 text-zinc-300 dark:text-zinc-700" />
                            </div>

                            <!-- Name, spec, SKU -->
                            <div class="flex-1 space-y-1">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-50 hover:text-orange-500">
                                    <Link v-if="item.product" :href="`/catalog/products/${item.product.slug}`">
                                        {{ item.name }}
                                    </Link>
                                    <span v-else>{{ item.name }}</span>
                                </h3>
                                <div class="text-xs text-zinc-400 font-mono">SKU: {{ item.sku }}</div>
                            </div>

                            <!-- Actions & Qty selectors -->
                            <div class="flex items-center justify-between w-full sm:w-auto gap-8">
                                <!-- Qty adjustments -->
                                <div class="flex items-center rounded-lg border border-zinc-200 dark:border-zinc-800 bg-background overflow-hidden h-9 w-28 shrink-0">
                                    <button 
                                        class="flex-1 h-full hover:bg-zinc-100 dark:hover:bg-zinc-800 font-bold transition-colors disabled:opacity-30"
                                        @click="updateQty(item, item.quantity - 1)"
                                    >
                                        <Minus class="h-3 w-3 mx-auto" />
                                    </button>
                                    <span class="flex-1 text-center font-bold text-xs">{{ item.quantity }}</span>
                                    <button 
                                        class="flex-1 h-full hover:bg-zinc-100 dark:hover:bg-zinc-800 font-bold transition-colors"
                                        @click="updateQty(item, item.quantity + 1)"
                                    >
                                        <Plus class="h-3 w-3 mx-auto" />
                                    </button>
                                </div>

                                <!-- Line subtotal -->
                                <div class="text-right shrink-0">
                                    <span class="block font-bold text-sm text-zinc-900 dark:text-zinc-50">
                                        ${{ item.subtotal.toFixed(2) }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 font-medium">
                                        ${{ item.price.toFixed(2) }} each
                                    </span>
                                </div>

                                <!-- Remove -->
                                <button class="text-zinc-400 hover:text-red-500 p-2 shrink-0 transition-colors" @click="removeCartItem(item)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Left button options -->
                    <div class="flex justify-between items-center pt-2">
                        <Link href="/catalog" class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-500 hover:text-orange-500 transition-colors">
                            <ArrowLeft class="h-4 w-4" />
                            <span>Continue Shopping</span>
                        </Link>
                        <Button variant="ghost" class="text-zinc-500 hover:text-red-500 font-bold text-xs" @click="clearCart">
                            <Trash2 class="h-4 w-4 mr-1.5" />
                            <span>Clear Cart</span>
                        </Button>
                    </div>
                </div>

                <!-- Summary Panel (1/3 column) -->
                <aside class="space-y-6">
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 shadow-xs space-y-6">
                        <h2 class="text-lg font-bold border-b border-zinc-200 dark:border-zinc-800 pb-3">Order Summary</h2>

                        <div class="space-y-3.5 text-sm">
                            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                                <span>Subtotal ({{ cartData.summary.item_count }} items)</span>
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">${{ cartData.summary.subtotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                                <span>Shipping</span>
                                <span class="font-bold text-emerald-500">Free</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                                <span>Taxes</span>
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">$0.00</span>
                            </div>
                            <div class="border-t border-zinc-200 dark:border-zinc-800 pt-3.5 flex items-baseline justify-between text-base font-extrabold">
                                <span>Grand Total</span>
                                <span class="text-2xl text-orange-500 font-black">${{ cartData.summary.grand_total.toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- Secure checkout notice -->
                        <Button class="w-full h-11 bg-orange-500 text-white hover:bg-orange-600 font-bold flex items-center justify-center gap-2" @click="checkout">
                            <CreditCard class="h-4 w-4" />
                            <span>Proceed to Checkout</span>
                        </Button>

                        <div class="flex items-center justify-center gap-1.5 text-[10px] text-zinc-400 font-semibold uppercase tracking-wider">
                            <ShieldCheck class="h-4 w-4 text-emerald-500" />
                            <span>Secured Payment Gateway</span>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Empty cart state -->
            <div v-else class="flex flex-col items-center justify-center py-24 text-center bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <ShoppingBag class="h-16 w-16 text-zinc-300 dark:text-zinc-700 opacity-60 mb-6 animate-bounce" />
                <h2 class="text-xl font-bold tracking-tight">Your Cart is Empty</h2>
                <p class="text-sm text-zinc-500 max-w-sm mt-2">
                    Looks like you haven't added anything to your cart yet. Head back to our catalog and discover amazing deals!
                </p>
                <Link href="/catalog" class="mt-6 inline-flex items-center justify-center px-6 py-3 rounded-full bg-orange-500 text-white font-bold hover:bg-orange-600 transition-colors shadow-lg shadow-orange-500/20">
                    Browse Products
                </Link>
            </div>
        </div>
    </StorefrontLayout>
</template>
