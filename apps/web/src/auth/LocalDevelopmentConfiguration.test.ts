import type { ConfigEnv, UserConfig } from 'vite'
import { describe, expect, it } from 'vitest'
import viteConfiguration from '../../vite.config'

function resolveConfiguration(env: ConfigEnv): UserConfig {
  const configuration = viteConfiguration as unknown as (
    env: ConfigEnv,
  ) => UserConfig

  return configuration(env)
}

describe('local authentication development configuration', () => {
  it('proxies same-origin API and CSRF paths only to the loopback API', () => {
    const configuration = resolveConfiguration({
      command: 'serve',
      mode: 'development',
    })

    expect(configuration.server).toMatchObject({
      host: '127.0.0.1',
      port: 5174,
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

  it('keeps the ordinary production build separate from the demo distribution', () => {
    const production = resolveConfiguration({
      command: 'build',
      mode: 'production',
    })
    const demo = resolveConfiguration({
      command: 'build',
      mode: 'demo',
    })

    expect(production.base).toBe('/')
    expect(production.build?.outDir).toBe('dist')
    expect(demo.base).toBe('./')
    expect(demo.build?.outDir).toBe('dist-demo')
  })

  it('does not fold lazy marketing or operator pages into the guest-home graph', () => {
    const production = resolveConfiguration({
      command: 'build',
      mode: 'production',
    })
    const preload = production.build?.modulePreload

    expect(production.build?.rollupOptions?.output).toBeUndefined()
    expect(preload).toEqual(
      expect.objectContaining({
        resolveDependencies: expect.any(Function),
      }),
    )

    if (typeof preload === 'object' && preload?.resolveDependencies) {
      expect(
        preload.resolveDependencies(
          'assets/index.js',
          [
            'assets/marketing-landing-abc.js',
            'assets/MarketingLanding-def.js',
            'assets/operator-console-ghi.js',
            'assets/OperatorConsole-jkl.js',
            'assets/GuestMiraHome-mno.js',
          ],
          { hostId: 'index.html', hostType: 'html' },
        ),
      ).toEqual(['assets/GuestMiraHome-mno.js'])
    }
  })
})
