<script setup>
// Renders release-note bodies that the API has already converted from markdown
// to sanitized HTML (CommonMark with raw HTML escaped and unsafe links
// stripped — see the API's MarkdownRenderer). This is the single, audited
// v-html sink for that trusted server-sanitized output; never pass raw,
// unsanitized free-text here.
defineProps({
  html: { type: String, default: '' },
});
</script>

<template>
  <div class="markdown-body" v-html="html"></div>
</template>

<style scoped>
.markdown-body :deep(p) {
  margin: 0;
}
.markdown-body :deep(p + p) {
  margin-top: 0.75em;
}
.markdown-body :deep(strong) {
  font-weight: 600;
}
.markdown-body :deep(em) {
  font-style: italic;
}
.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  margin: 0.25em 0;
  padding-left: 1.25em;
}
.markdown-body :deep(ul) {
  list-style: disc;
}
.markdown-body :deep(ol) {
  list-style: decimal;
}
.markdown-body :deep(li) {
  margin: 0.125em 0;
}
.markdown-body :deep(a) {
  text-decoration: underline;
}
.markdown-body :deep(code) {
  font-family: ui-monospace, monospace;
  font-size: 0.9em;
}
</style>
