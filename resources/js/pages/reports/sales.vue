<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const from = ref('')
const to = ref('')
const commissions = ref([])
const summary = ref({})
const loading = ref(false)
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = ref(20)

async function loadReport() {
  loading.value = true
  try {
    const { data } = await client.get('/reports/sales', {
      params: { from: from.value || undefined, to: to.value || undefined, page: page.value, per_page: itemsPerPage.value },
    })

    commissions.value = data.commissions.data
    totalItems.value = data.commissions.total
    summary.value = data.summary
  }
  finally {
    loading.value = false
  }
}

function applyFilter() {
  page.value = 1
  loadReport()
}

onMounted(loadReport)

const headers = [
  { title: 'Tanggal', key: 'earned_date' },
  { title: 'Invoice', key: 'invoice.invoice_number' },
  { title: 'Pelanggan', key: 'invoice.customer.name' },
  { title: 'Dasar Nominal', key: 'base_amount' },
  { title: 'Tipe', key: 'commission_type' },
  { title: 'Komisi', key: 'commission_amount' },
  { title: 'Status', key: 'status' },
]
</script>

<template>
  <VCard title="Laporan Komisi Sales">
    <VCardText>
      <VRow>
        <VCol cols="12" md="3">
          <VTextField v-model="from" type="date" label="Dari Tanggal" />
        </VCol>
        <VCol cols="12" md="3">
          <VTextField v-model="to" type="date" label="Sampai Tanggal" />
        </VCol>
        <VCol cols="12" md="3" class="d-flex align-end">
          <VBtn block @click="applyFilter">
            Terapkan
          </VBtn>
        </VCol>
      </VRow>

      <VRow class="mt-2">
        <VCol cols="6" md="3">
          <div class="text-caption">
            Total Komisi
          </div>
          <div class="text-h6">
            {{ formatCurrency(summary.total_komisi) }}
          </div>
        </VCol>
        <VCol cols="6" md="3">
          <div class="text-caption">
            Komisi Pending
          </div>
          <div class="text-h6 text-warning">
            {{ formatCurrency(summary.komisi_pending) }}
          </div>
        </VCol>
        <VCol cols="6" md="3">
          <div class="text-caption">
            Komisi Terbayar
          </div>
          <div class="text-h6 text-success">
            {{ formatCurrency(summary.komisi_paid) }}
          </div>
        </VCol>
        <VCol cols="6" md="3">
          <div class="text-caption">
            Jumlah Transaksi
          </div>
          <div class="text-h6">
            {{ summary.jumlah_transaksi_komisi }}
          </div>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:page="page"
      :items="commissions"
      :items-length="totalItems"
      :items-per-page="itemsPerPage"
      :headers="headers"
      :loading="loading"
      @update:options="loadReport"
    >
      <template #item.earned_date="{ item }">
        {{ formatDate(item.earned_date) }}
      </template>
      <template #item.base_amount="{ item }">
        {{ formatCurrency(item.base_amount) }}
      </template>
      <template #item.commission_type="{ item }">
        {{ item.commission_type === 'flat' ? 'Flat' : 'Persentase' }}
      </template>
      <template #item.commission_amount="{ item }">
        {{ formatCurrency(item.commission_amount) }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="item.status === 'paid' ? 'success' : 'warning'">
          {{ item.status }}
        </VChip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
