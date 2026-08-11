import { readFileSync } from 'node:fs'
import process from 'node:process'
import yaml from 'js-yaml'

const workflowPath = new URL('../.github/workflows/ci.yml', import.meta.url)
const makefilePath = new URL('../Makefile', import.meta.url)
const workflow = yaml.load(readFileSync(workflowPath, 'utf8'))
const makefile = readFileSync(makefilePath, 'utf8')

const fail = (message) => {
  process.stderr.write(`release gate contract failed: ${message}\n`)
  process.exitCode = 1
}

const jobs = workflow?.jobs

if (!jobs || typeof jobs !== 'object') {
  fail('CI workflow has no jobs mapping')
} else {
  const requiredJobs = ['verify', 'android', 'operations', 'release-gate']

  for (const jobName of requiredJobs) {
    if (!jobs[jobName]) {
      fail(`missing ${jobName} job`)
    }
  }

  const commandRuns = (jobName) =>
    (jobs[jobName]?.steps ?? [])
      .map((step) => (typeof step?.run === 'string' ? step.run : ''))
      .join('\n')

  const verifyRuns = commandRuns('verify')
  for (const command of ['make verify', 'make e2e', 'make build']) {
    if (!verifyRuns.includes(command)) {
      fail(`verify job does not run ${command}`)
    }
  }

  const postgresImage = jobs.verify?.services?.postgres?.image
  if (
    typeof postgresImage !== 'string' ||
    !/^postgres:[^@]+@sha256:[0-9a-f]{64}$/.test(postgresImage)
  ) {
    fail('verify job does not use a digest-pinned PostgreSQL service')
  }

  if (!commandRuns('android').includes('make android-closed-beta')) {
    fail('android job does not run the production-path Android target')
  }
  if (!makefile.includes('./gradlew --no-daemon testDebugUnitTest assembleDebug')) {
    fail('Android target does not require both native unit tests and APK assembly')
  }
  for (const releaseBundleContract of [
    'android-release-bundle:',
    'GE_ANDROID_UPLOAD_STORE_FILE',
    './gradlew --no-daemon testDebugUnitTest bundleRelease',
  ]) {
    if (!makefile.includes(releaseBundleContract)) {
      fail(`Android signed-bundle contract is missing ${releaseBundleContract}`)
    }
  }

  const operationsRuns = commandRuns('operations')
  for (const command of ['make load-soak', 'make backup-restore-drill']) {
    if (!operationsRuns.includes(command)) {
      fail(`operations job does not run ${command}`)
    }
  }
  if (!operationsRuns.includes('artisan serve') || !operationsRuns.includes('--no-reload')) {
    fail('operations job does not run the bounded multi-worker API server')
  }

  const releaseGate = jobs['release-gate']
  const needs = Array.isArray(releaseGate?.needs) ? releaseGate.needs : []
  for (const jobName of ['verify', 'android', 'operations']) {
    if (!needs.includes(jobName)) {
      fail(`release-gate does not require ${jobName}`)
    }
  }
  if (releaseGate?.if !== '${{ always() }}') {
    fail('release-gate must run even when an upstream job fails or is skipped')
  }

  const releaseRuns = commandRuns('release-gate')
  for (const result of ['VERIFY_RESULT', 'ANDROID_RESULT', 'OPERATIONS_RESULT']) {
    const variable = '${' + result + '}'
    if (!releaseRuns.includes(`[ "${variable}" != success ]`)) {
      fail(`release-gate does not fail closed on ${result}`)
    }
  }

  for (const [jobName, job] of Object.entries(jobs)) {
    for (const step of job?.steps ?? []) {
      if (
        typeof step?.uses === 'string' &&
        !/^[^@]+@[0-9a-f]{40}$/.test(step.uses)
      ) {
        fail(`${jobName} uses an action that is not pinned to a full commit`)
      }
    }
  }
}

if (!process.exitCode) {
  process.stdout.write('Release gate contract is fail-closed.\n')
}
