<script setup>
import client from '@/api/client'
import { formatCurrency } from '@/utils/currency'

const from = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const to = ref(new Date().toISOString().slice(0, 10))
const report = ref(null)
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const { data } = await client.get('/reports/income-statement', { params: { from: from.value, to: to.value } })

    report.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadReport)
</script>

<template>
  <VCard title="Laporan Laba Rugi">
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
    </VCardText>

    <VCardText v-if="report">
      <h6 class="text-h6 mb-2">
        Pendapatan
      </h6>
      <VTable density="compact" class="mb-4">
        <tbody>
          <tr v-for="row in report.revenue" :key="row.code">
            <td>{{ row.code }} - {{ row.name }}</td>
            <td class="text-end">
              {{ formatCurrency(row.balance) }}
            </td>
          </tr>
          <tr class="font-weight-bold">
            <td>Total Pendapatan</td>
            <td class="text-end">
              {{ formatCurrency(report.total_revenue) }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <h6 class="text-h6 mb-2">
        Beban Pokok Penjualan (HPP)
      </h6>
      <VTable density="compact" class="mb-4">
        <tbody>
          <tr v-for="row in report.cogs" :key="row.code">
            <td>{{ row.code }} - {{ row.name }}</td>
            <td class="text-end">
              {{ formatCurrency(row.balance) }}
            </td>
          </tr>
          <tr class="font-weight-bold">
            <td>Total HPP</td>
            <td class="text-end">
              {{ formatCurrency(report.total_cogs) }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <VDivider class="mb-4" />
      <div class="d-flex justify-space-between text-h6 mb-4">
        <span>Laba Kotor</span>
        <span>{{ formatCurrency(report.gross_profit) }}</span>
      </div>

      <h6 class="text-h6 mb-2">
        Beban Operasional
      </h6>
      <VTable density="compact" class="mb-4">
        <tbody>
          <tr v-for="row in report.opex" :key="row.code">
            <td>{{ row.code }} - {{ row.name }}</td>
            <td class="text-end">
              {{ formatCurrency(row.balance) }}
            </td>
          </tr>
          <tr class="font-weight-bold">
            <td>Total Beban Operasional</td>
            <td class="text-end">
              {{ formatCurrency(report.total_opex) }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <VDivider class="mb-4" />
      <div class="d-flex justify-space-between text-h5 font-weight-bold" :class="report.net_income >= 0 ? 'text-success' : 'text-error'">
        <span>Laba/Rugi Bersih</span>
        <span>{{ formatCurrency(report.net_income) }}</span>
      </div>
    </VCardText>
  </VCard>
</template>
