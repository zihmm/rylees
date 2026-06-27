// jsdom doesn't implement window.scrollTo; Notification.vue calls it on mount.
// Provide a no-op so components that scroll on appear don't throw in tests.
window.scrollTo = () => {};
