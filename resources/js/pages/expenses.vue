<script setup>
import client from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/currency'

const items = ref([])
const loading = ref(false)

const categoryOptions = [
  { title: 'Bandwidth Upstream', value: 'bandwidth_upstream' },
  { title: 'Sewa Tower/Kolokasi', value: 'sewa_tower_kolokasi' },
  { title: 'Listrik', value: 'listrik' },
  { title: 'Gaji Karyawan', value: 'gaji_karyawan' },
  { title: 'Perangkat Jaringan', value: 'perangkat_jaringan' },
  { title: 'Sewa Kantor', value: 'sewa_kantor' },
  { title: 'Internet Kantor', value: 'internet_kantor' },
  { title: 'Transportasi Operasional', value: 'transportasi_operasional' },
  { title: 'Pemeliharaan Jaringan', value: 'pemeliharaan_jaringan' },
  { title: 'Marketing', value: 'marketing' },
  { title: 'Lainnya', value: 'lainnya' },
]

const headers = [
  { title: 'No.', key: 'expense_number' },
  { title: 'Tanggal', key: 'expense_date' },
  { title: 'Kategori', key: 'category' },
  { title: 'Vendor', key: 'vendor' },
  { title: 'Deskripsi', key: 'description' },
  { title: 'Nominal', key: 'amount' },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/expenses', { params: { per_page: 30 } })

    items.value = data.data
  }
  finally {
    loading.value = false
  }
}

onMounted(loadItems)

const dialog = ref(false)
const saving = ref(false)
const errors = ref({})
const form = ref({ category: 'bandwidth_upstream', vendor: '', description: '', amount: 0, expense_date: new Date().toISOString().slice(0, 10) })

function openCreate() {
  errors.value = {}
  form.value = { category: 'bandwidth_upstream', vendor: '', description: '', amount: 0, expense_date: new Date().toISOString().slice(0, 10) }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    await client.post('/expenses', form.value)
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

function categoryTitle(value) {
  return categoryOptions.find(o => o.value === value)?.title ?? value
}
</script>

<template>
  <VCard title="Beban & Pembelian (COGS/Operasional)">
    <template #append>
      <VBtn prepend-icon="ri-add-line" @click="openCreate">
        Tambah Beban
      </VBtn>
    </template>

    <VDataTable :items="items" :headers="headers" :loading="loading" :items-per-page="30">
      <template #item.expense_date="{ item }">
        {{ formatDate(item.expense_date) }}
      </template>
      <template #item.category="{ item }">
        {{ categoryTitle(item.category) }}
      </template>
      <template #item.amount="{ item }">
        {{ formatCurrency(item.amount) }}
      </template>
    </VDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="500">
    <VCard title="Tambah Beban/Pembelian">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VSelect v-model="form.category" :items="categoryOptions" label="Kategori" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.vendor" label="Vendor (opsional)" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.description" label="Deskripsi" :error-messages="errors.description" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model.number="form.amount" type="number" label="Nominal" :error-messages="errors.amount" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.expense_date" type="date" label="Tanggal" />
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
