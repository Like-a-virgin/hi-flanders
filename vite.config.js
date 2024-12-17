import ViteRestart from 'vite-plugin-restart';

export default ({ command }) => ({
  base: command === "serve" ? "" : "/dist/",
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: "./web/dist",
    rollupOptions: {
      input: {
        app: "./src/scripts/main.js",
      },
      output: {
        sourcemap: true,
      },
    },
  },
  server: {
    hmr: {
      host: "localhost",
      protocol: "ws",
    },
  },
  plugins: [
    ViteRestart({
      reload: ["./templates/**/*"],
    }),
  ],
});