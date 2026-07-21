<script setup>
import client from '@/api/client'
import { formatCurrency } from '@/utils/currency'

const asOf = ref(new Date().toISOString().slice(0, 10))
const report = ref(null)
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const { data } = await client.get('/reports/balance-sheet', { params: { as_of: asOf.value } })

    report.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadReport)
</script>

<template>
  <VCard title="Neraca (Laporan Posisi Keuangan)">
    <VCardText>
      <VRow>
        <VCol cols="12" md="3">
          <VTextField v-model="asOf" type="date" label="Per Tanggal" />
        </VCol>
        <VCol cols="12" md="3" class="d-flex align-end">
          <VBtn block @click="loadReport">
            Terapkan
          </VBtn>
        </VCol>
        <VCol v-if="report" cols="12" md="6" class="d-flex align-end justify-end">
          <VChip :color="report.is_balanced ? 'success' : 'error'">
            {{ report.is_balanced ? 'Balance' : 'Tidak Balance' }}
          </VChip>
        </VCol>
      </VRow>
    </VCardText>

    <VCardText v-if="report">
      <VRow>
        <VCol cols="12" md="6">
          <h6 class="text-h6 mb-2">
            Aset
          </h6>
          <VTable density="compact">
            <tbody>
              <tr v-for="row in report.assets" :key="row.code">
                <td>{{ row.code }} - {{ row.name }}</td>
                <td class="text-end">
                  {{ formatCurrency(row.balance) }}
                </td>
              </tr>
              <tr class="font-weight-bold">
                <td>Total Aset</td>
                <td class="text-end">
                  {{ formatCurrency(report.total_assets) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCol>

        <VCol cols="12" md="6">
          <h6 class="text-h6 mb-2">
            Kewajiban
          </h6>
          <VTable density="compact" class="mb-4">
            <tbody>
              <tr v-for="row in report.liabilities" :key="row.code">
                <td>{{ row.code }} - {{ row.name }}</td>
                <td class="text-end">
                  {{ formatCurrency(row.balance) }}
                </td>
              </tr>
              <tr class="font-weight-bold">
                <td>Total Kewajiban</td>
                <td class="text-end">
                  {{ formatCurrency(report.total_liabilities) }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <h6 class="text-h6 mb-2">
            Ekuitas
          </h6>
          <VTable density="compact">
            <tbody>
              <tr v-for="row in report.equity" :key="row.code">
                <td>{{ row.code }} - {{ row.name }}</td>
                <td class="text-end">
                  {{ formatCurrency(row.balance) }}
                </td>
              </tr>
              <tr class="font-weight-bold">
                <td>Total Ekuitas</td>
                <td class="text-end">
                  {{ formatCurrency(report.total_equity) }}
                </td>
              </tr>
              <tr class="font-weight-bold">
                <td>Total Kewajiban + Ekuitas</td>
                <td class="text-end">
                  {{ formatCurrency(report.total_liabilities_and_equity) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
