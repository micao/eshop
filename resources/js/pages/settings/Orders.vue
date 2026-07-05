<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Package, Clock } from '@lucide/vue';
import axios from 'axios';
import { ref, onMounted } from 'vue';
import Heading from '@/components/Heading.vue';

interface OrderItem {
    id: string;
    quantity: number;
    price: number;
    sku: string;
    variant?: {
        name: string;
        product: {
            name: string;
        };
    };
}

interface Order {
    id: string;
    orderNumber: string;
    status: string;
    paymentStatus: string;
    totalAmount: number;
    createdAt: string;
    items: OrderItem[];
}

const page = usePage();
const orders = ref<Order[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const fetchOrders = async () => {
    try {
        const query = `
            query {
                usersOrders {
                    id
                    orders {
                        id
                        orderNumber
                        status
                        paymentStatus
                        totalAmount
                        createdAt
                        items {
                            id
                            sku
                            quantity
                            price
                            variant {
                                name
                                product {
                                    name
                                }
                            }
                        }
                    }
                }
            }
        `;
        const response = await axios.post('/api/graphql', { query });

        if (response.data.errors) {
            error.value = response.data.errors[0].message;
        } else {
            const userNodes = response.data.data.usersOrders;
            const currentUserId = page.props.auth.user.id;
            const currentUserNode = userNodes.find((u: any) => parseInt(u.id) === parseInt(currentUserId));

            if (currentUserNode) {
                orders.value = currentUserNode.orders || [];
            }
        }
    } catch (e: any) {
        error.value = e.response?.data?.errors?.[0]?.message || e.message || 'Failed to fetch orders.';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchOrders();
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Order history',
                href: '/settings/orders',
            },
        ],
    },
});

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20';
        case 'processing':
            return 'bg-blue-500/10 text-blue-500 border border-blue-500/20';
        case 'pending':
            return 'bg-amber-500/10 text-amber-500 border border-amber-500/20';
        default:
            return 'bg-zinc-500/10 text-zinc-500 border border-zinc-500/20';
    }
};
</script>

<template>
    <Head title="Order History" />

    <h1 class="sr-only">Order history</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Order History"
            description="View and track your previous store purchases."
        />

        <!-- Loading state -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-12 text-muted-foreground animate-pulse text-left">
            <Clock class="h-8 w-8 mb-2 animate-spin text-zinc-400" />
            <p class="text-sm">Retrieving your order history...</p>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="p-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-sm text-left">
            {{ error }}
        </div>

        <!-- Orders list -->
        <div v-else-if="orders.length > 0" class="space-y-6 text-left">
            <div 
                v-for="order in orders" 
                :key="order.id"
                class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-card overflow-hidden shadow-sm"
            >
                <!-- Order Header Info -->
                <div class="bg-muted/40 p-4 border-b border-zinc-200 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-6 text-xs text-muted-foreground">
                        <div>
                            <span class="block uppercase font-semibold text-[10px] tracking-wider">Date Placed</span>
                            <span class="font-medium text-foreground">{{ formatDate(order.createdAt) }}</span>
                        </div>
                        <div>
                            <span class="block uppercase font-semibold text-[10px] tracking-wider">Order ID</span>
                            <span class="font-mono text-foreground font-medium">{{ order.orderNumber }}</span>
                        </div>
                        <div>
                            <span class="block uppercase font-semibold text-[10px] tracking-wider">Total amount</span>
                            <span class="font-bold text-foreground">${{ order.totalAmount.toFixed(2) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(order.status)]">
                            {{ order.status }}
                        </span>
                        <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(order.paymentStatus === 'paid' ? 'completed' : 'pending')]">
                            Payment: {{ order.paymentStatus }}
                        </span>
                    </div>
                </div>

                <!-- Order Items list -->
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800/50 p-4">
                    <div v-for="item in order.items" :key="item.id" class="py-3 first:pt-0 last:pb-0 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-950 border shrink-0">
                            <Package class="h-6 w-6 text-zinc-400 opacity-60" />
                        </div>
                        <div class="flex-1 space-y-1">
                            <h4 class="text-sm font-semibold text-foreground">
                                {{ item.variant?.product?.name || 'Product Variant' }}
                            </h4>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span>Option: {{ item.variant?.name || '-' }}</span>
                                <span class="text-zinc-300 dark:text-zinc-700">|</span>
                                <span class="font-mono">SKU: {{ item.sku }}</span>
                                <span class="text-zinc-300 dark:text-zinc-700">|</span>
                                <span>Qty: {{ item.quantity }}</span>
                            </div>
                        </div>
                        <div class="text-right text-sm font-semibold text-foreground">
                            ${{ parseFloat(item.price as any).toFixed(2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-16 text-center border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl p-8 bg-card">
            <Package class="h-10 w-10 text-muted-foreground opacity-40 mb-3" />
            <h3 class="font-semibold text-base text-foreground">No Orders Yet</h3>
            <p class="text-xs text-muted-foreground max-w-xs mt-1">
                You haven't placed any orders yet. Visit our shop catalog to find product options.
            </p>
        </div>
    </div>
</template>
