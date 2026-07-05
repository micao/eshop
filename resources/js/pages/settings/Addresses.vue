<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPin, Phone, User, Trash2, Plus, Star } from '@lucide/vue';
import axios from 'axios';
import { ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Addresses',
                href: '/settings/addresses',
            },
        ],
    },
});

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

// State
const addresses = ref<Address[]>([]);
const isLoading = ref(false);
const showAddForm = ref(false);

const newAddress = ref({
    recipient_name: '',
    recipient_phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state_province: '',
    postal_code: '',
    country_code: 'BE',
    is_default: false,
});

onMounted(() => {
    fetchAddresses();
});

const fetchAddresses = async () => {
    isLoading.value = true;

    try {
        const response = await axios.get('/api/addresses');
        addresses.value = response.data;
    } catch {
        toast.error('Failed to load saved addresses.');
    } finally {
        isLoading.value = false;
    }
};

const handleAddAddress = async () => {
    if (!newAddress.value.recipient_name || !newAddress.value.recipient_phone || !newAddress.value.address_line_1 || !newAddress.value.city || !newAddress.value.postal_code) {
        toast.error('Please fill in all required fields.');

        return;
    }

    try {
        await axios.post('/api/addresses', newAddress.value);
        toast.success('Address added successfully.');
        showAddForm.value = false;
        
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
            is_default: false,
        };

        fetchAddresses();
    } catch {
        toast.error('Failed to add address.');
    }
};

const handleDeleteAddress = async (id: number) => {
    if (!confirm('Are you sure you want to delete this address?')) {
        return;
    }

    try {
        await axios.delete(`/api/addresses/${id}`);
        toast.success('Address deleted successfully.');
        fetchAddresses();
    } catch {
        toast.error('Failed to delete address.');
    }
};

const handleSetDefault = async (addr: Address) => {
    try {
        await axios.post('/api/addresses', {
            ...addr,
            is_default: true,
        });
        toast.success('Default address updated.');
        fetchAddresses();
    } catch {
        toast.error('Failed to update default address.');
    }
};
</script>

<template>
    <Head title="Saved Addresses" />

    <h1 class="sr-only">Saved Addresses</h1>

    <div class="flex flex-col space-y-6 text-left">
        <div class="flex justify-between items-center">
            <Heading
                variant="small"
                title="Saved Addresses"
                description="Manage your default billing and delivery shipping destinations"
            />
            <Button 
                v-if="!showAddForm" 
                size="sm" 
                class="bg-orange-500 text-white hover:bg-orange-600 font-bold gap-1.5"
                @click="showAddForm = true"
            >
                <Plus class="size-4" /> Add Address
            </Button>
        </div>

        <!-- Add Address Form -->
        <div v-if="showAddForm" class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 bg-zinc-50 dark:bg-zinc-950">
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
                <div class="md:col-span-2 flex items-center gap-2 mt-2">
                    <input type="checkbox" id="is_default" v-model="newAddress.is_default" class="h-4 w-4 rounded border-zinc-300 text-orange-500 focus:ring-orange-500" />
                    <Label for="is_default" class="cursor-pointer select-none">Set as default shipping destination</Label>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2">
                <Button variant="ghost" size="sm" @click="showAddForm = false">Cancel</Button>
                <Button size="sm" class="bg-orange-500 text-white hover:bg-orange-600 font-bold" @click="handleAddAddress">Save Address</Button>
            </div>
        </div>

        <!-- Address List -->
        <div v-if="isLoading" class="flex justify-center py-8">
            <Spinner class="size-6 text-orange-500" />
        </div>

        <div v-else-if="addresses.length > 0" class="grid grid-cols-1 gap-4">
            <div 
                v-for="addr in addresses" 
                :key="addr.id"
                class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 bg-white dark:bg-zinc-900 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-zinc-900 dark:text-zinc-50 flex items-center gap-1.5">
                            <User class="size-4 text-zinc-400" />
                            {{ addr.recipient_name }}
                        </span>
                        <span v-if="addr.is_default" class="text-[9px] uppercase tracking-wider font-bold text-orange-600 bg-orange-600/10 px-2 py-0.5 rounded">Default Shipping</span>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-start gap-1.5 leading-relaxed">
                        <MapPin class="size-4 text-zinc-400 shrink-0 mt-0.5" />
                        <span>
                            {{ addr.address_line_1 }}<span v-if="addr.address_line_2">, {{ addr.address_line_2 }}</span><br/>
                            {{ addr.postal_code }} {{ addr.city }}, {{ addr.country_code }}
                        </span>
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                        <Phone class="size-4 text-zinc-400" />
                        {{ addr.recipient_phone }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 self-end md:self-center">
                    <Button 
                        v-if="!addr.is_default" 
                        variant="outline" 
                        size="sm" 
                        class="gap-1.5 text-xs text-zinc-600 dark:text-zinc-300"
                        @click="handleSetDefault(addr)"
                    >
                        <Star class="size-3.5" /> Set default
                    </Button>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="border-red-500/20 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/10 gap-1.5 text-xs"
                        @click="handleDeleteAddress(addr.id)"
                    >
                        <Trash2 class="size-3.5" /> Delete
                    </Button>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-10 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-zinc-500 text-sm">
            You don't have any saved shipping addresses yet. Add one to speed up checkout.
        </div>
    </div>
</template>
