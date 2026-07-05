<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, Search, ClipboardList, Package, Clock, CreditCard, Truck, User as UserIcon } from '@lucide/vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface ShippingMethod {
    id: number;
    name: string;
    carrier: string;
}

interface OrderItem {
    id: number;
    quantity: number;
    price: number;
    sku: string;
    variant?: {
        name: string;
        product?: {
            name: string;
        };
    };
}

interface Order {
    id: number;
    order_number: string;
    status: 'pending' | 'processing' | 'completed' | 'cancelled';
    payment_status: 'pending' | 'paid' | 'failed';
    payment_method: string | null;
    tracking_number: string | null;
    shipping_name: string;
    shipping_phone: string;
    shipping_address_line_1: string;
    shipping_address_line_2: string | null;
    shipping_city: string;
    shipping_state_province: string | null;
    shipping_postal_code: string;
    shipping_country_code: string;
    subtotal: number;
    shipping_cost: number;
    tax: number;
    grand_total: number;
    created_at: string;
    user: User;
    shipping_method: ShippingMethod | null;
    items: OrderItem[];
}

const props = defineProps<{
    orders: {
        data: Order[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        status?: string;
        payment_status?: string;
        search?: string;
    };
}>();

// State management
const searchQuery = ref(props.filters.search || '');
const filterStatus = ref(props.filters.status || '');
const filterPaymentStatus = ref(props.filters.payment_status || '');

const applyFilters = () => {
    router.get('/admin/orders', {
        search: searchQuery.value,
        status: filterStatus.value,
        payment_status: filterPaymentStatus.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    filterStatus.value = '';
    filterPaymentStatus.value = '';
    router.get('/admin/orders');
};

const selectedOrder = ref<Order | null>(null);
const isViewOpen = ref(false);

const openView = (order: Order) => {
    selectedOrder.value = order;
    isViewOpen.value = true;
};

// Breadcrumb options
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Orders Dashboard',
                href: '/admin/orders',
            },
        ],
    },
});

// Helper for statuses
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20';
        case 'processing':
            return 'bg-blue-500/10 text-blue-500 border border-blue-500/20';
        case 'pending':
            return 'bg-amber-500/10 text-amber-500 border border-amber-500/20';
        case 'cancelled':
            return 'bg-destructive/10 text-destructive border border-destructive/20';
        default:
            return 'bg-zinc-500/10 text-zinc-500 border border-zinc-500/20';
    }
};

const getPaymentBadge = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20';
        case 'pending':
            return 'bg-amber-500/10 text-amber-500 border border-amber-500/20';
        case 'failed':
            return 'bg-destructive/10 text-destructive border border-destructive/20';
        default:
            return 'bg-zinc-500/10 text-zinc-500 border border-zinc-500/20';
    }
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Orders Dashboard" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-foreground">Orders</h2>
                    <p class="text-sm text-muted-foreground">Manage and audit your e-commerce orders, fulfillment, and payment statuses.</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="grid gap-4 md:grid-cols-4 items-end bg-card p-4 rounded-xl border">
                <div class="grid gap-2">
                    <Label for="search" class="text-xs font-semibold text-muted-foreground uppercase">Search Orders</Label>
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground opacity-50" />
                        <Input
                            id="search"
                            v-model="searchQuery"
                            placeholder="Order #, name, email"
                            class="pl-9"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="status" class="text-xs font-semibold text-muted-foreground uppercase">Fulfillment Status</Label>
                    <select
                        id="status"
                        v-model="filterStatus"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="applyFilters"
                    >
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="payment_status" class="text-xs font-semibold text-muted-foreground uppercase">Payment Status</Label>
                    <select
                        id="payment_status"
                        v-model="filterPaymentStatus"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="applyFilters"
                    >
                        <option value="">All Payments</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <Button @click="applyFilters" class="flex-1">Apply</Button>
                    <Button variant="outline" @click="resetFilters">Reset</Button>
                </div>
            </div>

            <!-- Orders Table List -->
            <div v-if="orders.data.length > 0" class="rounded-xl border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Order</th>
                                <th class="p-4">Date</th>
                                <th class="p-4">Customer</th>
                                <th class="p-4">Payment</th>
                                <th class="p-4">Fulfillment</th>
                                <th class="p-4">Shipping</th>
                                <th class="p-4 text-right">Total</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm">
                            <tr v-for="order in orders.data" :key="order.id" class="hover:bg-muted/10 transition-colors">
                                <td class="p-4 font-mono font-medium text-foreground">
                                    {{ order.order_number }}
                                </td>
                                <td class="p-4 text-muted-foreground whitespace-nowrap">
                                    {{ formatDate(order.created_at) }}
                                </td>
                                <td class="p-4">
                                    <div class="font-medium text-foreground">{{ order.shipping_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ order.user?.email || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold border', getPaymentBadge(order.payment_status)]">
                                        {{ order.payment_status }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold border', getStatusBadge(order.status)]">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-muted-foreground whitespace-nowrap">
                                    <div>{{ order.shipping_method?.name || 'Standard' }}</div>
                                    <div v-if="order.tracking_number" class="text-xs font-mono font-semibold">{{ order.tracking_number }}</div>
                                </td>
                                <td class="p-4 text-right font-semibold text-foreground">
                                    ${{ parseFloat(order.grand_total as any).toFixed(2) }}
                                </td>
                                <td class="p-4 text-center">
                                    <Button size="icon" variant="ghost" @click="openView(order)">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center min-h-[40vh] border border-dashed rounded-xl p-8 text-center bg-card">
                <ClipboardList class="h-12 w-12 text-muted-foreground opacity-50 mb-4" />
                <h3 class="font-semibold text-lg text-foreground">No Orders Found</h3>
                <p class="text-sm text-muted-foreground max-w-xs mt-1">
                    {{ searchQuery ? 'Try adjusting your filters or search query.' : 'There are no customer orders in the system yet.' }}
                </p>
                <Button v-if="searchQuery || filterStatus || filterPaymentStatus" variant="outline" class="mt-4" @click="resetFilters">
                    Clear Filters
                </Button>
            </div>

            <!-- Pagination section -->
            <div v-if="orders.last_page > 1" class="flex items-center justify-between border-t pt-6">
                <div class="text-xs text-muted-foreground">
                    Showing page {{ orders.current_page }} of {{ orders.last_page }}
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="link in orders.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1.5 text-xs rounded-md border bg-background transition-all hover:bg-zinc-50 dark:hover:bg-zinc-900"
                        :class="{ 
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-black pointer-events-none font-medium': link.active,
                            'opacity-50 pointer-events-none': !link.url && !link.active 
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- View Detail Slide-Over Sheet -->
        <Sheet v-model:open="isViewOpen">
            <SheetContent class="sm:max-w-xl overflow-y-auto bg-card border-l border-sidebar-border dark:border-zinc-800">
                <SheetHeader class="text-left" v-if="selectedOrder">
                    <div class="flex items-center justify-between mt-4">
                        <Badge :class="getStatusBadge(selectedOrder.status)">Fulfillment: {{ selectedOrder.status }}</Badge>
                        <Badge :class="getPaymentBadge(selectedOrder.payment_status)">Payment: {{ selectedOrder.payment_status }}</Badge>
                    </div>
                    <SheetTitle class="text-xl font-bold mt-2">Order Detail: {{ selectedOrder.order_number }}</SheetTitle>
                    <SheetDescription class="text-xs text-muted-foreground">Placed on {{ formatDate(selectedOrder.created_at) }}</SheetDescription>
                </SheetHeader>

                <div class="space-y-6 mt-6" v-if="selectedOrder">
                    <!-- Customer details -->
                    <div class="border rounded-lg p-4 bg-muted/20">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-2">
                            <UserIcon class="h-3 w-3" /> Customer details
                        </h4>
                        <div class="text-sm space-y-1">
                            <div><strong>Name:</strong> {{ selectedOrder.shipping_name }}</div>
                            <div><strong>Email:</strong> {{ selectedOrder.user?.email || '-' }}</div>
                            <div><strong>Phone:</strong> {{ selectedOrder.shipping_phone }}</div>
                        </div>
                    </div>

                    <!-- Delivery Information -->
                    <div class="border rounded-lg p-4 bg-muted/20">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-2">
                            <Truck class="h-3 w-3" /> Shipping details
                        </h4>
                        <div class="text-sm space-y-1">
                            <div><strong>Method:</strong> {{ selectedOrder.shipping_method?.name || 'Standard' }} (Carrier: {{ selectedOrder.shipping_method?.carrier || '-' }})</div>
                            <div v-if="selectedOrder.tracking_number"><strong>Tracking Number:</strong> <span class="font-mono font-semibold">{{ selectedOrder.tracking_number }}</span></div>
                            <div><strong>Address:</strong></div>
                            <div class="pl-2 border-l-2 text-muted-foreground">
                                <div>{{ selectedOrder.shipping_address_line_1 }}</div>
                                <div v-if="selectedOrder.shipping_address_line_2">{{ selectedOrder.shipping_address_line_2 }}</div>
                                <div>{{ selectedOrder.shipping_city }}, {{ selectedOrder.shipping_state_province || '' }} {{ selectedOrder.shipping_postal_code }}</div>
                                <div>{{ selectedOrder.shipping_country_code }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-2">
                            <Package class="h-3 w-3" /> Order Items
                        </h4>
                        <div class="divide-y border rounded-lg overflow-hidden">
                            <div v-for="item in selectedOrder.items" :key="item.id" class="p-3 flex items-start justify-between gap-4 bg-muted/5">
                                <div class="space-y-1">
                                    <div class="font-semibold text-sm">{{ item.variant?.product?.name || 'Product Variant' }}</div>
                                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                                        <span>Option: {{ item.variant?.name || '-' }}</span>
                                        <span>•</span>
                                        <span class="font-mono text-[10px]">SKU: {{ item.sku }}</span>
                                        <span>•</span>
                                        <span>Qty: {{ item.quantity }}</span>
                                    </div>
                                </div>
                                <div class="text-right text-sm font-semibold">${{ (item.price * item.quantity).toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Financial summary -->
                    <div class="border rounded-lg p-4 bg-muted/20 text-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>${{ parseFloat(selectedOrder.subtotal as any).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Shipping Cost</span>
                            <span>${{ parseFloat(selectedOrder.shipping_cost as any).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tax</span>
                            <span>${{ parseFloat(selectedOrder.tax as any).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 font-bold text-base text-foreground">
                            <span>Grand Total</span>
                            <span>${{ parseFloat(selectedOrder.grand_total as any).toFixed(2) }}</span>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
