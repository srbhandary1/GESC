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
const oauthOperations = new Set([
  'beginMobileAuthorization',
  'exchangeMobileToken',
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

const resolveLocalReference = (value) => {
  if (
    value === null ||
    typeof value !== 'object' ||
    !('$ref' in value) ||
    typeof value.$ref !== 'string' ||
    !value.$ref.startsWith('#/')
  ) {
    return value
  }

  return value.$ref
    .slice(2)
    .split('/')
    .map((segment) => segment.replaceAll('~1', '/').replaceAll('~0', '~'))
    .reduce((resolved, segment) => {
      if (
        resolved === null ||
        typeof resolved !== 'object' ||
        !(segment in resolved)
      ) {
        throw new Error(`Could not resolve local OpenAPI reference ${value.$ref}.`)
      }

      return resolved[segment]
    }, contract)
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

    if (responses === null || typeof responses !== 'object') {
      continue
    }

    const expectedMediaType =
      typeof operationId === 'string' && oauthOperations.has(operationId)
        ? 'application/json'
        : 'application/problem+json'
    const forbiddenMediaType =
      expectedMediaType === 'application/json'
        ? 'application/problem+json'
        : 'application/json'

    for (const [status, responseReference] of Object.entries(responses)) {
      if (!/^4[0-9]{2}$/.test(status) && status !== 'default') {
        continue
      }

      const response = resolveLocalReference(responseReference)
      const content =
        response !== null &&
        typeof response === 'object' &&
        'content' in response &&
        response.content !== null &&
        typeof response.content === 'object'
          ? response.content
          : {}

      if (!(expectedMediaType in content)) {
        violations.push(
          `${method.toUpperCase()} ${path} response ${status} must use ${expectedMediaType}.`,
        )
      }

      if (forbiddenMediaType in content) {
        violations.push(
          `${method.toUpperCase()} ${path} response ${status} must not use ${forbiddenMediaType}.`,
        )
      }
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
    'OpenAPI policy permits missing 4xx responses only for operational health checks, requires RFC 9457 for domain APIs and preserves OAuth JSON errors.',
  )
}
