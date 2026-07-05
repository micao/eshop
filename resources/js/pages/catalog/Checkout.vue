<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MapPin, Phone, User, Check, CreditCard, QrCode, ArrowLeft, Plus } from '@lucide/vue';
import axios from 'axios';
import { ref, onMounted, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';

type Category = {
    id: number;
    name: string;
    slug: string;
};

defineProps<{
    categories?: Category[];
}>();

type Address = {
    id: number;
    recipient_name: string;
    recipient_phone: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state_province: string | null;
    postal_code: string;
    country_code: string;
    is_default: boolean;
};

type ShippingRate = {
    shipping_method_id: number;
    name: string;
    carrier: string;
    cost: number;
    currency: string;
    delivery_days: number;
};

type CartItem = {
    variant_id: number;
    name: string;
    sku: string;
    price: number;
    quantity: number;
    thumbnail: string | null;
};

// State
const addresses = ref<Address[]>([]);
const selectedAddressId = ref<number | null>(null);
const shippingRates = ref<ShippingRate[]>([]);
const selectedRate = ref<ShippingRate | null>(null);
const paymentMethod = ref<'card' | 'bancontact'>('card');
const cartDetails = ref<{ items: CartItem[]; summary: { subtotal: number } } | null>(null);

const isLoadingAddresses = ref(false);
const isLoadingRates = ref(false);
const isLoadingCart = ref(false);
const isSubmitting = ref(false);

// Add Address Form State
const showAddAddressForm = ref(false);
const newAddress = ref({
    recipient_name: '',
    recipient_phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state_province: '',
    postal_code: '',
    country_code: 'BE',
});

// Card details mock fields
const cardName = ref('');
const cardNumber = ref('');
const cardExpiry = ref('');
const cardCvc = ref('');

// Load Initial Data
onMounted(() => {
    fetchCart();
    fetchAddresses();
});

const fetchCart = async () => {
    isLoadingCart.value = true;

    try {
        const response = await axios.get('/api/cart');
        cartDetails.value = response.data;

        if (!response.data.items || response.data.items.length === 0) {
            toast.error('Your cart is empty. Redirecting back to cart.');
            router.visit('/cart');
        }
    } catch {
        toast.error('Failed to load shopping cart.');
    } finally {
        isLoadingCart.value = false;
    }
};

const fetchAddresses = async () => {
    isLoadingAddresses.value = true;

    try {
        const response = await axios.get('/api/addresses');
        addresses.value = response.data;
        
        // Auto select default address
        const def = addresses.value.find(a => a.is_default);

        if (def) {
            selectAddress(def.id);
        } else if (addresses.value.length > 0) {
            selectAddress(addresses.value[0].id);
        }
    } catch {
        toast.error('Failed to load delivery addresses.');
    } finally {
        isLoadingAddresses.value = false;
    }
};

const selectAddress = async (id: number) => {
    selectedAddressId.value = id;
    selectedRate.value = null;
    shippingRates.value = [];
    
    // Fetch shipping rates
    isLoadingRates.value = true;

    try {
        const response = await axios.get(`/api/checkout/shipping-rates?user_address_id=${id}`);
        shippingRates.value = response.data;

        if (shippingRates.value.length > 0) {
            selectedRate.value = shippingRates.value[0];
        }
    } catch {
        toast.error('Failed to fetch shipping rates.');
    } finally {
        isLoadingRates.value = false;
    }
};

const handleAddAddress = async () => {
    if (!newAddress.value.recipient_name || !newAddress.value.recipient_phone || !newAddress.value.address_line_1 || !newAddress.value.city || !newAddress.value.postal_code) {
        toast.error('Please fill in all required address fields.');

        return;
    }
    
    try {
        const response = await axios.post('/api/addresses', newAddress.value);
        toast.success('Address added successfully.');
        showAddAddressForm.value = false;
        
        // Reset form
        newAddress.value = {
            recipient_name: '',
            recipient_phone: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            state_province: '',
            postal_code: '',
            country_code: 'BE',
        };
        
        // Refresh and select the newly created address
        await fetchAddresses();
        const savedAddr = response.data.address;

        if (savedAddr) {
            selectAddress(savedAddr.id);
        }
    } catch {
        toast.error('Failed to create address.');
    }
};

// Computations
const subtotal = computed(() => cartDetails.value?.summary?.subtotal || 0);
const shippingCost = computed(() => selectedRate.value?.cost || 0);
const grandTotal = computed(() => subtotal.value + shippingCost.value);

const handleCheckoutSubmit = async () => {
    if (!selectedAddressId.value) {
        toast.error('Please select a shipping address.');

        return;
    }

    if (!selectedRate.value) {
        toast.error('Please select a delivery shipping option.');

        return;
    }
    
    if (paymentMethod.value === 'card') {
        if (!cardName.value || !cardNumber.value || !cardExpiry.value || !cardCvc.value) {
            toast.error('Please fill in your credit card details.');

            return;
        }
    }
    
    isSubmitting.value = true;

    try {
        const response = await axios.post('/api/checkout', {
            user_address_id: selectedAddressId.value,
            shipping_method_id: selectedRate.value.shipping_method_id,
            payment_method: paymentMethod.value,
        });
        
        toast.success('Order completed successfully!');
        window.dispatchEvent(new CustomEvent('cart-updated'));
        
        // Redirect to success page
        const orderNum = response.data.order.order_number;
        router.visit(`/checkout/success?order_number=${orderNum}`);
    } catch (e: any) {
        const msg = e.response?.data?.message || 'Checkout failed. Please try again.';
        toast.error(msg);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <StorefrontLayout title="Checkout" :categories="categories">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 text-left">
            <!-- Header section -->
            <div class="mb-8 flex items-center gap-4">
                <Link href="/cart" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-white flex items-center gap-1 text-sm font-medium transition-colors">
                    <ArrowLeft class="size-4" />
                    Back to Cart
                </Link>
            </div>

            <!-- Two-Column Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Left Side: Order steps form -->
                <div class="lg:col-span-8 flex flex-col gap-6">
                    <!-- Step 1: Shipping Address -->
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-bold flex items-center gap-2">
                                <span class="size-6 rounded-full bg-orange-500 text-white text-xs font-black flex items-center justify-center">1</span>
                                Shipping Address
                            </h2>
                            <Button v-if="!showAddAddressForm" variant="outline" size="sm" class="gap-1 border-orange-500 text-orange-500 hover:bg-orange-500/10" @click="showAddAddressForm = true">
                                <Plus class="size-3.5" /> Add New Address
                            </Button>
                        </div>

                        <!-- Add Address Form -->
                        <div v-if="showAddAddressForm" class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-950 mb-6">
                            <h3 class="font-bold text-sm mb-4 text-zinc-900 dark:text-zinc-100">Add New Shipping Address</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <Label for="recipient_name">Recipient Full Name *</Label>
                                    <Input id="recipient_name" v-model="newAddress.recipient_name" placeholder="John Doe" class="mt-1" />
                                </div>
                                <div>
                                    <Label for="recipient_phone">Recipient Phone *</Label>
                                    <Input id="recipient_phone" v-model="newAddress.recipient_phone" placeholder="+32 490 00 00 00" class="mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <Label for="address_line_1">Street Address *</Label>
                                    <Input id="address_line_1" v-model="newAddress.address_line_1" placeholder="Rue de la Loi 16" class="mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <Label for="address_line_2">Apartment, Suite, Unit, etc. (Optional)</Label>
                                    <Input id="address_line_2" v-model="newAddress.address_line_2" placeholder="Appt 4B" class="mt-1" />
                                </div>
                                <div>
                                    <Label for="city">City *</Label>
                                    <Input id="city" v-model="newAddress.city" placeholder="Brussels" class="mt-1" />
                                </div>
                                <div>
                                    <Label for="state_province">State / Province</Label>
                                    <Input id="state_province" v-model="newAddress.state_province" placeholder="Brussels-Capital" class="mt-1" />
                                </div>
                                <div>
                                    <Label for="postal_code">Postal Code *</Label>
                                    <Input id="postal_code" v-model="newAddress.postal_code" placeholder="1000" class="mt-1" />
                                </div>
                                <div>
                                    <Label for="country_code">Country *</Label>
                                    <select id="country_code" v-model="newAddress.country_code" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-1 dark:bg-zinc-950 dark:border-zinc-800">
                                        <option value="BE">Belgium</option>
                                        <option value="FR">France</option>
                                        <option value="DE">Germany</option>
                                        <option value="NL">Netherlands</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-end gap-2">
                                <Button variant="ghost" size="sm" @click="showAddAddressForm = false">Cancel</Button>
                                <Button size="sm" class="bg-orange-500 text-white hover:bg-orange-600 font-bold" @click="handleAddAddress">Save Address</Button>
                            </div>
                        </div>

                        <!-- Address Cards List -->
                        <div v-if="addresses.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div 
                                v-for="addr in addresses" 
                                :key="addr.id"
                                :class="[
                                    'border rounded-xl p-4 cursor-pointer relative transition-all duration-200 select-none text-left',
                                    selectedAddressId === addr.id 
                                        ? 'border-orange-500 bg-orange-500/5 ring-1 ring-orange-500' 
                                        : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600'
                                ]"
                                @click="selectAddress(addr.id)"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-sm text-zinc-950 dark:text-zinc-50 flex items-center gap-1.5">
                                        <User class="size-3.5 text-zinc-400" />
                                        {{ addr.recipient_name }}
                                    </span>
                                    <span v-if="addr.is_default" class="text-[10px] uppercase tracking-wider font-bold text-orange-600 bg-orange-600/10 px-1.5 py-0.5 rounded">Default</span>
                                </div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 flex items-start gap-1">
                                    <MapPin class="size-3.5 text-zinc-400 shrink-0 mt-0.5" />
                                    <span>
                                        {{ addr.address_line_1 }}<span v-if="addr.address_line_2">, {{ addr.address_line_2 }}</span><br/>
                                        {{ addr.postal_code }} {{ addr.city }}, {{ addr.country_code }}
                                    </span>
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 flex items-center gap-1.5">
                                    <Phone class="size-3.5 text-zinc-400" />
                                    {{ addr.recipient_phone }}
                                </p>
                                <div v-if="selectedAddressId === addr.id" class="absolute bottom-3 right-3 size-5 rounded-full bg-orange-500 text-white flex items-center justify-center">
                                    <Check class="size-3.5" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty address block -->
                        <div v-else-if="!isLoadingAddresses" class="text-center py-6 text-zinc-500 text-sm">
                            No saved addresses found. Please add a shipping address to estimate delivery rates.
                        </div>
                    </div>

                    <!-- Step 2: Shipping Option -->
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs">
                        <h2 class="text-lg font-bold flex items-center gap-2 mb-6">
                            <span class="size-6 rounded-full bg-orange-500 text-white text-xs font-black flex items-center justify-center">2</span>
                            Delivery Shipping Options
                        </h2>

                        <div v-if="isLoadingRates" class="flex flex-col items-center justify-center py-8 gap-2">
                            <Spinner class="size-6 text-orange-500" />
                            <span class="text-xs text-zinc-500">Estimating delivery prices...</span>
                        </div>

                        <div v-else-if="shippingRates.length > 0" class="flex flex-col gap-3">
                            <div 
                                v-for="rate in shippingRates" 
                                :key="rate.shipping_method_id"
                                :class="[
                                    'border rounded-xl p-4 cursor-pointer relative transition-all duration-200 select-none flex items-center justify-between',
                                    selectedRate?.shipping_method_id === rate.shipping_method_id
                                        ? 'border-orange-500 bg-orange-500/5 ring-1 ring-orange-500'
                                        : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-400'
                                ]"
                                @click="selectedRate = rate"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-orange-500/10 rounded-lg text-orange-500">
                                        <span class="text-xs font-black uppercase">{{ rate.carrier }}</span>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-bold text-sm text-zinc-950 dark:text-zinc-50">{{ rate.name }}</h3>
                                        <p class="text-xs text-zinc-500">Arrives in approx {{ rate.delivery_days }} business day(s)</p>
                                    </div>
                                </div>
                                <span class="font-black text-sm text-zinc-900 dark:text-white">
                                    {{ rate.cost === 0 ? 'FREE' : `€${rate.cost.toFixed(2)}` }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="text-center py-6 text-zinc-500 text-sm">
                            {{ selectedAddressId ? 'No shipping carrier covers this location.' : 'Please select/add a shipping address first.' }}
                        </div>
                    </div>

                    <!-- Step 3: Payment Method -->
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs">
                        <h2 class="text-lg font-bold flex items-center gap-2 mb-6">
                            <span class="size-6 rounded-full bg-orange-500 text-white text-xs font-black flex items-center justify-center">3</span>
                            Payment Gateway Selection
                        </h2>

                        <!-- Selection Buttons -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div 
                                :class="[
                                    'border rounded-xl p-4 cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-2',
                                    paymentMethod === 'card'
                                        ? 'border-orange-500 bg-orange-500/5 ring-1 ring-orange-500 text-orange-500'
                                        : 'border-zinc-200 dark:border-zinc-800 text-zinc-500'
                                ]"
                                @click="paymentMethod = 'card'"
                            >
                                <CreditCard class="size-6" />
                                <span class="font-bold text-sm">Credit Card</span>
                            </div>
                            <div 
                                :class="[
                                    'border rounded-xl p-4 cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-2',
                                    paymentMethod === 'bancontact'
                                        ? 'border-orange-500 bg-orange-500/5 ring-1 ring-orange-500 text-orange-500'
                                        : 'border-zinc-200 dark:border-zinc-800 text-zinc-500'
                                ]"
                                @click="paymentMethod = 'bancontact'"
                            >
                                <QrCode class="size-6" />
                                <span class="font-bold text-sm">Bancontact / QR Code</span>
                            </div>
                        </div>

                        <!-- Card Inputs Simulator -->
                        <div v-if="paymentMethod === 'card'" class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-950 flex flex-col gap-4 text-left">
                            <h3 class="font-bold text-xs uppercase tracking-wider text-zinc-400">Card Payment details (Stripe Demo)</h3>
                            <div>
                                <Label for="card_name" class="text-xs">Cardholder Name</Label>
                                <Input id="card_name" v-model="cardName" placeholder="John Doe" class="mt-1 bg-white dark:bg-zinc-900" />
                            </div>
                            <div>
                                <Label for="card_number" class="text-xs">Card Number</Label>
                                <Input id="card_number" v-model="cardNumber" placeholder="4242 4242 4242 4242" class="mt-1 bg-white dark:bg-zinc-900" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label for="card_expiry" class="text-xs">Expiry Date</Label>
                                    <Input id="card_expiry" v-model="cardExpiry" placeholder="MM/YY" class="mt-1 bg-white dark:bg-zinc-900" />
                                </div>
                                <div>
                                    <Label for="card_cvc" class="text-xs">CVC</Label>
                                    <Input id="card_cvc" v-model="cardCvc" placeholder="123" class="mt-1 bg-white dark:bg-zinc-900" />
                                </div>
                            </div>
                        </div>

                        <!-- Bancontact QR info -->
                        <div v-else class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-950 text-center">
                            <QrCode class="size-16 text-orange-500 mx-auto mb-3" />
                            <h3 class="font-bold text-sm mb-1 text-zinc-900 dark:text-zinc-100">Bancontact Mobile App</h3>
                            <p class="text-xs text-zinc-500 max-w-sm mx-auto">
                                After clicking complete, a secure QR code modal session will display. Scan it using your Bancontact or bank mobile app to finalize the payment instantly.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Order Summary -->
                <div class="lg:col-span-4 flex flex-col gap-6">
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs sticky top-24">
                        <h2 class="text-lg font-bold mb-6">Order Summary</h2>

                        <!-- Cart Items Preview -->
                        <div class="flex flex-col gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-6 mb-6 max-h-64 overflow-y-auto">
                            <div v-if="isLoadingCart" class="flex justify-center py-4">
                                <Spinner class="size-5 text-orange-500" />
                            </div>
                            <div 
                                v-for="item in cartDetails?.items" 
                                :key="item.variant_id"
                                class="flex items-center gap-3"
                            >
                                <img 
                                    v-if="item.thumbnail" 
                                    :src="item.thumbnail" 
                                    class="size-10 rounded-lg object-cover bg-zinc-100 shrink-0" 
                                />
                                <div class="size-10 rounded-lg bg-orange-500/10 text-orange-600 flex items-center justify-center shrink-0" v-else>
                                    <ShoppingBag class="size-5" />
                                </div>
                                <div class="flex-1 min-w-0 text-left">
                                    <h4 class="font-bold text-xs text-zinc-950 dark:text-zinc-50 truncate">{{ item.name }}</h4>
                                    <p class="text-[10px] text-zinc-500">Qty: {{ item.quantity }}</p>
                                </div>
                                <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100">€{{ (item.price * item.quantity).toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- Calculations -->
                        <div class="flex flex-col gap-3 text-xs mb-6 border-b border-zinc-200 dark:border-zinc-800 pb-6">
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Subtotal</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">€{{ subtotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Shipping ({{ selectedRate?.carrier || 'Select address' }})</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ selectedRate ? (shippingCost === 0 ? 'FREE' : `€${shippingCost.toFixed(2)}`) : '—' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between items-baseline mb-6">
                            <span class="font-bold text-zinc-950 dark:text-zinc-100">Total Price</span>
                            <span class="font-black text-xl text-orange-500">€{{ grandTotal.toFixed(2) }}</span>
                        </div>

                        <Button 
                            class="w-full h-11 bg-orange-500 hover:bg-orange-600 text-white font-bold flex items-center justify-center gap-2"
                            :disabled="isSubmitting || !selectedAddressId || !selectedRate"
                            @click="handleCheckoutSubmit"
                        >
                            <Spinner v-if="isSubmitting" class="size-4 text-white" />
                            Pay & Place Order
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
