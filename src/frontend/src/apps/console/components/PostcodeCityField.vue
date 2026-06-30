<script setup>
// Postcode + city on one row: postcode is a fixed ~120px, city fills the rest.
// Mirrors FormField's row layout so it lines up with the other TextField rows.
defineProps({
  postcode: { type: [String, Number], default: '' },
  city: { type: [String, Number], default: '' },
  postcodeError: { type: String, default: '' },
  cityError: { type: String, default: '' },
  postcodeLabel: { type: String, default: 'Postcode' },
  cityLabel: { type: String, default: 'City' },
});
defineEmits(['update:postcode', 'update:city']);
</script>

<template>
  <div class="flex flex-col sm:flex-row gap-2 sm:gap-8 py-6 border-b border-field-border">
    <div class="sm:w-48 shrink-0 sm:pt-3">
      <label class="text-[13px]" :class="(postcodeError || cityError) ? 'text-danger' : 'text-field-label'">
        {{ postcodeLabel }} / {{ cityLabel }}
      </label>
    </div>
    <div class="flex-1 max-w-md">
      <div class="flex gap-3">
        <input
          :value="postcode"
          :placeholder="postcodeLabel"
          :aria-label="postcodeLabel"
          class="w-[120px] shrink-0 h-11 rounded-field border px-4 text-[14px] text-black outline-none focus:border-accent"
          :class="postcodeError ? 'border-danger' : 'border-field-border'"
          @input="$emit('update:postcode', $event.target.value)"
        />
        <input
          :value="city"
          :placeholder="cityLabel"
          :aria-label="cityLabel"
          class="flex-1 min-w-0 h-11 rounded-field border px-4 text-[14px] text-black outline-none focus:border-accent"
          :class="cityError ? 'border-danger' : 'border-field-border'"
          @input="$emit('update:city', $event.target.value)"
        />
      </div>
      <p v-if="postcodeError" class="text-danger text-[11px] mt-1">{{ postcodeError }}</p>
      <p v-if="cityError" class="text-danger text-[11px] mt-1">{{ cityError }}</p>
    </div>
  </div>
</template>
