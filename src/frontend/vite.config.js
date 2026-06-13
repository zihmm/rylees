import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

// SPA history fallback for the multi-page dev server: serve each app's
// index.html for its deep links so a browser refresh doesn't 404.
function mpaFallback() {
  return {
    name: "mpa-spa-fallback",
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        if (req.headers.accept?.includes("text/html")) {
          const path = req.url.split("?")[0];
          if (path.startsWith("/console")) {
            req.url = "/console/index.html";
          } else if (path.startsWith("/history")) {
            req.url = "/history/index.html";
          }
        }
        next();
      });
    },
  };
}

export default defineConfig({
  plugins: [vue(), mpaFallback()],
  server: {
    proxy: {
      "/v1": {
        target: "http://api.rylees.test",
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: "build",
    rollupOptions: {
      input: {
        console: "console/index.html",
        history: "history/index.html",
      },
    },
  },
});
