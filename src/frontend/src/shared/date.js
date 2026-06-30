import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/de';
import 'dayjs/locale/en-gb';
import 'dayjs/locale/fr';

dayjs.extend(relativeTime);

const REL_LOCALE = { de: 'de', en: 'en-gb', fr: 'fr' };
const ABS_LOCALE = { de: 'de-DE', en: 'en-GB', fr: 'fr-FR' };

/** Relative time, e.g. "Vor 3 Tagen" (localized, first letter capitalized). */
export function relative(iso, lang = 'de') {
  if (!iso) return '';
  const text = dayjs(iso).locale(REL_LOCALE[lang] || 'de').fromNow();
  return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Absolute date "DD. MMMM YYYY" (localized). */
export function absolute(iso, lang = 'de') {
  if (!iso) return '';
  return new Intl.DateTimeFormat(ABS_LOCALE[lang] || 'de-DE', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(iso));
}
