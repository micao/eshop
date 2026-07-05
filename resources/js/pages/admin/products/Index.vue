<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Eye, Edit, Trash2, Search, Package, AlertTriangle, Layers } from '@lucide/vue';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
}

interface Supplier {
    id: number;
    name: string;
    slug: string;
}

interface Variant {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    price: number;
    compare_at_price: number | null;
    inventory_quantity: number;
    options: Record<string, string>;
}

interface Product {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    summary: string | null;
    status: 'active' | 'draft' | 'archived';
    thumbnail: string | null;
    images: string[] | null;
    options: Array<{ name: string; values: string[] }> | null;
    variants: Variant[];
    brand: Brand | null;
    supplier: Supplier | null;
    created_at: string;
}

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
    suppliers: Supplier[];
    filters: {
        category_id?: string;
        brand_id?: string;
        supplier_id?: string;
        price_min?: string;
        price_max?: string;
    };
}>();

// State management
const searchQuery = ref('');
const categoryId = ref(props.filters.category_id || '');
const brandId = ref(props.filters.brand_id || '');
const supplierId = ref(props.filters.supplier_id || '');
const priceMin = ref(props.filters.price_min || '');
const priceMax = ref(props.filters.price_max || '');

const applyAdminFilters = () => {
    router.get('/admin/products', {
        category_id: categoryId.value,
        brand_id: brandId.value,
        supplier_id: supplierId.value,
        price_min: priceMin.value,
        price_max: priceMax.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetAdminFilters = () => {
    categoryId.value = '';
    brandId.value = '';
    supplierId.value = '';
    priceMin.value = '';
    priceMax.value = '';
    router.get('/admin/products');
};

const selectedProduct = ref<Product | null>(null);
const isViewOpen = ref(false);
const isEditOpen = ref(false);
const isDeleteOpen = ref(false);
const isDeleting = ref(false);

// Local search filter
const filteredProducts = computed(() => {
    if (!searchQuery.value) {
return props.products.data;
}

    const query = searchQuery.value.toLowerCase();

    return props.products.data.filter(product => 
        product.name.toLowerCase().includes(query) || 
        product.slug.toLowerCase().includes(query) ||
        (product.summary && product.summary.toLowerCase().includes(query)) ||
        product.variants.some(v => v.sku.toLowerCase().includes(query) || (v.barcode && v.barcode.includes(query)))
    );
});

// Edit form definition
const form = useForm({
    name: '',
    status: 'active' as 'active' | 'draft' | 'archived',
    summary: '',
    description: ''
});

// Action triggers
const openView = (product: Product) => {
    selectedProduct.value = product;
    isViewOpen.value = true;
};

const openEdit = (product: Product) => {
    selectedProduct.value = product;
    form.name = product.name;
    form.status = product.status;
    form.summary = product.summary || '';
    form.description = product.description || '';
    isEditOpen.value = true;
};

const submitEdit = () => {
    if (!selectedProduct.value) {
return;
}

    form.put(`/admin/products/${selectedProduct.value.id}`, {
        onSuccess: () => {
            isEditOpen.value = false;
        }
    });
};

const openDelete = (product: Product) => {
    selectedProduct.value = product;
    isDeleteOpen.value = true;
};

const confirmDelete = () => {
    if (!selectedProduct.value) {
return;
}

    isDeleting.value = true;
    router.delete(`/admin/products/${selectedProduct.value.id}`, {
        onSuccess: () => {
            isDeleteOpen.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

// Breadcrumb options
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Products Dashboard',
                href: '/admin/products',
            },
        ],
    },
});

// Helper for status classes
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-500/10';
        case 'draft':
            return 'bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/10';
        case 'archived':
            return 'bg-zinc-500/10 text-zinc-400 border border-zinc-500/20 hover:bg-zinc-500/10';
        default:
            return 'bg-zinc-500/10 text-zinc-500';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Products Management" />

        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Products Dashboard</h1>
                    <p class="text-sm text-muted-foreground">Manage your product catalog, view variants, edit metadata, and soft-delete items.</p>
                </div>
                <div class="relative w-full max-w-sm sm:w-80">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Search products, SKUs..." 
                        class="pl-9 w-full bg-background"
                    />
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 bg-muted/20 p-4 rounded-xl border border-sidebar-border dark:border-zinc-800 text-left">
                <!-- Category Select -->
                <div class="space-y-1.5">
                    <Label for="admin_category_filter" class="text-xs font-semibold text-muted-foreground">Category</Label>
                    <select
                        id="admin_category_filter"
                        v-model="categoryId"
                        @change="applyAdminFilters"
                        class="h-9 w-full text-xs rounded-md border border-input bg-background px-3 shadow-xs focus-visible:outline-none"
                    >
                        <option value="">All Categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <!-- Brand Select -->
                <div class="space-y-1.5">
                    <Label for="admin_brand_filter" class="text-xs font-semibold text-muted-foreground">Brand</Label>
                    <select
                        id="admin_brand_filter"
                        v-model="brandId"
                        @change="applyAdminFilters"
                        class="h-9 w-full text-xs rounded-md border border-input bg-background px-3 shadow-xs focus-visible:outline-none"
                    >
                        <option value="">All Brands</option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <!-- Supplier Select -->
                <div class="space-y-1.5">
                    <Label for="admin_supplier_filter" class="text-xs font-semibold text-muted-foreground">Supplier</Label>
                    <select
                        id="admin_supplier_filter"
                        v-model="supplierId"
                        @change="applyAdminFilters"
                        class="h-9 w-full text-xs rounded-md border border-input bg-background px-3 shadow-xs focus-visible:outline-none"
                    >
                        <option value="">All Suppliers</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Price Min/Max Inputs -->
                <div class="space-y-1.5 col-span-1 sm:col-span-2 md:col-span-2 flex flex-col justify-end">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 space-y-1.5">
                            <Label for="admin_price_min" class="text-xs font-semibold text-muted-foreground">Price Min</Label>
                            <Input
                                id="admin_price_min"
                                v-model="priceMin"
                                type="number"
                                placeholder="Min"
                                class="h-9 bg-background text-xs"
                                @keyup.enter="applyAdminFilters"
                            />
                        </div>
                        <div class="flex-1 space-y-1.5">
                            <Label for="admin_price_max" class="text-xs font-semibold text-muted-foreground">Price Max</Label>
                            <Input
                                id="admin_price_max"
                                v-model="priceMax"
                                type="number"
                                placeholder="Max"
                                class="h-9 bg-background text-xs"
                                @keyup.enter="applyAdminFilters"
                            />
                        </div>
                        <div class="flex gap-1.5 self-end pb-0.5">
                            <Button size="sm" class="h-9 text-xs" @click="applyAdminFilters">Apply</Button>
                            <Button size="sm" variant="ghost" class="h-9 text-xs" @click="resetAdminFilters">Clear</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <div v-if="filteredProducts.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div 
                    v-for="product in filteredProducts" 
                    :key="product.id"
                    class="group relative flex flex-col overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm transition-all duration-300 hover:shadow-md dark:border-zinc-800"
                >
                    <!-- Thumbnail Section -->
                    <div class="relative aspect-square w-full overflow-hidden bg-zinc-100 dark:bg-zinc-900 border-b border-sidebar-border dark:border-zinc-800">
                        <img 
                            v-if="product.thumbnail" 
                            :src="product.thumbnail" 
                            :alt="product.name" 
                            class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
                            <Package class="h-12 w-12 opacity-40" />
                        </div>

                        <!-- Hover Overlay (View, Edit, Delete) -->
                        <div class="absolute inset-0 flex items-center justify-center gap-2 bg-zinc-950/70 opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover:opacity-100">
                            <Button 
                                size="icon" 
                                variant="secondary" 
                                class="h-10 w-10 rounded-full" 
                                @click="openView(product)"
                                title="View details"
                            >
                                <Eye class="h-4 w-4" />
                            </Button>
                            <Button 
                                size="icon" 
                                variant="secondary" 
                                class="h-10 w-10 rounded-full" 
                                @click="openEdit(product)"
                                title="Edit metadata"
                            >
                                <Edit class="h-4 w-4" />
                            </Button>
                            <Button 
                                size="icon" 
                                variant="destructive" 
                                class="h-10 w-10 rounded-full" 
                                @click="openDelete(product)"
                                title="Delete product"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <!-- Options indicators -->
                        <div class="absolute bottom-2 left-2 flex gap-1">
                            <Badge v-for="option in product.options" :key="option.name" variant="outline" class="bg-black/50 text-[10px] text-white border-none py-0.5">
                                {{ option.name }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div class="flex flex-1 flex-col p-4 gap-2">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-foreground line-clamp-1 group-hover:text-primary transition-colors">
                                {{ product.name }}
                            </h3>
                            <Badge :class="getStatusBadge(product.status)">
                                {{ product.status }}
                            </Badge>
                        </div>

                        <p class="text-xs text-muted-foreground line-clamp-2 min-h-[32px]">
                            {{ product.summary || 'No summary available.' }}
                        </p>

                        <div class="mt-auto flex items-center justify-between border-t border-sidebar-border dark:border-zinc-800 pt-3 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <Layers class="h-3.5 w-3.5" />
                                <span>{{ product.variants.length }} Variant{{ product.variants.length > 1 ? 's' : '' }}</span>
                            </div>
                            <span v-if="product.variants[0]" class="font-medium text-foreground text-sm">
                                ${{ parseFloat(product.variants[0].price).toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center min-h-[40vh] border border-dashed border-sidebar-border rounded-xl p-8 text-center bg-card">
                <Package class="h-12 w-12 text-muted-foreground opacity-50 mb-4" />
                <h3 class="font-semibold text-lg text-foreground">No Products Found</h3>
                <p class="text-sm text-muted-foreground max-w-xs mt-1">
                    {{ searchQuery ? 'Try adjusting your search query or check for typos.' : 'Your product catalog is empty. Start seeding or importing data.' }}
                </p>
                <Button v-if="searchQuery" variant="outline" class="mt-4" @click="searchQuery = ''">
                    Clear Search
                </Button>
            </div>

            <!-- Pagination section -->
            <div v-if="products.last_page > 1" class="flex items-center justify-between border-t border-sidebar-border dark:border-zinc-800 pt-6">
                <div class="text-xs text-muted-foreground">
                    Showing page {{ products.current_page }} of {{ products.last_page }}
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1.5 text-xs rounded-md border border-sidebar-border bg-background transition-all hover:bg-zinc-50 dark:hover:bg-zinc-900"
                        :class="{ 
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-black pointer-events-none font-medium': link.active,
                            'opacity-50 pointer-events-none': !link.url && !link.active 
                        }"
                    >
                        <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- View Detail Slide-Over Sheet -->
        <Sheet v-model:open="isViewOpen">
            <SheetContent class="sm:max-w-xl overflow-y-auto bg-card border-l border-sidebar-border dark:border-zinc-800">
                <SheetHeader class="text-left" v-if="selectedProduct">
                    <div class="flex items-center justify-between mt-4">
                        <Badge :class="getStatusBadge(selectedProduct.status)">{{ selectedProduct.status }}</Badge>
                        <span class="text-xs text-muted-foreground">ID: {{ selectedProduct.id }}</span>
                    </div>
                    <SheetTitle class="text-xl font-bold mt-2">{{ selectedProduct.name }}</SheetTitle>
                    <SheetDescription class="text-sm text-muted-foreground">{{ selectedProduct.summary }}</SheetDescription>
                </SheetHeader>

                <div class="space-y-6 mt-6" v-if="selectedProduct">
                    <!-- Media Gallery -->
                    <div v-if="selectedProduct.images && selectedProduct.images.length > 0">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Media Gallery</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="(img, index) in selectedProduct.images" :key="index" class="aspect-square rounded-lg overflow-hidden border border-sidebar-border">
                                <img :src="img" alt="Product image" class="h-full w-full object-cover" />
                            </div>
                        </div>
                    </div>

                    <!-- Brand and Supplier info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-muted/20 border border-sidebar-border dark:border-zinc-800 rounded-lg">
                            <span class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Brand</span>
                            <span class="text-sm font-medium text-foreground">{{ selectedProduct.brand ? selectedProduct.brand.name : 'No Brand' }}</span>
                        </div>
                        <div class="p-3 bg-muted/20 border border-sidebar-border dark:border-zinc-800 rounded-lg">
                            <span class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Supplier</span>
                            <span class="text-sm font-medium text-foreground">{{ selectedProduct.supplier ? selectedProduct.supplier.name : 'No Supplier' }}</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Description</h4>
                        <p class="text-sm text-foreground leading-relaxed whitespace-pre-line bg-muted/30 p-3 rounded-lg border">
                            {{ selectedProduct.description || 'No detailed description available.' }}
                        </p>
                    </div>

                    <!-- Product Specifications Schema -->
                    <div v-if="selectedProduct.options && selectedProduct.options.length > 0">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Specifications Schema</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <div v-for="option in selectedProduct.options" :key="option.name" class="p-3 bg-muted/30 border rounded-lg">
                                <span class="text-xs font-medium text-muted-foreground">{{ option.name }}</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <Badge v-for="val in option.values" :key="val" variant="secondary" class="text-[10px]">
                                        {{ val }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variants Table list -->
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Variants list</h4>
                        <div class="space-y-3">
                            <div v-for="variant in selectedProduct.variants" :key="variant.id" class="p-3 border rounded-lg bg-muted/10 space-y-2">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h5 class="text-sm font-semibold text-foreground">{{ variant.name }}</h5>
                                        <span class="text-xs text-muted-foreground font-mono">SKU: {{ variant.sku }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-foreground">${{ parseFloat(variant.price).toFixed(2) }}</span>
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-xs text-muted-foreground pt-2 border-t border-dashed">
                                    <div>
                                        <span class="block text-[10px] uppercase font-semibold text-muted-foreground/75">Stock</span>
                                        <span class="font-medium" :class="variant.inventory_quantity > 0 ? 'text-foreground' : 'text-destructive font-bold'">
                                            {{ variant.inventory_quantity > 0 ? `${variant.inventory_quantity} units` : 'Out of Stock' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-semibold text-muted-foreground/75">Barcode</span>
                                        <span class="font-medium font-mono">{{ variant.barcode || '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-semibold text-muted-foreground/75">Options</span>
                                        <span class="font-medium line-clamp-1">
                                            {{ Object.entries(variant.options).map(([k, v]) => `${k}:${v}`).join(', ') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Modify Details Dialog Modal -->
        <Dialog v-model:open="isEditOpen">
            <DialogContent class="sm:max-w-[500px] bg-card border border-sidebar-border dark:border-zinc-800">
                <DialogHeader>
                    <DialogTitle>Modify Product</DialogTitle>
                    <DialogDescription>
                        Update the general descriptive properties of this product. Click save to publish changes.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="name" class="text-right">Name</Label>
                        <Input 
                            id="name" 
                            v-model="form.name" 
                            class="col-span-3 bg-background" 
                            required
                        />
                        <span v-if="form.errors.name" class="col-start-2 col-span-3 text-xs text-destructive">{{ form.errors.name }}</span>
                    </div>

                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="status" class="text-right">Status</Label>
                        <select 
                            id="status" 
                            v-model="form.status" 
                            class="col-span-3 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                        <span v-if="form.errors.status" class="col-start-2 col-span-3 text-xs text-destructive">{{ form.errors.status }}</span>
                    </div>

                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="summary" class="text-right">Summary</Label>
                        <Input 
                            id="summary" 
                            v-model="form.summary" 
                            class="col-span-3 bg-background" 
                        />
                        <span v-if="form.errors.summary" class="col-start-2 col-span-3 text-xs text-destructive">{{ form.errors.summary }}</span>
                    </div>

                    <div class="grid grid-cols-4 items-start gap-4">
                        <Label for="description" class="text-right pt-2">Description</Label>
                        <textarea 
                            id="description" 
                            v-model="form.description" 
                            rows="4"
                            class="col-span-3 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        ></textarea>
                        <span v-if="form.errors.description" class="col-start-2 col-span-3 text-xs text-destructive">{{ form.errors.description }}</span>
                    </div>

                    <DialogFooter class="pt-4 border-t">
                        <Button type="button" variant="outline" @click="isEditOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Soft-Delete Dialog Confirmation -->
        <Dialog v-model:open="isDeleteOpen">
            <DialogContent class="sm:max-w-[420px] bg-card border border-sidebar-border dark:border-zinc-800">
                <DialogHeader class="flex flex-col items-center text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive mb-3">
                        <AlertTriangle class="h-6 w-6" />
                    </div>
                    <DialogTitle>Soft-Delete Product?</DialogTitle>
                    <DialogDescription class="pt-2">
                        Are you sure you want to delete **{{ selectedProduct?.name }}**? This product will be hidden from the catalog, but can be restored from the database.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex flex-col sm:flex-row gap-2 mt-4 justify-center">
                    <Button type="button" variant="outline" @click="isDeleteOpen = false" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" @click="confirmDelete" :disabled="isDeleting" class="w-full sm:w-auto">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
