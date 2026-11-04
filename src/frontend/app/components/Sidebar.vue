<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

withDefaults(defineProps<{
  variant?: 'sidebar' | 'floating' | 'inset'
  collapsible?: 'offcanvas' | 'icon' | 'none'
  side?: 'left' | 'right'
}>(), {
  variant: 'sidebar',
  collapsible: 'icon',
  side: 'left'
})

const open = defineModel<boolean>('open', { default: true })

const items: NavigationMenuItem[] = [
  {
    label: 'Home',
    icon: 'i-lucide-house',
    to: '/'
  },
  {
    label: 'Products',
    icon: 'i-lucide-package',
    to: '/products'
  }
]
</script>

<template>
  <USidebar
    v-model:open="open"
    :variant="variant"
    :collapsible="collapsible"
    :side="side"
    :ui="{ container: 'h-full' }"
  >
    <template #header="{ state }">
      <UIcon name="i-lucide-store" class="size-6 shrink-0 text-primary" />
      <span v-if="state === 'expanded'" class="font-semibold truncate">Test Mart</span>
    </template>

    <template #default="{ state }">
      <UNavigationMenu
        :items="items"
        orientation="vertical"
        :collapsed="state === 'collapsed'"
        tooltip
        :ui="{ link: 'p-1.5 overflow-hidden' }"
      />
    </template>
  </USidebar>
</template>
