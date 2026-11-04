<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'sidebar' | 'floating' | 'inset'
  collapsible?: 'offcanvas' | 'icon' | 'none'
  side?: 'left' | 'right'
}>(), {
  variant: 'sidebar',
  collapsible: 'icon',
  side: 'left'
})

const open = ref(true)
</script>

<template>
  <div
    class="flex flex-1 min-h-svh"
    :class="[
      variant === 'inset' && 'bg-neutral-50 dark:bg-gray-950',
      side === 'right' && 'flex-row-reverse'
    ]"
  >
    <Sidebar
      v-model:open="open"
      :variant="variant"
      :collapsible="collapsible"
      :side="side"
    />

    <div
      class="flex-1 flex flex-col overflow-hidden lg:peer-data-[variant=floating]:my-4 peer-data-[variant=inset]:m-4 lg:peer-data-[variant=inset]:not-peer-data-[collapsible=offcanvas]:ms-0 peer-data-[variant=inset]:rounded-xl peer-data-[variant=inset]:shadow-sm peer-data-[variant=inset]:ring peer-data-[variant=inset]:ring-default bg-default"
    >
      <div
        class="h-(--ui-header-height) shrink-0 flex items-center px-4"
        :class="[
          variant !== 'floating' && 'border-b border-default',
          side === 'right' && 'justify-end'
        ]"
      >
        <UButton
          :icon="side === 'left' ? 'i-lucide-panel-left' : 'i-lucide-panel-right'"
          color="neutral"
          variant="ghost"
          aria-label="Toggle sidebar"
          @click="open = !open"
        />
      </div>

      <main class="flex-1 p-4">
        <slot />
      </main>

      <AppFooter />
    </div>
  </div>
</template>
