import { defineConfig } from 'vite'
import path from 'path'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    minify: true,
    manifest: false,
    rollupOptions: {
      input: {
        'frontend': path.resolve(__dirname, 'src/main.jsx'),
      },

      output:{
        dir: 'includes/assets/build',
        watch: true,
        entryFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
        manualChunks: undefined,
      },

    }
  }
})