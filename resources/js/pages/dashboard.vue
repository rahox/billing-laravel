<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatNumber } from '@/utils/currency'
import CardStatisticsVertical from '@core/components/cards/CardStatisticsVertical.vue'

const authStore = useAuthStore()
const summary = ref({})
const role = ref('')
const isLoading = ref(true)

async function loadDashboard() {
  isLoading.value = true
  try {
    const { data } = await client.get('/dashboard')

    summary.value = data.summary
    role.value = data.role
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadDashboard)

const ownerCards = computed(() => [
  { title: 'Total Omzet', stats: formatCurrency(summary.value.total_omzet), icon: 'ri-money-dollar-circle-line', color: 'primary' },
  { title: 'Total Terbayar', stats: formatCurrency(summary.value.total_terbayar), icon: 'ri-check-double-line', color: 'success' },
  { title: 'Total Piutang', stats: formatCurrency(summary.value.total_piutang), icon: 'ri-hourglass-line', color: 'warning' },
  { title: 'Pelanggan Aktif', stats: formatNumber(summary.value.jumlah_pelanggan_aktif), icon: 'ri-user-line', color: 'info' },
])

const resellerCards = computed(() => [
  { title: 'Jumlah Invoice', stats: formatNumber(summary.value.jumlah_invoice), icon: 'ri-file-list-3-line', color: 'primary' },
  { title: 'Total Tagihan', stats: formatCurrency(summary.value.total_tagihan), icon: 'ri-money-dollar-circle-line', color: 'info' },
  { title: 'Total Terbayar', stats: formatCurrency(summary.value.total_terbayar), icon: 'ri-check-double-line', color: 'success' },
  { title: 'Sisa Piutang', stats: formatCurrency(summary.value.total_piutang), icon: 'ri-hourglass-line', color: 'warning' },
])

const salesCards = computed(() => [
  { title: 'Total Komisi', stats: formatCurrency(summary.value.total_komisi), icon: 'ri-hand-coin-line', color: 'primary' },
  { title: 'Komisi Pending', stats: formatCurrency(summary.value.komisi_pending), icon: 'ri-time-line', color: 'warning' },
  { title: 'Komisi Terbayar', stats: formatCurrency(summary.value.komisi_paid), icon: 'ri-check-double-line', color: 'success' },
  { title: 'Jumlah Transaksi', stats: formatNumber(summary.value.jumlah_transaksi_komisi), icon: 'ri-exchange-line', color: 'info' },
])

const collectorCards = computed(() => [
  { title: 'Perlu Ditagih', stats: formatNumber(summary.value.jumlah_perlu_ditagih), icon: 'ri-alarm-warning-line', color: 'warning' },
  { title: 'Total Perlu Ditagih', stats: formatCurrency(summary.value.total_perlu_ditagih), icon: 'ri-money-dollar-circle-line', color: 'primary' },
  { title: 'Berhasil Ditagih', stats: formatCurrency(summary.value.total_berhasil_ditagih), icon: 'ri-check-double-line', color: 'success' },
  { title: 'Jumlah Konfirmasi', stats: formatNumber(summary.value.jumlah_konfirmasi), icon: 'ri-hand-heart-line', color: 'info' },
])

const cards = computed(() => {
  if (role.value === 'super-admin')
    return ownerCards.value
  if (role.value === 'reseller')
    return resellerCards.value
  if (role.value === 'sales')
    return salesCards.value
  if (role.value === 'collector')
    return collectorCards.value

  return []
})
</script>

<template>
  <div>
    <h4 class="text-h4 mb-1">
      Halo, {{ authStore.user?.name }} 👋
    </h4>
    <p class="text-medium-emphasis mb-6">
      Ringkasan billing sesuai peran Anda.
    </p>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
      color="primary"
      class="mb-4"
    />

    <VRow v-else class="match-height">
      <VCol
        v-for="card in cards"
        :key="card.title"
        cols="12"
        sm="6"
        md="3"
      >
        <CardStatisticsVertical v-bind="card" />
      </VCol>
    </VRow>
  </div>
</template>
