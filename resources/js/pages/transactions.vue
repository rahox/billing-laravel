<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const items = ref([])
const loading = ref(false)
const statusFilter = ref('draft')
const selected = ref([])
const customers = ref([])
const products = ref([])
const discounts = ref([])

const headers = [
  { title: '', key: 'data-table-select', sortable: false },
  { title: 'No. Transaksi', key: 'transaction_number' },
  { title: 'Pelanggan', key: 'customer.name' },
  { title: 'Produk', key: 'product.name' },
  { title: 'Periode', key: 'period' },
  { title: 'Subtotal', key: 'subtotal' },
  { title: 'Total', key: 'total' },
  { title: 'Status', key: 'status' },
]

async function loadItems() {
  loading.value = true
  selected.value = []
  try {
    const { data } = await client.get('/transactions', { params: { status: statusFilter.value || undefined, per_page: 50 } })

    items.value = data.data
  }
  finally {
    loading.value = false
  }
}

async function loadDropdowns() {
  const [customerRes, productRes, discountRes] = await Promise.all([
    client.get('/customers', { params: { per_page: 100, status: 'active' } }),
    client.get('/products', { params: { per_page: 50, active_only: true } }),
    client.get('/discounts', { params: { per_page: 50, active_only: true } }),
  ])

  customers.value = customerRes.data.data
  products.value = productRes.data.data
  discounts.value = discountRes.data.data
}

watch(statusFilter, loadItems)
onMounted(() => {
  loadItems()
  loadDropdowns()
})

const selectedItems = computed(() => items.value.filter(i => selected.value.includes(i.id)))

const canBuildInvoice = computed(() => {
  if (selected.value.length === 0)
    return false

  const customerIds = new Set(selectedItems.value.map(i => i.customer_id))

  return customerIds.size === 1
})

const canBuildDeliveryOrder = computed(() => {
  if (selected.value.length === 0)
    return false

  const customerIds = new Set(selectedItems.value.map(i => i.customer_id))
  const allBarang = selectedItems.value.every(i => i.product?.type === 'barang' && !i.delivery_order_id)

  return customerIds.size === 1 && allBarang
})

const dialog = ref(false)
const saving = ref(false)
const errors = ref({})
const selectedProduct = computed(() => products.value.find(p => p.id === form.value.product_id))
const form = ref({
  customer_id: null,
  product_id: null,
  discount_id: null,
  qty: 1,
  transaction_date: new Date().toISOString().slice(0, 10),
  period_start: null,
  period_end: null,
  activation_date: null,
  notes: '',
})

function openCreate() {
  errors.value = {}
  form.value = {
    customer_id: null, product_id: null, discount_id: null, qty: 1,
    transaction_date: new Date().toISOString().slice(0, 10),
    period_start: null, period_end: null, activation_date: null, notes: '',
  }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    await client.post('/transactions', form.value)
    dialog.value = false
    loadItems()
  }
  catch (error) {
    errors.value = error.response?.data?.errors ?? {}
  }
  finally {
    saving.value = false
  }
}

const invoicing = ref(false)
async function buildInvoice() {
  invoicing.value = true
  try {
    await client.post('/invoices', { transaction_ids: selected.value })
    loadItems()
  }
  finally {
    invoicing.value = false
  }
}

const doDialog = ref(false)
const doSaving = ref(false)
const doErrors = ref({})
const doForm = ref({
  delivery_date: new Date().toISOString().slice(0, 10),
  recipient_name: '',
  address: '',
  courier: '',
  tracking_number: '',
  notes: '',
})

function openDeliveryOrderDialog() {
  doErrors.value = {}
  const customer = customers.value.find(c => c.id === selectedItems.value[0]?.customer_id)

  doForm.value = {
    delivery_date: new Date().toISOString().slice(0, 10),
    recipient_name: customer?.name ?? '',
    address: customer?.address ?? '',
    courier: '',
    tracking_number: '',
    notes: '',
  }
  doDialog.value = true
}

async function saveDeliveryOrder() {
  doSaving.value = true
  doErrors.value = {}
  try {
    await client.post('/delivery-orders', { ...doForm.value, transaction_ids: selected.value })
    doDialog.value = false
    loadItems()
  }
  catch (error) {
    doErrors.value = error.response?.data?.errors ?? {}
  }
  finally {
    doSaving.value = false
  }
}
</script>

<template>
  <VCard title="Transaksi (Item Invoice)">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <VSelect
            v-model="statusFilter"
            :items="[{ title: 'Draft (belum diinvoice)', value: 'draft' }, { title: 'Sudah Diinvoice', value: 'invoiced' }, { title: 'Semua', value: null }]"
            label="Status"
          />
        </VCol>
        <VCol cols="12" md="8" class="d-flex justify-end align-center gap-2">
          <VBtn
            v-if="statusFilter === 'draft'"
            color="success"
            prepend-icon="ri-file-add-line"
            :disabled="!canBuildInvoice"
            :loading="invoicing"
            @click="buildInvoice"
          >
            Buat Invoice dari Terpilih ({{ selected.length }})
          </VBtn>
          <VBtn
            color="info"
            prepend-icon="ri-truck-line"
            :disabled="!canBuildDeliveryOrder"
            @click="openDeliveryOrderDialog"
          >
            Buat Delivery Order dari Terpilih ({{ selected.length }})
          </VBtn>
          <VBtn prepend-icon="ri-add-line" @click="openCreate">
            Tambah Transaksi
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable
      v-model="selected"
      :items="items"
      :headers="headers"
      :loading="loading"
      :items-per-page="50"
      show-select
      item-value="id"
    >
      <template #item.period="{ item }">
        <span v-if="item.period_start">{{ formatDate(item.period_start) }} - {{ formatDate(item.period_end) }}</span>
        <span v-else>-</span>
      </template>
      <template #item.subtotal="{ item }">
        {{ formatCurrency(item.subtotal) }}
      </template>
      <template #item.total="{ item }">
        {{ formatCurrency(item.total) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="item.status === 'draft' ? 'warning' : 'success'">
          {{ item.status }}
        </VChip>
      </template>
    </VDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="600">
    <VCard title="Tambah Transaksi">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-model="form.customer_id"
                :items="customers"
                item-title="name"
                item-value="id"
                label="Pelanggan"
                :error-messages="errors.customer_id"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="form.product_id"
                :items="products"
                item-title="name"
                item-value="id"
                label="Produk"
                :error-messages="errors.product_id"
              />
            </VCol>
            <VCol cols="6">
              <VTextField v-model.number="form.qty" type="number" label="Qty" min="1" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.transaction_date" type="date" label="Tanggal Transaksi" />
            </VCol>
            <VCol v-if="selectedProduct?.is_recurring" cols="6">
              <VTextField v-model="form.period_start" type="date" label="Awal Periode" />
            </VCol>
            <VCol v-if="selectedProduct?.is_recurring" cols="6">
              <VTextField v-model="form.period_end" type="date" label="Akhir Periode" />
            </VCol>
            <VCol cols="12">
              <VSelect v-model="form.discount_id" :items="discounts" item-title="name" item-value="id" label="Diskon (opsional)" clearable />
            </VCol>
            <VCol v-if="discounts.find(d => d.id === form.discount_id)?.mode === 'prorate_activation'" cols="12">
              <VTextField v-model="form.activation_date" type="date" label="Tanggal Aktivasi (untuk hitung prorate)" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.notes" label="Catatan" rows="2" />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="text" @click="dialog = false">
              Batal
            </VBtn>
            <VBtn type="submit" :loading="saving">
              Simpan
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog v-model="doDialog" max-width="600">
    <VCard title="Buat Delivery Order">
      <VCardText>
        <VForm @submit.prevent="saveDeliveryOrder">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="doForm.delivery_date" type="date" label="Tanggal Kirim" :error-messages="doErrors.delivery_date" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="doForm.recipient_name" label="Nama Penerima" :error-messages="doErrors.recipient_name" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="doForm.address" label="Alamat Kirim" rows="2" :error-messages="doErrors.address" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="doForm.courier" label="Kurir/Ekspedisi (opsional)" :error-messages="doErrors.courier" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="doForm.tracking_number" label="No. Resi (opsional)" :error-messages="doErrors.tracking_number" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="doForm.notes" label="Catatan" rows="2" />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="text" @click="doDialog = false">
              Batal
            </VBtn>
            <VBtn type="submit" :loading="doSaving">
              Simpan
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
