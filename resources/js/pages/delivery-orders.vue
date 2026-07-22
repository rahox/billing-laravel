<script setup>
import client from '@/api/client'
import { formatDate } from '@/utils/currency'

const router = useRouter()
const items = ref([])
const loading = ref(false)
const statusFilter = ref(null)
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = ref(15)

const statusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'Dikirim', value: 'shipped' },
  { title: 'Diterima', value: 'delivered' },
  { title: 'Dibatalkan', value: 'cancelled' },
]

const headers = [
  { title: 'No. Delivery Order', key: 'do_number' },
  { title: 'Pelanggan', key: 'customer.name' },
  { title: 'Tgl Kirim', key: 'delivery_date' },
  { title: 'Kurir', key: 'courier' },
  { title: 'No. Resi', key: 'tracking_number' },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/delivery-orders', {
      params: { status: statusFilter.value || undefined, page: page.value, per_page: itemsPerPage.value },
    })

    items.value = data.data
    totalItems.value = data.total
  }
  finally {
    loading.value = false
  }
}

watch(statusFilter, () => {
  page.value = 1
  loadItems()
})
watch(page, loadItems)
onMounted(loadItems)

const statusColor = status => ({ pending: 'warning', shipped: 'info', delivered: 'success', cancelled: 'error' }[status] ?? 'default')
</script>

<template>
  <VCard title="Delivery Order (Surat Jalan)">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <VSelect v-model="statusFilter" :items="statusOptions" label="Status" clearable />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:page="page"
      :items="items"
      :items-length="totalItems"
      :items-per-page="itemsPerPage"
      :headers="headers"
      :loading="loading"
      @update:options="loadItems"
      @click:row="(e, { item }) => router.push(`/delivery-orders/${item.id}`)"
    >
      <template #item.delivery_date="{ item }">
        {{ formatDate(item.delivery_date) }}
      </template>
      <template #item.courier="{ item }">
        {{ item.courier ?? '-' }}
      </template>
      <template #item.tracking_number="{ item }">
        {{ item.tracking_number ?? '-' }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="statusColor(item.status)">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="ri-eye-line" variant="text" size="small" :to="`/delivery-orders/${item.id}`" />
      </template>
    </VDataTableServer>
  </VCard>
</template>
