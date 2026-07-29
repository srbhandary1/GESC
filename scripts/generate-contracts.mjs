#!/usr/bin/env node

import {
  mkdtemp,
  readdir,
  readFile,
  rm,
} from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createClient } from '@hey-api/openapi-ts'
import { contractsConfig } from '../openapi-ts.config.mjs'

const checkOnly = process.argv.includes('--check')
const unknownArguments = process.argv.slice(2).filter((value) => value !== '--check')

if (unknownArguments.length > 0) {
  console.error(`Unknown argument: ${unknownArguments[0]}`)
  process.exit(2)
}

const repositoryRoot = dirname(dirname(fileURLToPath(import.meta.url)))
const generatedPath = resolve(
  repositoryRoot,
  'packages/contracts/src/generated',
)

process.chdir(repositoryRoot)

async function generate(outputPath) {
  await createClient({
    ...contractsConfig,
    output: {
      ...contractsConfig.output,
      path: outputPath,
    },
  })
}

async function filesIn(directory) {
  const files = []

  async function visit(current) {
    const entries = await readdir(current, { withFileTypes: true })

    for (const entry of entries) {
      const path = join(current, entry.name)

      if (entry.isDirectory()) {
        await visit(path)
      } else if (entry.isFile()) {
        files.push(relative(directory, path))
      }
    }
  }

  try {
    await visit(directory)
  } catch (error) {
    if (error && typeof error === 'object' && error.code === 'ENOENT') {
      return []
    }

    throw error
  }

  return files.sort()
}

async function directoriesMatch(expectedDirectory, actualDirectory) {
  const expectedFiles = await filesIn(expectedDirectory)
  const actualFiles = await filesIn(actualDirectory)

  if (
    expectedFiles.length !== actualFiles.length ||
    expectedFiles.some((file, index) => file !== actualFiles[index])
  ) {
    return false
  }

  for (const file of expectedFiles) {
    const [expected, actual] = await Promise.all([
      readFile(join(expectedDirectory, file)),
      readFile(join(actualDirectory, file)),
    ])

    if (!expected.equals(actual)) {
      return false
    }
  }

  return true
}

if (!checkOnly) {
  await generate(generatedPath)
  console.log(`Generated contracts in ${generatedPath}`)
  process.exit(0)
}

const temporaryRoot = await mkdtemp(
  join(tmpdir(), 'global-english-contracts-'),
)
const temporaryOutput = join(temporaryRoot, 'generated')

try {
  await generate(temporaryOutput)

  if (!(await directoriesMatch(generatedPath, temporaryOutput))) {
    console.error(
      'Generated contracts are stale. Run `pnpm run contracts:generate` and commit the result.',
    )
    process.exitCode = 1
  } else {
    console.log('Generated TypeScript contracts match the OpenAPI source.')
  }
} finally {
  await rm(temporaryRoot, { recursive: true, force: true })
}
