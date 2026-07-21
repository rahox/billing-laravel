<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const canManage = computed(() => authStore.hasRole('super-admin'))

const items = ref([])
const loading = ref(false)

const headers = [
  { title: 'Nama', key: 'name' },
  { title: 'Tipe', key: 'type' },
  { title: 'Mode', key: 'mode' },
  { title: 'Nilai', key: 'value' },
  { title: 'Aktif', key: 'is_active' },
  { title: 'Aksi', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/discounts', { params: { per_page: 50 } })

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
const defaultForm = () => ({ name: '', type: 'percentage', mode: 'manual', value: 0, is_active: true, description: '' })
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
      await client.patch(`/discounts/${editingId.value}`, form.value)
    else
      await client.post('/discounts', form.value)

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

const modeLabel = mode => (mode === 'prorate_activation' ? 'Prorate Aktivasi Awal' : 'Manual')
</script>

<template>
  <VCard title="Diskon">
    <template v-if="canManage" #append>
      <VBtn prepend-icon="ri-add-line" @click="openCreate">
        Tambah Diskon
      </VBtn>
    </template>

    <VDataTable :items="items" :headers="headers" :loading="loading" :items-per-page="50">
      <template #item.type="{ item }">
        <VChip size="small">
          {{ item.type === 'flat' ? 'Nominal Tetap' : 'Persentase' }}
        </VChip>
      </template>
      <template #item.mode="{ item }">
        {{ modeLabel(item.mode) }}
      </template>
      <template #item.value="{ item }">
        <span v-if="item.mode === 'prorate_activation'">Otomatis (sisa hari)</span>
        <span v-else-if="item.type === 'percentage'">{{ item.value }}%</span>
        <span v-else>Rp {{ Number(item.value).toLocaleString('id-ID') }}</span>
      </template>
      <template #item.is_active="{ item }">
        <VIcon :icon="item.is_active ? 'ri-check-line' : 'ri-close-line'" :color="item.is_active ? 'success' : 'error'" />
      </template>
      <template #item.actions="{ item }">
        <VBtn v-if="canManage" icon="ri-edit-line" variant="text" size="small" @click="openEdit(item)" />
      </template>
    </VDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="500">
    <VCard :title="editingId ? 'Edit Diskon' : 'Tambah Diskon'">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="form.name" label="Nama Diskon" :error-messages="errors.name" />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.mode" :items="[{ title: 'Manual', value: 'manual' }, { title: 'Prorate Aktivasi Awal', value: 'prorate_activation' }]" label="Mode" />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.type" :items="[{ title: 'Nominal Tetap (Flat)', value: 'flat' }, { title: 'Persentase', value: 'percentage' }]" label="Tipe" />
            </VCol>
            <VCol v-if="form.mode === 'manual'" cols="12">
              <VTextField v-model.number="form.value" type="number" :label="form.type === 'percentage' ? 'Nilai (%)' : 'Nilai (Rp)'" />
            </VCol>
            <VCol cols="12">
              <VSwitch v-model="form.is_active" label="Aktif" />
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
