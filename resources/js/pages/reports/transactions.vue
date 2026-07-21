<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const from = ref('')
const to = ref('')
const invoices = ref([])
const summary = ref({})
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const { data } = await client.get('/reports/transactions', { params: { from: from.value || undefined, to: to.value || undefined } })

    invoices.value = data.invoices
    summary.value = data.summary
  }
  finally {
    loading.value = false
  }
}

onMounted(loadReport)

const headers = [
  { title: 'No. Invoice', key: 'invoice_number' },
  { title: 'Pelanggan', key: 'customer.name' },
  { title: 'Sales', key: 'sales.name' },
  { title: 'Collector', key: 'collector.name' },
  { title: 'Tanggal', key: 'invoice_date' },
  { title: 'Total', key: 'grand_total' },
  { title: 'Terbayar', key: 'paid_amount' },
  { title: 'Status', key: 'status' },
]

const statusColor = status => ({ lunas: 'success', cicilan: 'warning', belum_lunas: 'info', overdue: 'error' }[status] ?? 'default')
</script>

<template>
  <VCard title="Laporan Transaksi">
    <VCardText>
      <VRow>
        <VCol cols="12" md="3">
          <VTextField v-model="from" type="date" label="Dari Tanggal" />
        </VCol>
        <VCol cols="12" md="3">
          <VTextField v-model="to" type="date" label="Sampai Tanggal" />
        </VCol>
        <VCol cols="12" md="3" class="d-flex align-end">
          <VBtn block @click="loadReport">
            Terapkan
          </VBtn>
        </VCol>
      </VRow>

      <VRow class="mt-2">
        <VCol cols="6" md="2">
          <div class="text-caption">
            Jumlah Invoice
          </div>
          <div class="text-h6">
            {{ summary.jumlah_invoice }}
          </div>
        </VCol>
        <VCol cols="6" md="2">
          <div class="text-caption">
            Total Tagihan
          </div>
          <div class="text-h6">
            {{ formatCurrency(summary.total_tagihan) }}
          </div>
        </VCol>
        <VCol cols="6" md="2">
          <div class="text-caption">
            Total Terbayar
          </div>
          <div class="text-h6 text-success">
            {{ formatCurrency(summary.total_terbayar) }}
          </div>
        </VCol>
        <VCol cols="6" md="2">
          <div class="text-caption">
            Sisa Piutang
          </div>
          <div class="text-h6 text-warning">
            {{ formatCurrency(summary.total_piutang) }}
          </div>
        </VCol>
        <VCol cols="6" md="2">
          <div class="text-caption">
            Lunas / Cicilan / Belum
          </div>
          <div class="text-h6">
            {{ summary.lunas }} / {{ summary.cicilan }} / {{ summary.belum_lunas }}
          </div>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable :items="invoices" :headers="headers" :loading="loading" :items-per-page="20">
      <template #item.invoice_date="{ item }">
        {{ formatDate(item.invoice_date) }}
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
    </VDataTable>
  </VCard>
</template>
