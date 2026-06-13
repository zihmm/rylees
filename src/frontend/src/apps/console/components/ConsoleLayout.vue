<script setup>
import { useSlots } from 'vue';
import SideNav from './SideNav.vue';
import PageHeader from './PageHeader.vue';

defineProps({
  parent: { type: Object, default: null },
  current: { type: String, default: '' },
});
const slots = useSlots();
</script>

<template>
  <div class="flex h-screen bg-white">
    <SideNav />

    <div class="flex-1 min-w-0 flex flex-col border-l border-field-border">
      <PageHeader :parent="parent" :current="current">
        <template v-if="slots['header-actions']" #actions>
          <slot name="header-actions" />
        </template>
      </PageHeader>

      <!-- Content + right sidebar sit UNDER the full-width header. -->
      <div class="flex-1 flex min-h-0">
        <main class="flex-1 min-w-0 flex flex-col min-h-0">
          <div class="flex-1 overflow-auto px-10 py-8">
            <slot />
          </div>

          <!-- Pinned action bar: stays visible while the content above scrolls. -->
          <div
            v-if="slots['footer-actions']"
            class="shrink-0 flex justify-end gap-3 border-t border-field-border px-10 py-4"
          >
            <slot name="footer-actions" />
          </div>
        </main>

        <aside v-if="slots.sidebar" class="w-[379px] shrink-0 bg-panel border-l border-field-border p-8 overflow-auto">
          <slot name="sidebar" />
        </aside>
      </div>
    </div>
  </div>
</template>
