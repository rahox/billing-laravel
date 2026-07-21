<script setup>
import client from '@/api/client'

const items = ref([])
const loading = ref(false)
const roleFilter = ref('reseller')
const resellers = ref([])

const roleOptions = [
  { title: 'Reseller', value: 'reseller' },
  { title: 'Sales', value: 'sales' },
  { title: 'Collector', value: 'collector' },
]

const headers = [
  { title: 'Nama', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'HP', key: 'phone' },
  { title: 'Komisi', key: 'commission' },
  { title: 'Aktif', key: 'is_active' },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/users', { params: { role: roleFilter.value, per_page: 50 } })

    items.value = data.data
  }
  finally {
    loading.value = false
  }
}

async function loadResellers() {
  const { data } = await client.get('/users', { params: { role: 'reseller', per_page: 50 } })

  resellers.value = data.data
}

watch(roleFilter, loadItems)
onMounted(() => {
  loadItems()
  loadResellers()
})

const dialog = ref(false)
const saving = ref(false)
const errors = ref({})
const defaultForm = () => ({
  name: '', email: '', password: '', phone: '', role: roleFilter.value,
  parent_reseller_id: null, commission_type: null, commission_value: null,
})
const form = ref(defaultForm())

function openCreate() {
  errors.value = {}
  form.value = defaultForm()
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    await client.post('/users', form.value)
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
  <VCard title="Manajemen Pengguna (Reseller / Sales / Collector)">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <VSelect v-model="roleFilter" :items="roleOptions" label="Role" />
        </VCol>
        <VCol cols="12" md="8" class="d-flex justify-end align-center">
          <VBtn prepend-icon="ri-add-line" @click="openCreate">
            Tambah User
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable :items="items" :headers="headers" :loading="loading" :items-per-page="50">
      <template #item.commission="{ item }">
        <span v-if="item.commission_type === 'flat'">Rp {{ Number(item.commission_value).toLocaleString('id-ID') }} / pembayaran</span>
        <span v-else-if="item.commission_type === 'percentage'">{{ item.commission_value }}% / pembayaran</span>
        <span v-else>-</span>
      </template>
      <template #item.is_active="{ item }">
        <VIcon :icon="item.is_active ? 'ri-check-line' : 'ri-close-line'" :color="item.is_active ? 'success' : 'error'" />
      </template>
    </VDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="500">
    <VCard title="Tambah User">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VSelect v-model="form.role" :items="roleOptions" label="Role" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.name" label="Nama" :error-messages="errors.name" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.email" label="Email" :error-messages="errors.email" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.password" type="password" label="Password" :error-messages="errors.password" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.phone" label="No. HP" />
            </VCol>
            <VCol v-if="form.role === 'sales'" cols="12">
              <VSelect v-model="form.parent_reseller_id" :items="resellers" item-title="name" item-value="id" label="Reseller Induk" />
            </VCol>
            <template v-if="form.role === 'sales'">
              <VCol cols="6">
                <VSelect
                  v-model="form.commission_type"
                  :items="[{ title: 'Flat per Pembayaran', value: 'flat' }, { title: 'Persentase per Pembayaran', value: 'percentage' }]"
                  label="Tipe Komisi"
                />
              </VCol>
              <VCol cols="6">
                <VTextField v-model.number="form.commission_value" type="number" :label="form.commission_type === 'percentage' ? 'Nilai (%)' : 'Nilai (Rp)'" />
              </VCol>
            </template>
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
