<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDate } from '@/utils/currency'

const route = useRoute()
const authStore = useAuthStore()
const canRecordPayment = computed(() => authStore.hasRole('super-admin') || authStore.hasRole('collector'))

const invoice = ref(null)
const loading = ref(false)

async function loadInvoice() {
  loading.value = true
  try {
    const { data } = await client.get(`/invoices/${route.params.id}`)

    invoice.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadInvoice)

const statusColor = status => ({ lunas: 'success', cicilan: 'warning', belum_lunas: 'info', overdue: 'error', pending: 'warning', confirmed: 'success' }[status] ?? 'default')

const sisaTagihan = computed(() => (invoice.value ? Number(invoice.value.grand_total) - Number(invoice.value.paid_amount) : 0))

const dialog = ref(false)
const saving = ref(false)
const errors = ref({})
const form = ref({ amount: 0, payment_date: new Date().toISOString().slice(0, 10), method: 'transfer', status: 'confirmed', notes: '' })

function openPaymentDialog() {
  errors.value = {}
  form.value = { amount: sisaTagihan.value, payment_date: new Date().toISOString().slice(0, 10), method: 'transfer', status: 'confirmed', notes: '' }
  dialog.value = true
}

async function savePayment() {
  saving.value = true
  errors.value = {}
  try {
    await client.post('/payments', { ...form.value, invoice_id: invoice.value.id })
    dialog.value = false
    loadInvoice()
  }
  catch (error) {
    errors.value = error.response?.data?.errors ?? {}
  }
  finally {
    saving.value = false
  }
}

async function confirmPayment(payment) {
  await client.post(`/payments/${payment.id}/confirm`)
  loadInvoice()
}
</script>

<template>
  <div v-if="invoice">
    <VCard :title="`Invoice ${invoice.invoice_number}`" class="mb-6">
      <template #append>
        <VChip :color="statusColor(invoice.status)">
          {{ invoice.status }}
        </VChip>
      </template>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Pelanggan
            </div>
            <div class="text-h6">
              {{ invoice.customer?.name }}
            </div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Tgl Invoice
            </div>
            <div>{{ formatDate(invoice.invoice_date) }}</div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Jatuh Tempo
            </div>
            <div>{{ formatDate(invoice.due_date) }}</div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Sisa Tagihan
            </div>
            <div class="text-h6" :class="sisaTagihan > 0 ? 'text-error' : 'text-success'">
              {{ formatCurrency(sisaTagihan) }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VRow>
      <VCol cols="12" md="7">
        <VCard title="Rincian Transaksi" class="mb-6">
          <VTable>
            <thead>
              <tr>
                <th>Produk</th>
                <th>Qty</th>
                <th>Subtotal</th>
                <th>PPN</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="trx in invoice.transactions" :key="trx.id">
                <td>{{ trx.product?.name }}</td>
                <td>{{ trx.qty }}</td>
                <td>{{ formatCurrency(trx.subtotal) }}</td>
                <td>{{ formatCurrency(trx.ppn_amount) }}</td>
                <td>{{ formatCurrency(trx.total) }}</td>
              </tr>
            </tbody>
          </VTable>
          <VCardText class="text-end">
            <div>Subtotal: {{ formatCurrency(invoice.subtotal) }}</div>
            <div>Diskon: -{{ formatCurrency(invoice.discount_total) }}</div>
            <div>PPN (11%): {{ formatCurrency(invoice.ppn_total) }}</div>
            <div class="text-h6">
              Total: {{ formatCurrency(invoice.grand_total) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="5">
        <VCard title="Riwayat Pembayaran">
          <template v-if="canRecordPayment" #append>
            <VBtn size="small" prepend-icon="ri-add-line" @click="openPaymentDialog">
              Catat Pembayaran
            </VBtn>
          </template>
          <VList>
            <VListItem v-for="payment in invoice.payments" :key="payment.id">
              <VListItemTitle>{{ formatCurrency(payment.amount) }} - {{ payment.method }}</VListItemTitle>
              <VListItemSubtitle>{{ formatDate(payment.payment_date) }}</VListItemSubtitle>
              <template #append>
                <VChip size="small" :color="statusColor(payment.status)" class="me-2">
                  {{ payment.status }}
                </VChip>
                <VBtn
                  v-if="payment.status === 'pending' && canRecordPayment"
                  size="small"
                  color="success"
                  @click="confirmPayment(payment)"
                >
                  Konfirmasi
                </VBtn>
              </template>
            </VListItem>
            <VListItem v-if="!invoice.payments?.length">
              <VListItemTitle class="text-medium-emphasis">
                Belum ada pembayaran.
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>
  </div>

  <VProgressLinear v-else-if="loading" indeterminate color="primary" />
</template>
