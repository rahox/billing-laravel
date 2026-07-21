<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const router = useRouter()
const items = ref([])
const loading = ref(false)
const statusFilter = ref(null)
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = ref(15)

const statusOptions = [
  { title: 'Belum Lunas', value: 'belum_lunas' },
  { title: 'Cicilan', value: 'cicilan' },
  { title: 'Lunas', value: 'lunas' },
  { title: 'Overdue', value: 'overdue' },
]

const headers = [
  { title: 'No. Invoice', key: 'invoice_number' },
  { title: 'Pelanggan', key: 'customer.name' },
  { title: 'Tgl Invoice', key: 'invoice_date' },
  { title: 'Jatuh Tempo', key: 'due_date' },
  { title: 'Total Tagihan', key: 'grand_total' },
  { title: 'Terbayar', key: 'paid_amount' },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/invoices', {
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

const statusColor = status => ({ lunas: 'success', cicilan: 'warning', belum_lunas: 'info', overdue: 'error' }[status] ?? 'default')
</script>

<template>
  <VCard title="Invoice">
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
      @click:row="(e, { item }) => router.push(`/invoices/${item.id}`)"
    >
      <template #item.invoice_date="{ item }">
        {{ formatDate(item.invoice_date) }}
      </template>
      <template #item.due_date="{ item }">
        {{ formatDate(item.due_date) }}
      </template>
      <template #item.grand_total="{ item }">
        {{ formatCurrency(item.grand_total) }}
      </template>
      <template #item.paid_amount="{ item }">
        {{ formatCurrency(item.paid_amount) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="statusColor(item.status)">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="ri-eye-line" variant="text" size="small" :to="`/invoices/${item.id}`" />
      </template>
    </VDataTableServer>
  </VCard>
</template>
