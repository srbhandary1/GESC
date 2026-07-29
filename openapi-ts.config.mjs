import { defineConfig } from '@hey-api/openapi-ts'

export const contractsConfig = {
  input: './docs/api/openapi.yaml',
  output: {
    clean: true,
    path: './packages/contracts/src/generated',
    tsConfigPath: './packages/contracts/tsconfig.json',
  },
  plugins: [
    '@hey-api/typescript',
    {
      name: '@hey-api/schemas',
      type: 'json',
    },
    '@hey-api/client-fetch',
    '@hey-api/sdk',
  ],
}

export default defineConfig(contractsConfig)
