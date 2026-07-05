<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ShoppingBag, ChevronRight, Layers, Heart, CheckCircle2, AlertCircle } from '@lucide/vue';
import { toast } from 'vue-sonner';

type Variant = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    price: string;
    compare_at_price: string | null;
    inventory_quantity: number;
    track_inventory: boolean;
    continue_selling_out_of_stock: boolean;
    options: Record<string, string>;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    summary: string | null;
    thumbnail: string | null;
    images: string[] | null;
    options: Array<{ name: string; values: string[] }> | null;
    variants: Variant[];
    category: { name: string; slug: string } | null;
};

const props = defineProps<{
    product: Product;
}>();

// Specification choices
const selectedOptions = ref<Record<string, string>>({});

// Initialize default option selections
if (props.product.options && props.product.options.length > 0) {
    props.product.options.forEach(opt => {
        if (opt.values && opt.values.length > 0) {
            selectedOptions.value[opt.name] = opt.values[0];
        }
    });
}

// Find matched variant based on current specification choices
const selectedVariant = computed<Variant | null>(() => {
    if (!props.product.variants || props.product.variants.length === 0) return null;
    
    // If product has no options schema, return the first variant (default variant)
    if (!props.product.options || props.product.options.length === 0) {
        return props.product.variants[0];
    }

    return props.product.variants.find(variant => {
        return Object.entries(selectedOptions.value).every(([key, value]) => {
            return variant.options && variant.options[key] === value;
        });
    }) || null;
});

// Image display state
const activeImage = ref(props.product.thumbnail);

// Quantity selector
const quantity = ref(1);

const incrementQty = () => {
    if (!selectedVariant.value) return;
    const max = selectedVariant.value.track_inventory && !selectedVariant.value.continue_selling_out_of_stock
        ? selectedVariant.value.inventory_quantity
        : 99;
    if (quantity.value < max) quantity.value++;
};

const decrementQty = () => {
    if (quantity.value > 1) quantity.value--;
};

// Check if variant has stock
const isOutOfStock = computed(() => {
    if (!selectedVariant.value) return true;
    if (!selectedVariant.value.track_inventory) return false;
    if (selectedVariant.value.continue_selling_out_of_stock) return false;
    return selectedVariant.value.inventory_quantity <= 0;
});

// Guest cart addition action
const addToCart = () => {
    if (!selectedVariant.value) return;
    
    try {
        const cart = JSON.parse(localStorage.getItem('eshop_cart') || '[]');
        const existingItem = cart.find((item: any) => item.variant_id === selectedVariant.value!.id);
        
        if (existingItem) {
            existingItem.quantity += quantity.value;
        } else {
            cart.push({
                variant_id: selectedVariant.value.id,
                quantity: quantity.value
            });
        }
        
        localStorage.setItem('eshop_cart', JSON.stringify(cart));
        
        // Dispatch custom event to notify StorefrontLayout to update the cart badge
        window.dispatchEvent(new CustomEvent('cart-updated'));
        
        toast.success(`Added ${quantity.value}x ${selectedVariant.value.name} to cart!`);
    } catch (e) {
        toast.error('Failed to add item to cart.');
    }
};
</script>

<template>
    <StorefrontLayout :title="product.name" :categories="$page.props.categories">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-xs font-semibold text-zinc-400 mb-8 overflow-x-auto whitespace-nowrap">
                <Link href="/" class="hover:text-orange-500 transition-colors">Home</Link>
                <ChevronRight class="h-3 w-3" />
                <Link href="/catalog" class="hover:text-orange-500 transition-colors">Catalog</Link>
                <template v-if="product.category">
                    <ChevronRight class="h-3 w-3" />
                    <Link :href="`/catalog?category=${product.category.slug}`" class="hover:text-orange-500 transition-colors">
                        {{ product.category.name }}
                    </Link>
                </template>
                <ChevronRight class="h-3 w-3" />
                <span class="text-zinc-600 dark:text-zinc-300 select-none truncate max-w-[200px]">
                    {{ product.name }}
                </span>
            </nav>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
                <!-- Left: Media Gallery Column -->
                <div class="space-y-4">
                    <div class="aspect-square rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 overflow-hidden flex items-center justify-center p-4">
                        <img 
                            :src="activeImage || 'https://picsum.photos/600/600'" 
                            :alt="product.name" 
                            class="max-h-full max-w-full object-contain rounded-xl"
                        />
                    </div>
                    
                    <!-- Small image thumbnails -->
                    <div 
                        v-if="product.images && product.images.length > 0" 
                        class="grid grid-cols-4 gap-4"
                    >
                        <button 
                            v-if="product.thumbnail"
                            class="aspect-square rounded-lg border bg-white dark:bg-zinc-900 overflow-hidden p-1 focus:outline-none"
                            :class="activeImage === product.thumbnail ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-zinc-200 dark:border-zinc-800'"
                            @click="activeImage = product.thumbnail"
                        >
                            <img :src="product.thumbnail" class="h-full w-full object-cover rounded-md" />
                        </button>
                        <button 
                            v-for="(img, idx) in product.images" 
                            :key="idx"
                            class="aspect-square rounded-lg border bg-white dark:bg-zinc-900 overflow-hidden p-1 focus:outline-none"
                            :class="activeImage === img ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-zinc-200 dark:border-zinc-800'"
                            @click="activeImage = img"
                        >
                            <img :src="img" class="h-full w-full object-cover rounded-md" />
                        </button>
                    </div>
                </div>

                <!-- Right: Product Info & Purchase Column -->
                <div class="space-y-6 text-left">
                    <!-- Heading -->
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Badge v-if="product.category" class="bg-orange-500/10 text-orange-500 border border-orange-500/20 hover:bg-orange-500/10">
                                {{ product.category.name }}
                            </Badge>
                            <Badge v-if="selectedVariant" variant="outline" class="font-mono text-[10px]">
                                {{ selectedVariant.sku }}
                            </Badge>
                        </div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50 leading-tight">
                            {{ product.name }}
                        </h1>
                        <p class="text-sm text-zinc-500">{{ product.summary }}</p>
                    </div>

                    <!-- Pricing section -->
                    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-4" v-if="selectedVariant">
                        <div class="flex items-baseline gap-4">
                            <span class="text-3xl font-black text-zinc-900 dark:text-zinc-50">
                                ${{ parseFloat(selectedVariant.price).toFixed(2) }}
                            </span>
                            <span 
                                v-if="selectedVariant.compare_at_price" 
                                class="text-lg text-zinc-400 line-through font-semibold"
                            >
                                ${{ parseFloat(selectedVariant.compare_at_price).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Option Selection Panel -->
                    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-4 space-y-4" v-if="product.options && product.options.length > 0">
                        <div v-for="option in product.options" :key="option.name" class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                                Select {{ option.name }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    v-for="val in option.values" 
                                    :key="val"
                                    class="px-4 py-2 text-xs font-semibold rounded-lg border transition-all"
                                    :class="selectedOptions[option.name] === val 
                                        ? 'border-orange-500 bg-orange-500/5 text-orange-500 ring-2 ring-orange-500/15' 
                                        : 'border-zinc-200 dark:border-zinc-800 bg-background hover:border-zinc-300 dark:hover:border-zinc-700'"
                                    @click="selectedOptions[option.name] = val"
                                >
                                    {{ val }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory and Actions bar -->
                    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-4 space-y-6">
                        <!-- Inventory Alert -->
                        <div class="flex items-center gap-2 text-xs">
                            <template v-if="isOutOfStock">
                                <AlertCircle class="h-4.5 w-4.5 text-red-500" />
                                <span class="font-bold text-red-500">Temporarily Out of Stock</span>
                            </template>
                            <template v-else-if="selectedVariant && selectedVariant.track_inventory">
                                <CheckCircle2 class="h-4.5 w-4.5 text-emerald-500" />
                                <span class="text-zinc-500">
                                    In stock ({{ selectedVariant.inventory_quantity }} units available)
                                </span>
                            </template>
                            <template v-else>
                                <CheckCircle2 class="h-4.5 w-4.5 text-emerald-500" />
                                <span class="text-zinc-500">In stock</span>
                            </template>
                        </div>

                        <!-- Quantity Selector + Purchase Button -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Qty buttons -->
                            <div class="flex items-center rounded-lg border border-zinc-200 dark:border-zinc-800 bg-background overflow-hidden h-11 w-32 self-start">
                                <button 
                                    class="flex-1 h-full hover:bg-zinc-100 dark:hover:bg-zinc-800 font-bold transition-colors disabled:opacity-30"
                                    :disabled="quantity <= 1"
                                    @click="decrementQty"
                                >
                                    -
                                </button>
                                <span class="flex-1 text-center font-bold text-sm">{{ quantity }}</span>
                                <button 
                                    class="flex-1 h-full hover:bg-zinc-100 dark:hover:bg-zinc-800 font-bold transition-colors"
                                    @click="incrementQty"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Add to Cart -->
                            <Button 
                                class="flex-1 h-11 rounded-lg font-bold bg-orange-500 text-white hover:bg-orange-600 shadow-md shadow-orange-500/10 flex items-center justify-center gap-2"
                                :disabled="isOutOfStock || !selectedVariant"
                                @click="addToCart"
                            >
                                <ShoppingBag class="h-4 w-4" />
                                <span>Add to Cart</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">Description</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed whitespace-pre-line">
                            {{ product.description || 'No detailed specifications or descriptions provided.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
