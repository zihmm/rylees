import { computed, unref } from 'vue';

/** Parse a version string into an array of numeric parts, e.g. "3.5.1" -> [3,5,1]. */
function parts(version) {
  return String(version ?? '')
    .split('.')
    .map((p) => parseInt(p, 10) || 0);
}

/** Compare two version strings descending (newest first). */
function compareVersionsDesc(a, b) {
  const pa = parts(a);
  const pb = parts(b);
  const len = Math.max(pa.length, pb.length);
  for (let i = 0; i < len; i += 1) {
    const diff = (pb[i] || 0) - (pa[i] || 0);
    if (diff !== 0) return diff;
  }
  return 0;
}

/** Sort items newest first: by publishedAt desc, falling back to version desc. */
function sortNewestFirst(items) {
  return [...items].sort((a, b) => {
    const ta = a.publishedAt ? new Date(a.publishedAt).getTime() : NaN;
    const tb = b.publishedAt ? new Date(b.publishedAt).getTime() : NaN;
    if (!Number.isNaN(ta) && !Number.isNaN(tb) && ta !== tb) {
      return tb - ta;
    }
    return compareVersionsDesc(a.version, b.version);
  });
}

/**
 * Group release items by major version and compute the current (highest) version.
 *
 * @param {import('vue').Ref<Array>|Array} itemsSource reactive ref or plain array of items
 * @returns {{ currentVersion: import('vue').ComputedRef<string|null>, majorGroups: import('vue').ComputedRef<Array> }}
 */
export function useVersionGrouping(itemsSource) {
  const items = computed(() => unref(itemsSource) || []);

  const currentVersion = computed(() => {
    if (!items.value.length) return null;
    return [...items.value].sort((a, b) => compareVersionsDesc(a.version, b.version))[0].version;
  });

  const majorGroups = computed(() => {
    const buckets = new Map();
    for (const item of items.value) {
      const major = parts(item.version)[0] || 0;
      if (!buckets.has(major)) buckets.set(major, []);
      buckets.get(major).push(item);
    }
    return [...buckets.entries()]
      .sort((a, b) => b[0] - a[0])
      .map(([major, groupItems]) => ({
        major,
        items: sortNewestFirst(groupItems),
      }));
  });

  return { currentVersion, majorGroups };
}
