<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle, Truck, Package, Printer, ArrowRight, MapPin, User, Phone } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';

type Category = {
    id: number;
    name: string;
    slug: string;
};

type OrderItem = {
    id: number;
    variant_id: number;
    quantity: number;
    price: number;
    sku: string | null;
    variant?: {
        name: string;
        product?: {
            name: string;
        }
    }
};

type Order = {
    id: number;
    order_number: string;
    subtotal: number;
    shipping_cost: number;
    grand_total: number;
    status: string;
    tracking_number: string | null;
    shipping_label_url: string | null;
    shipping_name: string;
    shipping_phone: string;
    shipping_address_line_1: string;
    shipping_address_line_2: string | null;
    shipping_city: string;
    shipping_state_province: string | null;
    shipping_postal_code: string;
    shipping_country_code: string;
    items: OrderItem[];
};

defineProps<{
    categories?: Category[];
    order: Order;
}>();
</script>

<template>
    <StorefrontLayout title="Order Placed Successfully" :categories="categories">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center text-left">
            <!-- Success Icon -->
            <div class="mb-6 flex justify-center">
                <CheckCircle class="size-16 text-green-500 animate-bounce" />
            </div>

            <h1 class="text-3xl font-black tracking-tight mb-2 text-zinc-900 dark:text-white">
                Thank you for your order!
            </h1>
            <p class="text-zinc-500 text-sm mb-10 max-w-md mx-auto">
                Your order <span class="font-bold text-zinc-900 dark:text-zinc-100">#{{ order.order_number }}</span> has been captured. We've sent a confirmation email containing invoice details.
            </p>

            <!-- Grid card -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <!-- Left: Delivery status & metadata -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 flex flex-col gap-4 text-left">
                    <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-850 pb-3">
                        <Truck class="size-4 text-orange-500" /> Shipping & Tracking
                    </h3>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-zinc-400">Order Status</span>
                        <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100 capitalize">{{ order.status }}</p>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-zinc-400">Tracking Code</span>
                        <p class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ order.tracking_number || 'Preparing shipment...' }}
                        </p>
                    </div>
                    <div v-if="order.shipping_label_url" class="mt-2">
                        <a :href="order.shipping_label_url" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-orange-500 hover:text-orange-600 font-bold">
                            <Printer class="size-3.5" /> Download Shipping Label PDF
                        </a>
                    </div>
                </div>

                <!-- Right: Destination snapshots -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 flex flex-col gap-4 text-left">
                    <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-850 pb-3">
                        <MapPin class="size-4 text-orange-500" /> Delivery Address
                    </h3>
                    <p class="font-bold text-xs text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                        <User class="size-3.5 text-zinc-400" /> {{ order.shipping_name }}
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-start gap-1.5 leading-relaxed">
                        <MapPin class="size-3.5 text-zinc-400 shrink-0 mt-0.5" />
                        <span>
                            {{ order.shipping_address_line_1 }}<span v-if="order.shipping_address_line_2">, {{ order.shipping_address_line_2 }}</span><br/>
                            {{ order.shipping_postal_code }} {{ order.shipping_city }}, {{ order.shipping_country_code }}
                        </span>
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                        <Phone class="size-3.5 text-zinc-400" /> {{ order.shipping_phone }}
                    </p>
                </div>
            </div>

            <!-- Items summary card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 mb-10 text-left">
                <h3 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-850 pb-4 mb-4">
                    <Package class="size-4 text-orange-500" /> Items Purchased
                </h3>
                <div class="flex flex-col gap-3">
                    <div 
                        v-for="item in order.items" 
                        :key="item.id"
                        class="flex items-center justify-between text-xs"
                    >
                        <div class="flex flex-col">
                            <span class="font-bold text-zinc-900 dark:text-white">
                                {{ item.variant?.product?.name || 'Product' }}
                            </span>
                            <span class="text-[10px] text-zinc-500">
                                Variant: {{ item.variant?.name || 'Default' }} (SKU: {{ item.sku }})
                            </span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-zinc-500">Qty: {{ item.quantity }}</span>
                            <span class="font-bold text-zinc-950 dark:text-zinc-50 ml-6">€{{ (item.price * item.quantity).toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-850 mt-4 pt-4 flex flex-col gap-2 text-xs">
                    <div class="flex justify-between text-zinc-500">
                        <span>Subtotal</span>
                        <span>€{{ order.subtotal.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-500">
                        <span>Shipping Cost</span>
                        <span>{{ order.shipping_cost === 0 ? 'FREE' : `€${order.shipping_cost.toFixed(2)}` }}</span>
                    </div>
                    <div class="flex justify-between font-black text-sm text-zinc-900 dark:text-white mt-2 pt-2 border-t border-dashed border-zinc-100 dark:border-zinc-800">
                        <span>Grand Total Paid</span>
                        <span class="text-orange-500 text-base">€{{ order.grand_total.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Redirect Button -->
            <div class="flex justify-center">
                <Link href="/catalog">
                    <Button class="bg-orange-500 text-white hover:bg-orange-600 font-bold gap-2">
                        Continue Shopping <ArrowRight class="size-4" />
                    </Button>
                </Link>
            </div>
        </div>
    </StorefrontLayout>
</template>
