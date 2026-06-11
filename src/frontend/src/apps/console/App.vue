<script setup>
// Auth restore happens in the router guard (before the first protected
// navigation resolves), so a hard reload lands on the requested route.
</script>

<template>
  <RouterView />
</template>
