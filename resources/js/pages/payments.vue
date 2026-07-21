<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const items = ref([])
const loading = ref(false)
const statusFilter = ref('pending')

const headers = [
  { title: 'No. Pembayaran', key: 'payment_number' },
  { title: 'Invoice', key: 'invoice.invoice_number' },
  { title: 'Pelanggan', key: 'invoice.customer.name' },
  { title: 'Tanggal', key: 'payment_date' },
  { title: 'Metode', key: 'method' },
  { title: 'Nominal', key: 'amount' },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/payments', { params: { status: statusFilter.value || undefined, per_page: 50 } })

    items.value = data.data
  }
  finally {
    loading.value = false
  }
}

watch(statusFilter, loadItems)
onMounted(loadItems)

async function confirmPayment(payment) {
  await client.post(`/payments/${payment.id}/confirm`)
  loadItems()
}

const statusColor = status => ({ pending: 'warning', confirmed: 'success', rejected: 'error' }[status] ?? 'default')
</script>

<template>
  <VCard title="Pembayaran">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <VSelect
            v-model="statusFilter"
            :items="[{ title: 'Menunggu Konfirmasi', value: 'pending' }, { title: 'Terkonfirmasi', value: 'confirmed' }, { title: 'Semua', value: null }]"
            label="Status"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable :items="items" :headers="headers" :loading="loading" :items-per-page="50">
      <template #item.payment_date="{ item }">
        {{ formatDate(item.payment_date) }}
      </template>
      <template #item.amount="{ item }">
        {{ formatCurrency(item.amount) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="statusColor(item.status)">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn v-if="item.status === 'pending'" size="small" color="success" @click="confirmPayment(item)">
          Konfirmasi
        </VBtn>
        <VBtn icon="ri-eye-line" variant="text" size="small" :to="`/invoices/${item.invoice_id}`" />
      </template>
    </VDataTable>
  </VCard>
</template>
