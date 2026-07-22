<script setup>
import { useAuthStore } from '@/stores/auth'
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'

const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('super-admin'))
const isReseller = computed(() => authStore.hasRole('reseller'))
const isSales = computed(() => authStore.hasRole('sales'))
const isCollector = computed(() => authStore.hasRole('collector'))
</script>

<template>
  <VerticalNavLink
    :item="{ title: 'Dashboard', icon: 'ri-home-smile-line', to: '/dashboard' }"
  />

  <template v-if="isSuperAdmin || isReseller || isSales">
    <VerticalNavSectionTitle :item="{ heading: 'Pelanggan & Produk' }" />
    <VerticalNavLink :item="{ title: 'Pelanggan', icon: 'ri-team-line', to: '/customers' }" />
    <VerticalNavLink
      v-if="isSuperAdmin"
      :item="{ title: 'Produk', icon: 'ri-box-3-line', to: '/products' }"
    />
    <VerticalNavLink
      v-if="isSuperAdmin"
      :item="{ title: 'Diskon', icon: 'ri-price-tag-3-line', to: '/discounts' }"
    />
  </template>

  <VerticalNavSectionTitle :item="{ heading: 'Billing' }" />
  <VerticalNavLink
    v-if="isSuperAdmin || isReseller"
    :item="{ title: 'Transaksi', icon: 'ri-shopping-cart-2-line', to: '/transactions' }"
  />
  <VerticalNavLink :item="{ title: 'Invoice', icon: 'ri-file-list-3-line', to: '/invoices' }" />
  <VerticalNavLink
    v-if="isSuperAdmin || isReseller"
    :item="{ title: 'Delivery Order', icon: 'ri-truck-line', to: '/delivery-orders' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin || isCollector"
    :item="{ title: 'Pembayaran', icon: 'ri-wallet-3-line', to: '/payments' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin"
    :item="{ title: 'Beban/Pembelian', icon: 'ri-shopping-bag-3-line', to: '/expenses' }"
  />

  <VerticalNavSectionTitle :item="{ heading: 'Laporan' }" />
  <VerticalNavLink
    v-if="isSuperAdmin || isReseller"
    :item="{ title: 'Laporan Transaksi', icon: 'ri-bar-chart-2-line', to: '/reports/transactions' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin || isSales"
    :item="{ title: 'Komisi Sales', icon: 'ri-hand-coin-line', to: '/reports/sales' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin || isCollector"
    :item="{ title: 'Penagihan', icon: 'ri-phone-line', to: '/reports/collector' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin"
    :item="{ title: 'Laba Rugi', icon: 'ri-line-chart-line', to: '/reports/income-statement' }"
  />
  <VerticalNavLink
    v-if="isSuperAdmin"
    :item="{ title: 'Neraca', icon: 'ri-scales-3-line', to: '/reports/balance-sheet' }"
  />

  <template v-if="isSuperAdmin">
    <VerticalNavSectionTitle :item="{ heading: 'Administrasi' }" />
    <VerticalNavLink :item="{ title: 'Pengguna (Reseller/Sales/Collector)', icon: 'ri-shield-user-line', to: '/users' }" />
  </template>

  <VerticalNavSectionTitle :item="{ heading: 'Akun' }" />
  <VerticalNavLink :item="{ title: 'Pengaturan Akun', icon: 'ri-user-settings-line', to: '/account-settings' }" />
</template>
