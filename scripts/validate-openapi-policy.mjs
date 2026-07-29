#!/usr/bin/env node

import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import yaml from 'js-yaml'

const repositoryRoot = dirname(dirname(fileURLToPath(import.meta.url)))
const contractPath = resolve(repositoryRoot, 'docs/api/openapi.yaml')
const contract = yaml.load(await readFile(contractPath, 'utf8'))
const methods = new Set([
  'delete',
  'get',
  'head',
  'options',
  'patch',
  'post',
  'put',
  'trace',
])
const operationalHealthOperations = new Set([
  'getLiveness',
  'getReadiness',
])
const violations = []

if (
  contract === null ||
  typeof contract !== 'object' ||
  !('paths' in contract) ||
  contract.paths === null ||
  typeof contract.paths !== 'object'
) {
  throw new Error('The OpenAPI contract does not contain a paths object.')
}

for (const [path, pathItem] of Object.entries(contract.paths)) {
  if (pathItem === null || typeof pathItem !== 'object') {
    continue
  }

  for (const [method, operation] of Object.entries(pathItem)) {
    if (
      !methods.has(method) ||
      operation === null ||
      typeof operation !== 'object'
    ) {
      continue
    }

    const operationId = operation.operationId
    const responses = operation.responses
    const hasDocumentedClientError =
      responses !== null &&
      typeof responses === 'object' &&
      Object.keys(responses).some((status) => /^4[0-9]{2}$/.test(status))

    if (
      !hasDocumentedClientError &&
      (typeof operationId !== 'string' ||
        !operationalHealthOperations.has(operationId))
    ) {
      violations.push(
        `${method.toUpperCase()} ${path} must document at least one 4xx response.`,
      )
    }
  }
}

if (violations.length > 0) {
  for (const violation of violations) {
    console.error(violation)
  }

  process.exitCode = 1
} else {
  console.log(
    'OpenAPI policy permits missing 4xx responses only for operational health checks.',
  )
}
