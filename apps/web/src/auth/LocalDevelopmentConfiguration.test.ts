import type { UserConfig } from 'vite'
import { describe, expect, it } from 'vitest'
import viteConfiguration from '../../vite.config'

describe('local authentication development configuration', () => {
  it('proxies same-origin API and CSRF paths only to the loopback API', () => {
    const configuration = viteConfiguration as UserConfig

    expect(configuration.server).toMatchObject({
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: false,
        },
        '/sanctum': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: false,
        },
      },
    })
  })
})
