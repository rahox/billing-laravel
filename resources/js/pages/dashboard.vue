<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatNumber } from '@/utils/currency'
import CardStatisticsVertical from '@core/components/cards/CardStatisticsVertical.vue'

const authStore = useAuthStore()
const summary = ref({})
const financials = ref({
  total_pendapatan: 0,
  total_beban: 0,
  laba_bersih: 0,
  pendapatan_by_category: [],
  beban_by_category: [],
  monthly_trend: [],
})
const role = ref('')
const isLoading = ref(true)

async function loadDashboard() {
  isLoading.value = true
  try {
    const { data } = await client.get('/dashboard')

    summary.value = data.summary
    role.value = data.role
    if (data.financials)
      financials.value = data.financials
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

const financialCards = computed(() => {
  const f = financials.value

  return [
    { title: 'Total Pendapatan', stats: formatCurrency(f.total_pendapatan), icon: 'ri-line-chart-line', color: 'success' },
    { title: 'Total Beban', stats: formatCurrency(f.total_beban), icon: 'ri-shopping-bag-3-line', color: 'error' },
    { title: 'Laba Bersih', stats: formatCurrency(f.laba_bersih), icon: 'ri-scales-3-line', color: f.laba_bersih >= 0 ? 'primary' : 'error' },
  ]
})

const trendChartSeries = computed(() => [
  { name: 'Pendapatan', data: financials.value.monthly_trend.map(m => m.pendapatan) },
  { name: 'Beban', data: financials.value.monthly_trend.map(m => m.beban) },
])

const trendChartOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, parentHeightOffset: 0 },
  colors: ['#56CA00', '#FF4C51'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  legend: { position: 'top', horizontalAlign: 'end' },
  grid: { strokeDashArray: 6 },
  xaxis: { categories: financials.value.monthly_trend.map(m => m.month) },
  yaxis: { labels: { formatter: val => formatCurrency(val) } },
  tooltip: { y: { formatter: val => formatCurrency(val) } },
}))

const revenueDonutSeries = computed(() => financials.value.pendapatan_by_category.map(c => c.value))

const revenueDonutOptions = computed(() => ({
  labels: financials.value.pendapatan_by_category.map(c => c.name),
  colors: ['#8C57FF', '#16B1FF', '#FFB400', '#56CA00', '#FF4C51'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: val => `${Number(val).toFixed(0)}%` },
  tooltip: { y: { formatter: val => formatCurrency(val) } },
  plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Pendapatan', formatter: w => formatCurrency(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
}))
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

    <template v-else>
      <VRow class="match-height">
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

      <template v-if="role === 'super-admin'">
        <h5 class="text-h5 mt-6 mb-4">
          Laporan Keuangan
        </h5>

        <VRow class="match-height">
          <VCol
            v-for="card in financialCards"
            :key="card.title"
            cols="12"
            sm="6"
            md="4"
          >
            <CardStatisticsVertical v-bind="card" />
          </VCol>
        </VRow>

        <VRow class="match-height mt-1">
          <VCol cols="12" md="8">
            <VCard title="Tren Pendapatan vs Beban (6 Bulan Terakhir)">
              <VCardText>
                <apexchart
                  type="area"
                  height="340"
                  :options="trendChartOptions"
                  :series="trendChartSeries"
                />
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="4">
            <VCard title="Komposisi Pendapatan">
              <VCardText>
                <apexchart
                  v-if="revenueDonutSeries.length"
                  type="donut"
                  height="340"
                  :options="revenueDonutOptions"
                  :series="revenueDonutSeries"
                />
                <p v-else class="text-medium-emphasis text-center py-10">
                  Belum ada data pendapatan pada periode ini.
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </template>
    </template>
  </div>
</template>
