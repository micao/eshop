<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package, Boxes, AlertTriangle, AlertCircle, ArrowRight, ShieldCheck } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';

type Stats = {
    totalProducts: number;
    totalStock: number;
    lowStockCount: number;
    outOfStockCount: number;
};

type Product = {
    name: string;
    slug: string;
};

type Variant = {
    id: number;
    name: string;
    sku: string;
    price: string;
    inventory_quantity: number;
    options: Record<string, string>;
    product: Product;
};

type CategoryStat = {
    name: string;
    slug: string;
    products_count: number;
    total_stock: number;
};

defineProps<{
    stats: Stats;
    lowStockVariants: Variant[];
    categoriesStats: CategoryStat[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const formatOptions = (options: Record<string, string>) => {
    if (!options || Object.keys(options).length === 0) {
return '';
}

    return Object.entries(options)
        .map(([key, value]) => `${key}: ${value}`)
        .join(' / ');
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-6 text-left">
        <!-- Dashboard Header Welcome -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Welcome to Administration</h1>
                <p class="text-xs text-zinc-500 mt-0.5">Real-time overview of your store catalog and inventory stock metrics.</p>
            </div>
            <div class="flex items-center gap-2 self-start bg-orange-500/10 border border-orange-500/20 text-orange-600 dark:text-orange-400 px-3 py-1.5 rounded-full text-xs font-semibold">
                <ShieldCheck class="h-4 w-4" />
                <span>Secure Administrator Mode</span>
            </div>
        </div>

        <!-- Inventory Metrics Stats Cards Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Total Products -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs flex items-center justify-between group hover:border-orange-500/40 transition-all">
                <div class="space-y-1">
                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Total Products</span>
                    <h3 class="text-3xl font-black tracking-tight text-zinc-900 dark:text-zinc-50">
                        {{ stats.totalProducts }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-orange-500/10 text-orange-500 flex items-center justify-center">
                    <Package class="h-5 w-5" />
                </div>
            </div>

            <!-- Card 2: Total Stock -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs flex items-center justify-between group hover:border-emerald-500/40 transition-all">
                <div class="space-y-1">
                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Total Stock</span>
                    <h3 class="text-3xl font-black tracking-tight text-zinc-900 dark:text-zinc-50">
                        {{ stats.totalStock }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                    <Boxes class="h-5 w-5" />
                </div>
            </div>

            <!-- Card 3: Low Stock Alerts -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs flex items-center justify-between group hover:border-amber-500/40 transition-all"
                 :class="{ 'border-amber-500/30 bg-amber-500/5': stats.lowStockCount > 0 }">
                <div class="space-y-1">
                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Low Stock Items</span>
                    <h3 class="text-3xl font-black tracking-tight text-zinc-900 dark:text-zinc-50"
                        :class="{ 'text-amber-600 dark:text-amber-400': stats.lowStockCount > 0 }">
                        {{ stats.lowStockCount }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center"
                     :class="{ 'bg-amber-500/20': stats.lowStockCount > 0 }">
                    <AlertTriangle class="h-5 w-5" />
                </div>
            </div>

            <!-- Card 4: Out of Stock Alerts -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs flex items-center justify-between group hover:border-red-500/40 transition-all"
                 :class="{ 'border-red-500/30 bg-red-500/5': stats.outOfStockCount > 0 }">
                <div class="space-y-1">
                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Out of Stock</span>
                    <h3 class="text-3xl font-black tracking-tight text-zinc-900 dark:text-zinc-50"
                        :class="{ 'text-red-600 dark:text-red-400': stats.outOfStockCount > 0 }">
                        {{ stats.outOfStockCount }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-red-500/10 text-red-500 flex items-center justify-center"
                     :class="{ 'bg-red-500/20': stats.outOfStockCount > 0 }">
                    <AlertCircle class="h-5 w-5" />
                </div>
            </div>
        </div>

        <!-- Dynamic Tables & Allocation Section -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left: Low Stock Items List Table (2/3 columns) -->
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-md font-bold flex items-center gap-2">
                            <AlertTriangle class="h-4.5 w-4.5 text-amber-500" />
                            <span>Low Stock Alerts</span>
                        </h2>
                        <p class="text-xs text-zinc-400">Inventory variants currently requiring restocking.</p>
                    </div>
                    <Link href="/admin/products" class="text-xs font-bold text-orange-500 hover:text-orange-600 flex items-center gap-1">
                        <span>Manage Products</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>

                <!-- Warnings details table -->
                <div class="overflow-x-auto" v-if="lowStockVariants.length > 0">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800/80 text-zinc-400 text-xs uppercase font-bold">
                                <th class="pb-3">Product Name</th>
                                <th class="pb-3">SKU</th>
                                <th class="pb-3 text-right">Available Qty</th>
                                <th class="pb-3 text-right">Alert Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="variant in lowStockVariants" :key="variant.id" class="border-b border-zinc-100 dark:border-zinc-800/50 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 last:border-0 transition-colors">
                                <td class="py-3">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ variant.product ? variant.product.name : 'Unknown Product' }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-medium">
                                        {{ formatOptions(variant.options) || 'Default variant options' }}
                                    </div>
                                </td>
                                <td class="py-3 font-mono text-xs">
                                    {{ variant.sku }}
                                </td>
                                <td class="py-3 text-right font-black" :class="variant.inventory_quantity <= 0 ? 'text-red-500' : 'text-amber-500'">
                                    {{ variant.inventory_quantity }} units
                                </td>
                                <td class="py-3 text-right">
                                    <Badge v-if="variant.inventory_quantity <= 0" class="bg-red-500/10 text-red-500 border border-red-500/20 hover:bg-red-500/10 font-bold text-[9px] uppercase">
                                        Out of stock
                                    </Badge>
                                    <Badge v-else class="bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/10 font-bold text-[9px] uppercase">
                                        Low stock
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-12 text-center text-zinc-400">
                    <Boxes class="h-8 w-8 opacity-20 mb-2" />
                    <span class="text-xs font-medium">All item stock quantities are currently healthy.</span>
                </div>
            </div>

            <!-- Right: Category stock distribution progress bar analysis (1/3 column) -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 space-y-4">
                <div>
                    <h2 class="text-md font-bold flex items-center gap-2">
                        <Boxes class="h-4.5 w-4.5 text-orange-500" />
                        <span>Categories Distribution</span>
                    </h2>
                    <p class="text-xs text-zinc-400">Inventory allocation metrics compiled by categories.</p>
                </div>

                <!-- Distribution list -->
                <div class="space-y-4" v-if="categoriesStats.length > 0">
                    <div v-for="category in categoriesStats" :key="category.slug" class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ category.name }}</span>
                            <span class="text-zinc-400 font-medium">
                                {{ category.products_count }} prods / <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ category.total_stock }} items</span>
                            </span>
                        </div>
                        <!-- Simple visual progress indicator bar -->
                        <div class="h-2 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500 rounded-full transition-all duration-500"
                                 :style="{ width: `${Math.min(100, Math.max(10, category.total_stock > 0 ? (category.total_stock / Math.max(1, stats.totalStock)) * 100 : 0))}%` }">
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-12 text-center text-zinc-400">
                    <span class="text-xs">No active category records available.</span>
                </div>
            </div>
        </div>
    </div>
</template>
