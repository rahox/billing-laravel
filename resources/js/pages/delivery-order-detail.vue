<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDate } from '@/utils/currency'

const route = useRoute()
const authStore = useAuthStore()
const canManage = computed(() => authStore.hasRole('super-admin') || authStore.hasRole('reseller'))

const deliveryOrder = ref(null)
const loading = ref(false)

async function loadDeliveryOrder() {
  loading.value = true
  try {
    const { data } = await client.get(`/delivery-orders/${route.params.id}`)

    deliveryOrder.value = data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadDeliveryOrder)

const statusColor = status => ({ pending: 'warning', shipped: 'info', delivered: 'success', cancelled: 'error' }[status] ?? 'default')

const shipDialog = ref(false)
const shipping = ref(false)
const shipForm = ref({ courier: '', tracking_number: '' })

function openShipDialog() {
  shipForm.value = { courier: deliveryOrder.value.courier ?? '', tracking_number: deliveryOrder.value.tracking_number ?? '' }
  shipDialog.value = true
}

async function ship() {
  shipping.value = true
  try {
    await client.post(`/delivery-orders/${deliveryOrder.value.id}/ship`, shipForm.value)
    shipDialog.value = false
    loadDeliveryOrder()
  }
  finally {
    shipping.value = false
  }
}

const delivering = ref(false)
async function deliver() {
  delivering.value = true
  try {
    await client.post(`/delivery-orders/${deliveryOrder.value.id}/deliver`, { recipient_name: deliveryOrder.value.recipient_name })
    loadDeliveryOrder()
  }
  finally {
    delivering.value = false
  }
}

const cancelling = ref(false)
async function cancel() {
  cancelling.value = true
  try {
    await client.post(`/delivery-orders/${deliveryOrder.value.id}/cancel`)
    loadDeliveryOrder()
  }
  finally {
    cancelling.value = false
  }
}
</script>

<template>
  <div v-if="deliveryOrder">
    <VCard :title="`Delivery Order ${deliveryOrder.do_number}`" class="mb-6">
      <template #append>
        <VChip :color="statusColor(deliveryOrder.status)">
          {{ deliveryOrder.status }}
        </VChip>
      </template>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Pelanggan
            </div>
            <div class="text-h6">
              {{ deliveryOrder.customer?.name }}
            </div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Tgl Kirim
            </div>
            <div>{{ formatDate(deliveryOrder.delivery_date) }}</div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Penerima
            </div>
            <div>{{ deliveryOrder.recipient_name ?? '-' }}</div>
          </VCol>
          <VCol cols="12" md="3">
            <div class="text-caption text-medium-emphasis">
              Kurir / No. Resi
            </div>
            <div>{{ deliveryOrder.courier ?? '-' }} {{ deliveryOrder.tracking_number ? `(${deliveryOrder.tracking_number})` : '' }}</div>
          </VCol>
          <VCol cols="12">
            <div class="text-caption text-medium-emphasis">
              Alamat Kirim
            </div>
            <div>{{ deliveryOrder.address ?? '-' }}</div>
          </VCol>
          <VCol v-if="deliveryOrder.notes" cols="12">
            <div class="text-caption text-medium-emphasis">
              Catatan
            </div>
            <div>{{ deliveryOrder.notes }}</div>
          </VCol>
        </VRow>

        <div v-if="canManage" class="d-flex gap-2 mt-4">
          <VBtn v-if="deliveryOrder.status === 'pending'" color="primary" prepend-icon="ri-truck-line" @click="openShipDialog">
            Tandai Dikirim
          </VBtn>
          <VBtn v-if="deliveryOrder.status === 'shipped'" color="success" prepend-icon="ri-checkbox-circle-line" :loading="delivering" @click="deliver">
            Tandai Diterima
          </VBtn>
          <VBtn v-if="deliveryOrder.status === 'pending'" color="error" variant="outlined" :loading="cancelling" @click="cancel">
            Batalkan
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VCard title="Rincian Barang">
      <VTable>
        <thead>
          <tr>
            <th>No. Transaksi</th>
            <th>Produk</th>
            <th>Qty</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="trx in deliveryOrder.transactions" :key="trx.id">
            <td>{{ trx.transaction_number }}</td>
            <td>{{ trx.product?.name }}</td>
            <td>{{ trx.qty }}</td>
            <td>{{ formatCurrency(trx.total) }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>

  <VProgressLinear v-else-if="loading" indeterminate color="primary" />

  <VDialog v-model="shipDialog" max-width="500">
    <VCard title="Tandai Dikirim">
      <VCardText>
        <VForm @submit.prevent="ship">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="shipForm.courier" label="Kurir/Ekspedisi" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="shipForm.tracking_number" label="No. Resi" />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="text" @click="shipDialog = false">
              Batal
            </VBtn>
            <VBtn type="submit" :loading="shipping">
              Simpan
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
