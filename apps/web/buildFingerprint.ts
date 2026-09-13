import { createHash } from 'node:crypto'
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, relative, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const webDirectory = dirname(fileURLToPath(import.meta.url))
const defaultRepositoryRoot = resolve(webDirectory, '../..')

const trackedInputs = [
  'package.json',
  'pnpm-lock.yaml',
  'pnpm-workspace.yaml',
  'apps/web/buildFingerprint.ts',
  'apps/web/index.html',
  'apps/web/package.json',
  'apps/web/tsconfig.app.json',
  'apps/web/tsconfig.json',
  'apps/web/tsconfig.node.json',
  'apps/web/vite.config.ts',
  'apps/web/public',
  'apps/web/src',
  'packages/contracts/src',
  'packages/design-system/src',
  'packages/localization/src',
] as const

function collectFiles(path: string): string[] {
  if (!existsSync(path)) {
    return []
  }

  if (!statSync(path).isDirectory()) {
    return [path]
  }

  return readdirSync(path, { withFileTypes: true }).flatMap((entry) => {
    const child = resolve(path, entry.name)

    if (entry.isDirectory()) {
      return collectFiles(child)
    }

    return entry.isFile() ? [child] : []
  })
}

export function calculateWebBuildFingerprint(
  repositoryRoot: string = defaultRepositoryRoot,
): string {
  const files = trackedInputs
    .flatMap((input) => collectFiles(resolve(repositoryRoot, input)))
    .sort()
  const hash = createHash('sha256')

  for (const file of files) {
    hash.update(relative(repositoryRoot, file))
    hash.update('\0')
    hash.update(readFileSync(file))
    hash.update('\0')
  }

  return hash.digest('hex')
}

const invokedPath = process.argv[1]
if (
  invokedPath !== undefined &&
  import.meta.url === pathToFileURL(resolve(invokedPath)).href
) {
  process.stdout.write(`${calculateWebBuildFingerprint()}\n`)
}
