<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const outstanding = ref([])
const history = ref([])
const summary = ref({})
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const { data } = await client.get('/reports/collector')

    outstanding.value = data.perlu_ditagih
    history.value = data.riwayat_pembayaran
    summary.value = data.summary
  }
  finally {
    loading.value = false
  }
}

onMounted(loadReport)

const outstandingHeaders = [
  { title: 'No. Invoice', key: 'invoice_number' },
  { title: 'Pelanggan', key: 'customer.name' },
  { title: 'Jatuh Tempo', key: 'due_date' },
  { title: 'Sisa Tagihan', key: 'sisa' },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', sortable: false },
]

const historyHeaders = [
  { title: 'Tanggal', key: 'payment_date' },
  { title: 'Invoice', key: 'invoice.invoice_number' },
  { title: 'Pelanggan', key: 'invoice.customer.name' },
  { title: 'Nominal', key: 'amount' },
  { title: 'Status', key: 'status' },
]
</script>

<template>
  <VRow>
    <VCol cols="12" md="3">
      <VCard>
        <VCardText>
          <div class="text-caption">
            Perlu Ditagih
          </div>
          <div class="text-h5">
            {{ summary.jumlah_perlu_ditagih }}
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="3">
      <VCard>
        <VCardText>
          <div class="text-caption">
            Total Perlu Ditagih
          </div>
          <div class="text-h5 text-warning">
            {{ formatCurrency(summary.total_perlu_ditagih) }}
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="3">
      <VCard>
        <VCardText>
          <div class="text-caption">
            Berhasil Ditagih
          </div>
          <div class="text-h5 text-success">
            {{ formatCurrency(summary.total_berhasil_ditagih) }}
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="3">
      <VCard>
        <VCardText>
          <div class="text-caption">
            Jumlah Konfirmasi
          </div>
          <div class="text-h5">
            {{ summary.jumlah_konfirmasi }}
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VCard title="Perlu Ditagih" class="mt-6">
    <VDataTable :items="outstanding" :headers="outstandingHeaders" :loading="loading" :items-per-page="20">
      <template #item.due_date="{ item }">
        {{ formatDate(item.due_date) }}
      </template>
      <template #item.sisa="{ item }">
        {{ formatCurrency(Number(item.grand_total) - Number(item.paid_amount)) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="item.status === 'overdue' ? 'error' : 'info'">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="ri-eye-line" variant="text" size="small" :to="`/invoices/${item.id}`" />
      </template>
    </VDataTable>
  </VCard>

  <VCard title="Riwayat Konfirmasi Pembayaran" class="mt-6">
    <VDataTable :items="history" :headers="historyHeaders" :loading="loading" :items-per-page="20">
      <template #item.payment_date="{ item }">
        {{ formatDate(item.payment_date) }}
      </template>
      <template #item.amount="{ item }">
        {{ formatCurrency(item.amount) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="item.status === 'confirmed' ? 'success' : 'warning'">
          {{ item.status }}
        </VChip>
      </template>
    </VDataTable>
  </VCard>
</template>
