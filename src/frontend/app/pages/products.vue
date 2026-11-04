<script setup lang="ts">
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui'

// Mirrors App\Http\Resources\ProductResource. price is a decimal:2 cast,
// so Laravel serialises it as a string like "4999.00".
interface Product {
  id: number
  category_id: number | null
  category: { id: number, name: string, slug: string } | null
  name: string
  sku: string
  description: string | null
  price: string
  stock: number
  image: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

interface Category {
  id: number
  name: string
  slug: string
}

// Laravel paginated resource collection
interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// Every API error: { error: { status, code, message, details? } }
interface ApiError {
  error: {
    status: number
    code: string
    message: string
    details?: Record<string, string[]>
  }
}

interface ProductForm {
  category: string
  name: string
  sku: string
  description: string
  price: number
  stock: number
  image: string
  is_active: boolean
}

const { public: { apiBase } } = useRuntimeConfig()
const toast = useToast()

// Select items can't use '' or null as a value, so these sentinels mean "no filter" / "no category".
const ALL = 'all'
const NONE = 'none'

// List controls -> query string for GET /api/products
const page = ref(1)
const search = ref('')
const q = ref('')
const category = ref(ALL)
const status = ref(ALL)
const stock = ref(ALL)
const sort = ref('-created_at')

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { q.value = value.trim() }, 300)
})

// Any filter change starts again from the first page.
watch([q, category, status, stock, sort], () => { page.value = 1 })

const query = computed(() => ({
  page: page.value,
  sort: sort.value,
  ...(q.value && { q: q.value }),
  ...(category.value !== ALL && { category_id: category.value }),
  ...(status.value !== ALL && { is_active: status.value }),
  ...(stock.value !== ALL && { in_stock: stock.value })
}))

// Fetch in the browser only: during SSR inside the frontend container,
// localhost:8000 does not reach the backend.
const { data, status: fetchStatus, error, refresh } = await useFetch<Paginated<Product>>(
  `${apiBase}/products`,
  { query, server: false }
)

const { data: categoryData } = await useFetch<Paginated<Category>>(
  `${apiBase}/categories`,
  { query: { per_page: 100, sort: 'name' }, server: false }
)

const products = computed(() => data.value?.data ?? [])
const total = computed(() => data.value?.meta.total ?? 0)
const perPage = computed(() => data.value?.meta.per_page ?? 10)
const categories = computed(() => categoryData.value?.data ?? [])

const categoryFilterItems = computed(() => [
  { label: 'All categories', value: ALL },
  ...categories.value.map(c => ({ label: c.name, value: String(c.id) }))
])
const categoryFormItems = computed(() => [
  { label: 'Uncategorised', value: NONE },
  ...categories.value.map(c => ({ label: c.name, value: String(c.id) }))
])
const statusItems = [
  { label: 'Any status', value: ALL },
  { label: 'Active', value: 'true' },
  { label: 'Inactive', value: 'false' }
]
const stockItems = [
  { label: 'Any stock', value: ALL },
  { label: 'In stock', value: 'true' },
  { label: 'Out of stock', value: 'false' }
]
const sortItems = [
  { label: 'Newest first', value: '-created_at' },
  { label: 'Oldest first', value: 'created_at' },
  { label: 'Name A-Z', value: 'name' },
  { label: 'Name Z-A', value: '-name' },
  { label: 'Price: low to high', value: 'price' },
  { label: 'Price: high to low', value: '-price' },
  { label: 'Stock: low to high', value: 'stock' },
  { label: 'Stock: high to low', value: '-stock' }
]

const hasFilters = computed(() => !!search.value || category.value !== ALL || status.value !== ALL || stock.value !== ALL)

function clearFilters() {
  search.value = ''
  q.value = ''
  category.value = ALL
  status.value = ALL
  stock.value = ALL
}

const columns: TableColumn<Product>[] = [
  { accessorKey: 'name', header: 'Product' },
  { accessorKey: 'sku', header: 'SKU' },
  { accessorKey: 'category', header: 'Category' },
  { accessorKey: 'price', header: 'Price' },
  { accessorKey: 'stock', header: 'Stock' },
  { accessorKey: 'is_active', header: 'Status' },
  { id: 'actions' }
]

function formatPrice(price: string) {
  return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(Number(price))
}

function apiError(err: any): ApiError['error'] | undefined {
  return (err?.data as ApiError | undefined)?.error
}

// Create / edit form
const emptyForm = (): ProductForm => ({
  category: NONE,
  name: '',
  sku: '',
  description: '',
  price: 0,
  stock: 0,
  image: '',
  is_active: true
})

const formOpen = ref(false)
const editing = ref<Product | null>(null)
const state = reactive<ProductForm>(emptyForm())
const saving = ref(false)
const form = useTemplateRef('form')

function openCreate() {
  editing.value = null
  Object.assign(state, emptyForm())
  formOpen.value = true
}

function openEdit(product: Product) {
  editing.value = product
  Object.assign(state, {
    category: product.category_id ? String(product.category_id) : NONE,
    name: product.name,
    sku: product.sku,
    description: product.description ?? '',
    price: Number(product.price),
    stock: product.stock,
    image: product.image ?? '',
    is_active: product.is_active
  })
  formOpen.value = true
}

// Same rules as ProductController::rules()
function validate(s: ProductForm): FormError[] {
  const errors: FormError[] = []
  if (!s.name) errors.push({ name: 'name', message: 'Name is required' })
  else if (s.name.length > 255) errors.push({ name: 'name', message: 'Name must be at most 255 characters' })
  if (!s.sku) errors.push({ name: 'sku', message: 'SKU is required' })
  else if (s.sku.length > 64) errors.push({ name: 'sku', message: 'SKU must be at most 64 characters' })
  if (s.price == null || s.price < 0) errors.push({ name: 'price', message: 'Price must be 0 or more' })
  if (s.stock == null || s.stock < 0 || !Number.isInteger(s.stock)) errors.push({ name: 'stock', message: 'Stock must be a whole number, 0 or more' })
  return errors
}

async function onSubmit(event: FormSubmitEvent<ProductForm>) {
  saving.value = true
  const { category: selected, ...fields } = event.data
  const body = {
    ...fields,
    category_id: selected === NONE ? null : Number(selected),
    description: fields.description || null,
    image: fields.image || null
  }

  try {
    if (editing.value) {
      await $fetch(`${apiBase}/products/${editing.value.id}`, { method: 'PUT', body })
    } else {
      await $fetch(`${apiBase}/products`, { method: 'POST', body })
    }
    toast.add({ title: editing.value ? 'Product updated' : 'Product created', color: 'success', icon: 'i-lucide-check' })
    formOpen.value = false
    await refresh()
  } catch (err: any) {
    const e = apiError(err)
    if (e?.details) {
      // The form field for category_id is called "category"
      form.value?.setErrors(Object.entries(e.details).map(([name, messages]) => ({
        name: name === 'category_id' ? 'category' : name,
        message: messages[0]!
      })))
    } else {
      toast.add({ title: 'Could not save product', description: e?.message ?? err?.message, color: 'error', icon: 'i-lucide-circle-alert' })
    }
  } finally {
    saving.value = false
  }
}

// Delete confirmation
const deleting = ref<Product | null>(null)
const deleteOpen = computed({
  get: () => deleting.value !== null,
  set: (open) => { if (!open) deleting.value = null }
})
const removing = ref(false)

async function confirmDelete() {
  if (!deleting.value) return
  removing.value = true
  try {
    await $fetch(`${apiBase}/products/${deleting.value.id}`, { method: 'DELETE' })
    toast.add({ title: 'Product deleted', color: 'success', icon: 'i-lucide-trash-2' })
    deleting.value = null
    // Step back if we just removed the last item on this page
    if (products.value.length === 1 && page.value > 1) page.value--
    else await refresh()
  } catch (err: any) {
    // e.g. 409 when the product is on an existing order
    toast.add({ title: 'Could not delete product', description: apiError(err)?.message ?? err?.message, color: 'error', icon: 'i-lucide-circle-alert' })
    deleting.value = null
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-highlighted">Products</h1>
        <p class="text-sm text-muted">{{ total }} product{{ total === 1 ? '' : 's' }}{{ hasFilters ? ' match these filters' : '' }}</p>
      </div>
      <UButton icon="i-lucide-plus" label="Add product" @click="openCreate" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Search name, SKU or description"
        class="w-full sm:w-72"
      />
      <USelect v-model="category" :items="categoryFilterItems" class="w-40" />
      <USelect v-model="status" :items="statusItems" class="w-36" />
      <USelect v-model="stock" :items="stockItems" class="w-36" />
      <USelect v-model="sort" :items="sortItems" icon="i-lucide-arrow-up-down" class="w-48" />
      <UButton
        v-if="hasFilters"
        label="Clear"
        icon="i-lucide-x"
        color="neutral"
        variant="ghost"
        @click="clearFilters"
      />
    </div>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Unable to load products"
      :description="apiError(error)?.message ?? error.message"
    />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <UTable
        :data="products"
        :columns="columns"
        :loading="fetchStatus === 'pending' || fetchStatus === 'idle'"
        :empty="hasFilters ? 'No products match these filters.' : 'No products yet.'"
      >
        <template #name-cell="{ row }">
          <div class="flex items-center gap-3">
            <UAvatar :src="row.original.image ?? undefined" :alt="row.original.name" icon="i-lucide-package" size="md" />
            <div class="min-w-0">
              <p class="font-medium text-highlighted truncate">{{ row.original.name }}</p>
              <p v-if="row.original.description" class="text-xs text-muted truncate max-w-xs">{{ row.original.description }}</p>
            </div>
          </div>
        </template>

        <template #sku-cell="{ row }">
          <span class="font-mono text-sm">{{ row.original.sku }}</span>
        </template>

        <template #category-cell="{ row }">
          <span v-if="row.original.category">{{ row.original.category.name }}</span>
          <span v-else class="text-dimmed">-</span>
        </template>

        <template #price-cell="{ row }">
          {{ formatPrice(row.original.price) }}
        </template>

        <template #stock-cell="{ row }">
          <span :class="row.original.stock === 0 ? 'text-error' : ''">{{ row.original.stock }}</span>
        </template>

        <template #is_active-cell="{ row }">
          <UBadge
            :label="row.original.is_active ? 'Active' : 'Inactive'"
            :color="row.original.is_active ? 'success' : 'neutral'"
            variant="subtle"
          />
        </template>

        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1">
            <UButton icon="i-lucide-pencil" color="neutral" variant="ghost" aria-label="Edit" @click="openEdit(row.original)" />
            <UButton icon="i-lucide-trash-2" color="error" variant="ghost" aria-label="Delete" @click="deleting = row.original" />
          </div>
        </template>
      </UTable>
    </UCard>

    <div v-if="total > perPage" class="flex justify-center">
      <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
    </div>

    <!-- Create / edit -->
    <UModal v-model:open="formOpen" :title="editing ? 'Edit product' : 'Add product'">
      <template #body>
        <UForm ref="form" :state="state" :validate="validate" class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit="onSubmit">
          <UFormField label="Name" name="name" required>
            <UInput v-model="state.name" class="w-full" />
          </UFormField>

          <UFormField label="SKU" name="sku" required>
            <UInput v-model="state.sku" class="w-full" />
          </UFormField>

          <UFormField label="Category" name="category" class="sm:col-span-2">
            <USelect v-model="state.category" :items="categoryFormItems" class="w-full" />
          </UFormField>

          <UFormField label="Description" name="description" class="sm:col-span-2">
            <UTextarea v-model="state.description" :rows="3" class="w-full" />
          </UFormField>

          <UFormField label="Price (RM)" name="price" required>
            <UInputNumber
              v-model="state.price"
              :min="0"
              :step="0.01"
              :format-options="{ minimumFractionDigits: 2, maximumFractionDigits: 2 }"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Stock" name="stock" required>
            <UInputNumber v-model="state.stock" :min="0" :step="1" class="w-full" />
          </UFormField>

          <UFormField label="Image URL" name="image" class="sm:col-span-2">
            <UInput v-model="state.image" type="url" placeholder="https://..." class="w-full" />
          </UFormField>

          <UFormField name="is_active" class="sm:col-span-2">
            <USwitch v-model="state.is_active" label="Active" />
          </UFormField>

          <div class="sm:col-span-2 flex justify-end gap-2">
            <UButton label="Cancel" color="neutral" variant="ghost" @click="formOpen = false" />
            <UButton type="submit" :label="editing ? 'Save changes' : 'Add product'" :loading="saving" />
          </div>
        </UForm>
      </template>
    </UModal>

    <!-- Delete confirmation -->
    <UModal
      v-model:open="deleteOpen"
      title="Delete product"
      :description="deleting ? `Delete ${deleting.name} (${deleting.sku})? This cannot be undone.` : ''"
    >
      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton label="Cancel" color="neutral" variant="ghost" @click="deleting = null" />
          <UButton label="Delete" color="error" :loading="removing" @click="confirmDelete" />
        </div>
      </template>
    </UModal>
  </div>
</template>
