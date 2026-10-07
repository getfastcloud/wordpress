import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import fs from 'fs'

let exitHandlersBound = false
const hotFile = './.hot'

export default defineConfig({
    server: {
        host: true,
        port: 5175,
        origin: 'http://localhost:5175',
        cors: {
            origin: '*',
        },
    },
    build: {
        outDir: 'assets',
        assetsDir: '',
        emptyOutDir: true,
        manifest: true,
        modulePreload: {
            polyfill: false
        },
        rollupOptions: {
            input: './src/main.ts',
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        return 'vendor'
                    }
                }
            }
        }
    },
    experimental: {
        // Emit asset URLs relative to the referencing JS/CSS file instead of
        // absolute from root (/logo.svg → ./logo.svg).  When WordPress loads
        // the module from /wp-content/plugins/fastcloudwp/assets/main.js the
        // browser resolves ./logo.svg against that URL automatically.
        renderBuiltUrl() {
            return {relative: true}
        },
    },
    plugins: [
        vue(),
        tailwindcss(),
        {
            name: 'fastcloudwp-hot',
            configureServer(server) {
                server.httpServer?.once('listening', () => {
                    fs.writeFileSync(hotFile, 'http://localhost:5175')
                })
                server.httpServer?.once('close', () => {
                    if (fs.existsSync(hotFile)) fs.unlinkSync(hotFile)
                })

                if (!exitHandlersBound) {
                    const clean = () => {
                        if (fs.existsSync(hotFile)) {
                            fs.rmSync(hotFile)
                        }
                    }

                    process.on('exit', clean)
                    process.on('SIGINT', () => process.exit())
                    process.on('SIGTERM', () => process.exit())
                    process.on('SIGHUP', () => process.exit())

                    exitHandlersBound = true
                }
            }
        }
    ],
})
