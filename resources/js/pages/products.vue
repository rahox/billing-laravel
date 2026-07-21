<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency } from '@/utils/currency'

const authStore = useAuthStore()
const canManage = computed(() => authStore.hasRole('super-admin'))

const items = ref([])
const loading = ref(false)

const headers = [
  { title: 'Kode', key: 'code' },
  { title: 'Nama', key: 'name' },
  { title: 'Tipe', key: 'type' },
  { title: 'Harga', key: 'price' },
  { title: 'HPP', key: 'cost_price' },
  { title: 'Recurring', key: 'is_recurring' },
  { title: 'PPN', key: 'is_ppn_applicable' },
  { title: 'BHP/USO', key: 'is_telco_levy_applicable' },
  { title: 'Aksi', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/products', { params: { per_page: 50 } })

    items.value = data.data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadItems)

const dialog = ref(false)
const saving = ref(false)
const editingId = ref(null)
const errors = ref({})
const defaultForm = () => ({
  name: '', type: 'jasa', price: 0, cost_price: 0, is_recurring: false,
  recurring_period: 'monthly', is_ppn_applicable: true, is_telco_levy_applicable: false,
  is_active: true, description: '',
})
const form = ref(defaultForm())

function openCreate() {
  editingId.value = null
  errors.value = {}
  form.value = defaultForm()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  errors.value = {}
  form.value = { ...item }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (editingId.value)
      await client.patch(`/products/${editingId.value}`, form.value)
    else
      await client.post('/products', form.value)

    dialog.value = false
    loadItems()
  }
  catch (error) {
    errors.value = error.response?.data?.errors ?? {}
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VCard title="Produk Jasa & Barang">
    <template v-if="canManage" #append>
      <VBtn prepend-icon="ri-add-line" @click="openCreate">
        Tambah Produk
      </VBtn>
    </template>

    <VDataTable :items="items" :headers="headers" :loading="loading" :items-per-page="50">
      <template #item.price="{ item }">
        {{ formatCurrency(item.price) }}
      </template>
      <template #item.cost_price="{ item }">
        {{ formatCurrency(item.cost_price) }}
      </template>
      <template #item.type="{ item }">
        <VChip size="small" :color="item.type === 'jasa' ? 'primary' : 'secondary'">
          {{ item.type }}
        </VChip>
      </template>
      <template #item.is_recurring="{ item }">
        <VIcon v-if="item.is_recurring" icon="ri-check-line" color="success" />
        <span v-else>-</span>
        <span v-if="item.is_recurring" class="ms-1 text-caption">({{ item.recurring_period }})</span>
      </template>
      <template #item.is_ppn_applicable="{ item }">
        <VIcon :icon="item.is_ppn_applicable ? 'ri-check-line' : 'ri-close-line'" :color="item.is_ppn_applicable ? 'success' : 'error'" />
      </template>
      <template #item.is_telco_levy_applicable="{ item }">
        <VIcon :icon="item.is_telco_levy_applicable ? 'ri-check-line' : 'ri-close-line'" :color="item.is_telco_levy_applicable ? 'success' : 'error'" />
      </template>
      <template #item.actions="{ item }">
        <VBtn v-if="canManage" icon="ri-edit-line" variant="text" size="small" @click="openEdit(item)" />
      </template>
    </VDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="600">
    <VCard :title="editingId ? 'Edit Produk' : 'Tambah Produk'">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="form.name" label="Nama Produk" :error-messages="errors.name" />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.type" :items="[{ title: 'Jasa', value: 'jasa' }, { title: 'Barang', value: 'barang' }]" label="Tipe" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model.number="form.price" type="number" label="Harga Jual" :error-messages="errors.price" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model.number="form.cost_price" type="number" label="Harga Pokok (HPP)" />
            </VCol>
            <VCol cols="6" class="d-flex align-center">
              <VSwitch v-model="form.is_recurring" label="Recurring (langganan)" :disabled="form.type !== 'jasa'" />
            </VCol>
            <VCol v-if="form.is_recurring" cols="6">
              <VSelect
                v-model="form.recurring_period"
                :items="[{ title: 'Bulanan', value: 'monthly' }, { title: 'Kuartalan', value: 'quarterly' }, { title: 'Tahunan', value: 'yearly' }]"
                label="Periode Recurring"
              />
            </VCol>
            <VCol cols="6">
              <VSwitch v-model="form.is_ppn_applicable" label="Kena PPN" />
            </VCol>
            <VCol cols="6">
              <VSwitch v-model="form.is_telco_levy_applicable" label="Kena BHP & USO (jasa internet)" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.description" label="Deskripsi" rows="2" />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="text" @click="dialog = false">
              Batal
            </VBtn>
            <VBtn type="submit" :loading="saving">
              Simpan
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
