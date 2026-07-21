<script setup>
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('super-admin'))

const items = ref([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref(null)
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = ref(15)

const resellers = ref([])
const salesList = ref([])
const collectors = ref([])

const statusOptions = [
  { title: 'Prospek', value: 'prospect' },
  { title: 'Aktif', value: 'active' },
  { title: 'Suspend', value: 'suspended' },
  { title: 'Berhenti', value: 'terminated' },
]

const headers = [
  { title: 'No. Pelanggan', key: 'customer_number' },
  { title: 'Nama', key: 'name' },
  { title: 'HP', key: 'phone1' },
  { title: 'Reseller', key: 'reseller.name' },
  { title: 'Sales', key: 'sales.name' },
  { title: 'Collector', key: 'collector.name' },
  { title: 'Status', key: 'status' },
  { title: 'Aksi', key: 'actions', sortable: false },
]

async function loadItems() {
  loading.value = true
  try {
    const { data } = await client.get('/customers', {
      params: { search: search.value || undefined, status: statusFilter.value || undefined, page: page.value, per_page: itemsPerPage.value },
    })

    items.value = data.data
    totalItems.value = data.total
  }
  finally {
    loading.value = false
  }
}

async function loadDropdowns() {
  const [resellerRes, collectorRes] = await Promise.all([
    client.get('/users', { params: { role: 'reseller' } }),
    client.get('/users', { params: { role: 'collector' } }),
  ])

  resellers.value = resellerRes.data.data
  collectors.value = collectorRes.data.data

  if (isSuperAdmin.value && resellers.value.length)
    await loadSalesForReseller(resellers.value[0].id)
}

async function loadSalesForReseller(resellerId) {
  if (!resellerId) {
    salesList.value = []

    return
  }
  const { data } = await client.get('/users', { params: { role: 'sales', reseller_id: resellerId } })

  salesList.value = data.data
}

watch([search, statusFilter], () => {
  page.value = 1
  loadItems()
})
watch(page, loadItems)

onMounted(() => {
  loadItems()
  loadDropdowns()
})

const dialog = ref(false)
const saving = ref(false)
const editingId = ref(null)
const errors = ref({})
const form = ref({
  name: '',
  address: '',
  phone1: '',
  phone2: '',
  reseller_id: null,
  sales_id: null,
  collector_id: null,
  status: 'active',
})

function openCreate() {
  editingId.value = null
  errors.value = {}
  form.value = { name: '', address: '', phone1: '', phone2: '', reseller_id: null, sales_id: null, collector_id: null, status: 'active' }
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  errors.value = {}
  form.value = {
    name: item.name,
    address: item.address,
    phone1: item.phone1,
    phone2: item.phone2,
    reseller_id: item.reseller_id,
    sales_id: item.sales_id,
    collector_id: item.collector_id,
    status: item.status,
  }
  if (item.reseller_id)
    loadSalesForReseller(item.reseller_id)
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (editingId.value)
      await client.patch(`/customers/${editingId.value}`, form.value)
    else
      await client.post('/customers', form.value)

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

const statusColor = status => ({ active: 'success', prospect: 'info', suspended: 'warning', terminated: 'error' }[status] ?? 'default')
</script>

<template>
  <VCard title="Data Pelanggan">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <VTextField v-model="search" label="Cari nama/no. pelanggan/HP" prepend-inner-icon="ri-search-line" clearable />
        </VCol>
        <VCol cols="12" md="4">
          <VSelect v-model="statusFilter" :items="statusOptions" label="Status" clearable />
        </VCol>
        <VCol cols="12" md="4" class="d-flex justify-end align-center">
          <VBtn prepend-icon="ri-add-line" @click="openCreate">
            Tambah Pelanggan
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:page="page"
      :items="items"
      :items-length="totalItems"
      :items-per-page="itemsPerPage"
      :headers="headers"
      :loading="loading"
      @update:options="loadItems"
    >
      <template #item.status="{ item }">
        <VChip :color="statusColor(item.status)" size="small">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="ri-edit-line" variant="text" size="small" @click="openEdit(item)" />
      </template>
    </VDataTableServer>
  </VCard>

  <VDialog v-model="dialog" max-width="600">
    <VCard :title="editingId ? 'Edit Pelanggan' : 'Tambah Pelanggan'">
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="form.name" label="Nama" :error-messages="errors.name" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.address" label="Alamat" rows="2" :error-messages="errors.address" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.phone1" label="No. HP 1" :error-messages="errors.phone1" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.phone2" label="No. HP 2 (opsional)" :error-messages="errors.phone2" />
            </VCol>
            <VCol v-if="isSuperAdmin" cols="6">
              <VSelect
                v-model="form.reseller_id"
                :items="resellers"
                item-title="name"
                item-value="id"
                label="Reseller"
                clearable
                @update:model-value="loadSalesForReseller"
              />
            </VCol>
            <VCol v-if="isSuperAdmin" cols="6">
              <VSelect v-model="form.sales_id" :items="salesList" item-title="name" item-value="id" label="Sales" clearable />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.collector_id" :items="collectors" item-title="name" item-value="id" label="Collector" clearable />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.status" :items="statusOptions" label="Status" />
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
