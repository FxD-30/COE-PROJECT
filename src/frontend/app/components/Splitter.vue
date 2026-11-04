<script setup lang="ts">
import type { SplitterItem } from '@nuxt/ui'

// Resizable two-pane layout. Use the #sidebar slot for the collapsible left pane
// and the default slot for the main pane.
const props = withDefaults(defineProps<{
  id?: string
  minSize?: number
  defaultSize?: number
  collapsedSize?: number
}>(), {
  id: 'splitter',
  minSize: 200,
  defaultSize: 250,
  collapsedSize: 48
})

const items = computed<SplitterItem[]>(() => [
  {
    slot: 'sidebar',
    sizeUnit: 'px',
    minSize: props.minSize,
    defaultSize: props.defaultSize,
    collapsible: true,
    collapsedSize: props.collapsedSize,
    class: 'bg-elevated/50 border border-default rounded-xl'
  },
  { slot: 'main', class: 'bg-elevated/50 border border-default rounded-xl' }
])
</script>

<template>
  <div class="w-full h-96">
    <USplitter :id="id" :items="items">
      <template #sidebar="{ collapsed, collapse, expand }">
        <div class="flex-1 flex flex-col gap-2 p-2">
          <UButton
            :icon="collapsed ? 'i-lucide-panel-left-open' : 'i-lucide-panel-left-close'"
            :label="collapsed ? undefined : 'Collapse'"
            :aria-label="collapsed ? 'Expand' : undefined"
            color="neutral"
            variant="subtle"
            @click="collapsed ? expand() : collapse()"
          />
          <slot v-if="!collapsed" name="sidebar" />
        </div>
      </template>

      <template #main>
        <div class="flex-1 p-4">
          <slot />
        </div>
      </template>
    </USplitter>
  </div>
</template>
