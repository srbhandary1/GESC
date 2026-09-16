import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { expect, test, type Page, type Route } from '@playwright/test'

const ids = {
  actor: '01J90000000000000000000005',
  auditActivated: '01J90000000000000000000006',
  auditReviewed: '01J90000000000000000000007',
  branch: '01J90000000000000000000011',
  challenge: '01J90000000000000000000012',
  cohort: '01J90000000000000000000021',
  contentDocument: '01J90000000000000000000031',
  contentDraft: '01J90000000000000000000032',
  contentGraph: '01J90000000000000000000033',
  contentProgram: '01J90000000000000000000034',
  contentRevision: '01J90000000000000000000035',
  contentReviewAppointment: '01J90000000000000000000036',
  enrollment: '01J90000000000000000000022',
  invitation: '01J90000000000000000000013',
  learner: '01J90000000000000000000020',
  membership: '01J90000000000000000000002',
  mediaAppointment: '01J90000000000000000000050',
  mediaAsset: '01J90000000000000000000040',
  mediaCleanUpload: '01J90000000000000000000046',
  mediaMetadata: '01J90000000000000000000041',
  mediaNewUpload: '01J90000000000000000000048',
  mediaRejectedUpload: '01J90000000000000000000047',
  mediaReviewEn: '01J90000000000000000000043',
  mediaReviewKn: '01J90000000000000000000044',
  mediaRights: '01J90000000000000000000045',
  mediaScan: '01J90000000000000000000049',
  mediaSource: '01J90000000000000000000051',
  mediaVariant: '01J90000000000000000000042',
  organization: '01J90000000000000000000003',
  publication: '01J90000000000000000000004',
  role: '01J90000000000000000000023',
  user: '01J90000000000000000000001',
  version: '01J90000000000000000000008',
} as const

test('organization People workspace uses one login for routine commands, conflict recovery, and one-time invitations', async ({
  page,
}) => {
  const api = await mockOrganizationApi(page)

  await page.goto('/#practice')

  const taskNavigation = page.getByRole('navigation', {
    name: message('operator.navigation.label'),
  })
  const peopleLink = taskNavigation.getByRole('link', {
    name: message('operator.navigation.people'),
  })
  await peopleLink.focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#operator-people$/u)

  const invitations = page.getByRole('article', {
    name: message('operator.people.invitations.title'),
  })
  await expect(
    invitations.getByRole('button', {
      name: message('operator.people.action.issue'),
    }),
  ).toBeVisible()

  const branches = page.getByRole('article', {
    name: message('operator.people.branches.title'),
  })
  await expect(branches.getByRole('alert')).toContainText(
    message('operator.panel.error'),
  )
  await branches
    .getByRole('button', { name: message('operator.retry') })
    .click()
  await expect(branches.getByText('Central Branch')).toBeVisible()

  await branches.getByLabel(message('operator.people.field.key')).fill('north')
  await branches.getByLabel(message('operator.people.field.name')).fill(
    'North Branch',
  )
  await branches
    .getByRole('button', { name: message('operator.people.action.create') })
    .click()
  await expect(branches.getByText('North Branch')).toBeVisible()
  expect(api.branchCommandIds).toHaveLength(1)

  await branches
    .getByRole('button', { name: message('operator.people.action.archive') })
    .click()
  await branches
    .getByRole('button', { name: message('operator.people.action.confirm') })
    .click()
  await expect(branches.getByRole('alert')).toContainText(
    message('operator.people.command.conflict'),
  )
  const readsBeforeReload = api.branchReads
  await branches
    .getByRole('button', { name: message('operator.people.command.reload') })
    .click()
  await expect.poll(() => api.branchReads).toBeGreaterThan(readsBeforeReload)

  await invitations
    .getByRole('button', { name: message('operator.people.action.issue') })
    .click()
  const activationCode = invitations.getByText('GE-ONE-TIME-E2E-CODE')
  await expect(activationCode).toHaveAttribute('dir', 'ltr')
  await invitations
    .getByRole('button', { name: message('operator.people.invitation.saved') })
    .click()
  await expect(activationCode).toHaveCount(0)
  await invitations
    .getByRole('button', { name: message('operator.people.action.issue') })
    .click()
  await expect(
    invitations.getByRole('heading', {
      name: message('operator.people.invitation.replay_title'),
    }),
  ).toBeVisible()

  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations).toEqual([])

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  expect(
    await page.evaluate(async () => (await indexedDB.databases()).length),
  ).toBe(0)
})

test('learner workspace keeps bounded administration tasks independent and secrets ephemeral', async ({
  page,
}) => {
  const api = await mockLearnerApi(page)

  await page.goto('/#practice')

  const taskNavigation = page.getByRole('navigation', {
    name: message('operator.navigation.label'),
  })
  const learnersLink = taskNavigation.getByRole('link', {
    name: message('operator.navigation.learners'),
  })
  await learnersLink.focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#operator-learners$/u)

  const directory = page.getByRole('article', {
    name: message('operator.learners.directory.title'),
  })
  const enrollments = page.getByRole('article', {
    name: message('operator.learners.enrollments.title'),
  })
  const invitations = page.getByRole('article', {
    name: message('operator.learners.invitations.title'),
  })

  await expect(directory.getByText('Asha Learner')).toBeVisible()
  await expect(enrollments).toContainText(
    message('operator.learners.enrollments.select_prompt'),
  )
  await expect(
    invitations.getByRole('button', {
      name: message('operator.people.action.issue'),
    }),
  ).toBeVisible()

  await directory
    .getByRole('button', {
      name: 'Asha Learner',
    })
    .click()
  await expect(
    directory.getByRole('heading', {
      name: message('operator.learners.detail.title'),
    }),
  ).toBeVisible()
  await expect(enrollments.getByRole('alert')).toContainText(
    message('operator.panel.error'),
  )
  await expect(
    invitations.getByRole('button', {
      name: message('operator.people.action.issue'),
    }),
  ).toBeVisible()

  await enrollments
    .getByRole('button', { name: message('operator.retry') })
    .click()
  await expect(
    enrollments.getByText(message('operator.learners.enrollments.enrollment')),
  ).toBeVisible()
  expect(api.enrollmentReads).toBe(2)

  const detail = directory.getByRole('region', {
    name: message('operator.learners.detail.title'),
  })
  await detail
    .getByLabel(message('operator.learners.field.goal'), {
      exact: true,
    })
    .selectOption('workplace-confidence')
  await detail
    .getByRole('button', {
      name: message('operator.learners.action.correct_goal'),
    })
    .click()
  await expect(
    directory.getByText(message('operator.learners.command.success')),
  ).toBeVisible()
  expect(api.goalCorrections).toEqual([
    {
      expected_version: 1,
      goal_key: 'workplace-confidence',
    },
  ])

  await invitations
    .getByRole('button', {
      name: message('operator.people.action.issue'),
    })
    .click()
  const activationCode = invitations.getByText('GE-LEARNER-E2E-ONLY-ONCE')
  await expect(activationCode).toHaveAttribute('dir', 'ltr')
  await invitations
    .getByRole('button', {
      name: message('operator.people.invitation.saved'),
    })
    .click()
  await expect(activationCode).toHaveCount(0)

  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations).toEqual([])

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  expect(
    await page.evaluate(async () => (await indexedDB.databases()).length),
  ).toBe(0)
})

test('Content Studio keeps governed resources independent through exact review and stale reload', async ({
  page,
}) => {
  const api = await mockContentStudioApi(page)

  await page.goto('/#operator-learning')

  const studio = page.getByRole('article', {
    name: message('operator.content.title'),
  })
  await expect(studio.getByText('Synthetic Content Workspace').first()).toBeVisible()
  await expect(
    studio.getByText('Hello from the exact English head.').first(),
  ).toBeVisible()

  const publicationPanel = studio
    .getByRole('heading', { name: message('operator.content.publications.title') })
    .locator('..')
  await expect(publicationPanel.getByRole('alert')).toContainText(
    message('operator.content.error'),
  )
  await publicationPanel
    .getByRole('button', { name: message('operator.retry') })
    .click()
  await expect(publicationPanel).toContainText(
    message('operator.content.publications.empty'),
  )
  expect(api.publicationReads).toBe(2)

  await studio.getByText(message('operator.content.graph.title')).click()
  const levels = studio.getByRole('group', {
    name: message('operator.content.graph.levels'),
  })
  await levels.getByRole('button', { name: message('operator.content.add') }).click()
  const unsavedKey = levels.getByLabel(message('operator.content.field.key'))
  await unsavedKey.fill('unsaved-a1')
  await studio
    .getByRole('button', { name: message('operator.content.graph.save') })
    .click()
  await expect(studio).toContainText(message('operator.content.command.conflict'))
  await expect(unsavedKey).toHaveValue('unsaved-a1')
  expect(api.workspaceReads).toBeGreaterThan(1)

  await studio
    .getByRole('button', { name: message('operator.content.review.approve') })
    .click()
  await studio
    .getByRole('button', {
      name: message('operator.content.review.confirm_action'),
    })
    .click()
  await expect.poll(() => api.reviewDecisions.length).toBe(1)
  expect(api.reviewDecisions[0]).toMatchObject({
    authority_appointment_id: ids.contentReviewAppointment,
    curriculum_graph_revision_id: ids.contentGraph,
    decision: 'approved',
    graph_hash: 'b'.repeat(64),
    rationale_key: 'meets_standard',
  })

  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations).toEqual([])

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    ))
    .toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  expect(await page.evaluate(async () => (await indexedDB.databases()).length)).toBe(0)
})

test('Media Library keeps private upload, quarantine, rights, and attachment evidence exact', async ({
  page,
}) => {
  const api = await mockMediaLibraryApi(page)

  await page.goto('/#operator-learning')

  const library = page.getByRole('article', {
    name: message('operator.media.title'),
  })
  await expect(library.getByText('Clean quarantine candidate')).toBeVisible()
  const assets = library
    .getByRole('heading', { name: message('operator.media.assets') })
    .locator('..')
  await expect(assets.getByRole('alert')).toContainText(
    message('operator.media.error'),
  )
  await assets
    .getByRole('button', { name: message('operator.retry') })
    .click()
  await expect(assets.getByText('Foundation greeting')).toBeVisible()
  expect(api.assetReads).toBe(2)

  await library
    .getByRole('button', { name: /Rejected scan candidate/iu })
    .click()
  const uploadDetail = library.getByRole('region', {
    name: message('operator.media.upload.detail'),
  })
  await expect(uploadDetail).toContainText('scan_failed')
  await expect(
    uploadDetail.getByRole('button', {
      name: message('operator.media.quarantine.accept'),
    }),
  ).toHaveCount(0)

  await library
    .getByRole('button', { name: /Clean quarantine candidate/iu })
    .click()
  await uploadDetail
    .getByRole('button', { name: message('operator.media.preview.load') })
    .click()
  await expect(uploadDetail.locator('audio')).toBeVisible()
  expect(api.previewRequests).toBe(1)

  const uploader = library
    .locator('details')
    .filter({ hasText: message('operator.media.upload.start') })
    .first()
  await uploader.locator('summary').click()
  await uploader
    .getByLabel(message('operator.media.field.label'))
    .fill('Resumable private greeting')
  await uploader
    .getByLabel(message('operator.media.field.file'))
    .setInputFiles({
      buffer: Buffer.from('RIFF0000WAVEfmt resumable-e2e-audio'),
      mimeType: 'audio/wav',
      name: 'resumable.wav',
    })
  await uploader
    .getByRole('button', { name: message('operator.media.upload.start') })
    .click()
  await expect(uploader.getByRole('alert')).toContainText(
    message('operator.media.command.error'),
  )
  expect(api.startRequests).toBe(1)
  expect(api.partRequests).toBe(1)
  await uploader
    .getByRole('button', { name: message('operator.media.upload.resume') })
    .click()
  await expect(uploader.getByRole('status')).toContainText(
    message('operator.media.command.success'),
  )
  expect(api.startRequests).toBe(1)
  expect(api.partRequests).toBe(2)
  expect(api.finalizeRequests).toBe(1)
  expect(api.partCommandIds[0]).toBe(api.partCommandIds[1])

  await assets
    .getByRole('button', { name: /Foundation greeting/iu })
    .click()
  const assetDetail = library.getByRole('region', {
    name: message('operator.media.asset.detail'),
  })
  const rights = assetDetail
    .locator('details')
    .filter({ hasText: message('operator.media.rights.title') })
  await rights.locator('summary').click()
  await rights
    .getByLabel(message('operator.media.field.program'))
    .fill(ids.contentProgram)
  await rights
    .getByLabel(message('operator.media.field.appointment'))
    .fill(ids.mediaAppointment)
  await rights
    .getByLabel(message('operator.media.rights.confirm'))
    .check()
  await rights
    .getByRole('button', { name: message('operator.media.rights.record') })
    .click()
  await expect(rights.getByRole('alert')).toContainText(
    message('operator.media.command.conflict'),
  )
  await expect(
    rights.getByLabel(message('operator.media.field.program')),
  ).toHaveValue(ids.contentProgram)
  expect(api.rightsBodies).toHaveLength(1)
  expect(api.rightsBodies[0]).toMatchObject({
    evidence_hash: 'f'.repeat(64),
    license_or_contract_reference: 'APP-0044',
    media_asset_id: ids.mediaAsset,
    provenance_reference: 'APP-0044',
    selected_asset_hash: 'a'.repeat(64),
  })

  const attachment = assetDetail
    .locator('details')
    .filter({ hasText: message('operator.media.attachment.title') })
  await attachment.locator('summary').click()
  await attachment
    .getByLabel(message('operator.media.field.program'))
    .fill(ids.contentProgram)
  await attachment
    .getByLabel(message('operator.media.attachment.version'))
    .fill(ids.version)
  await attachment
    .getByLabel(message('operator.media.attachment.source'))
    .fill(ids.mediaSource)
  await attachment
    .getByLabel(message('operator.media.attachment.english_review'))
    .selectOption(ids.mediaReviewEn)
  await attachment
    .getByLabel(message('operator.media.attachment.kannada_review'))
    .selectOption(ids.mediaReviewKn)
  await attachment
    .getByLabel(message('operator.media.attachment.rights'))
    .selectOption(ids.mediaRights)
  await attachment
    .getByLabel(message('operator.media.attachment.confirm'))
    .check()
  await attachment
    .getByRole('button', { name: message('operator.media.attachment.record') })
    .click()
  await expect.poll(() => api.attachmentBodies.length).toBe(1)
  expect(api.attachmentBodies[0]).toEqual({
    curriculum_version_id: ids.version,
    english_review_id: ids.mediaReviewEn,
    expected_lifecycle_version: 1,
    expected_workspace_version: 1,
    kannada_review_id: ids.mediaReviewKn,
    media_rights_revision_id: ids.mediaRights,
    metadata_content_hash: 'c'.repeat(64),
    metadata_revision_id: ids.mediaMetadata,
    metadata_variant_id: ids.mediaVariant,
    program_id: ids.contentProgram,
    source_revision_id: ids.mediaSource,
    usage_key: 'model_audio',
    variant_content_hash: 'd'.repeat(64),
  })

  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations).toEqual([])

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    ))
    .toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  expect(await page.evaluate(async () => (await indexedDB.databases()).length)).toBe(0)
})

test('permission-aware operator shell keeps safe tasks available independently', async ({
  page,
}) => {
  const api = await mockOperatorApi(page)

  await page.goto('/#practice')

  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('operator.shell.title'),
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('navigation', {
      name: message('shell.navigation_label'),
    }),
  ).toHaveCount(0)

  const taskNavigation = page.getByRole('navigation', {
    name: message('operator.navigation.label'),
  })
  await expect(taskNavigation.getByRole('link')).toHaveCount(4)
  await expect(
    taskNavigation.getByRole('link', {
      name: message('operator.navigation.learning'),
    }),
  ).toBeVisible()
  const search = page.locator('.operator-search')
  await expect(search).toBeVisible()
  await search
    .getByRole('searchbox', {
      name: message('operator.search.label'),
    })
    .fill('draft')
  const versionResult = search.getByRole('link', {
    name: /Curriculum version Synthetic Foundation Preview/u,
  })
  await expect(versionResult).toBeVisible()
  await search
    .getByRole('button', { name: message('operator.load_more') })
    .click()
  await expect(
    search.getByText(
      'synthetic-foundation-preview / draft-1 · #1',
      { exact: true },
    ),
  ).toBeVisible()
  expect(api.searchRequests).toEqual([
    { cursor: null, query: 'draft' },
    { cursor: 'search-cursor', query: 'draft' },
  ])

  await versionResult.focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#operator-learning$/u)
  await expect(
    page.getByText('#1 · head', { exact: true }),
  ).toBeVisible()

  await taskNavigation
    .getByRole('link', {
      name: message('operator.navigation.operations'),
    })
    .focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#operator-operations$/u)

  const supportPanel = page
    .getByRole('heading', {
      name: message('operator.support_diagnostics.title'),
    })
    .locator('..')
  await expect(
    supportPanel.getByText(message('operator.panel.error')),
  ).toBeVisible()
  await supportPanel
    .getByRole('button', { name: message('operator.retry') })
    .click()
  await expect(supportPanel.getByText('stuck_in_progress')).toBeVisible()
  expect(api.supportRequests).toBe(2)

  await taskNavigation
    .getByRole('link', {
      name: message('operator.navigation.safety'),
    })
    .focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#operator-safety$/u)

  const auditPanel = page
    .getByRole('heading', { name: message('operator.audit.title') })
    .locator('..')
  await auditPanel
    .getByRole('button', {
      name: /curriculum\.publication\.activated/u,
    })
    .click()
  await expect(auditPanel.getByText(ids.actor)).toBeVisible()
  await expect(
    auditPanel.getByText('11111111-1111-4111-8111-111111111111'),
  ).toBeVisible()
  await auditPanel
    .getByRole('button', { name: message('operator.load_more') })
    .click()
  await expect(
    auditPanel.getByRole('button', {
      name: /curriculum\.content_review\.recorded/u,
    }),
  ).toBeVisible()
  expect(api.auditCursors).toEqual([null, 'audit-cursor'])

  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations).toEqual([])

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations).toEqual([])

  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  expect(
    await page.evaluate(async () => (await indexedDB.databases()).length),
  ).toBe(0)
})

interface MockOperatorState {
  readonly auditCursors: Array<string | null>
  readonly searchRequests: Array<{
    readonly cursor: string | null
    readonly query: string | null
  }>
  supportRequests: number
}

interface MockOrganizationState {
  readonly branchCommandIds: Array<string | undefined>
  branchReads: number
}

interface MockLearnerState {
  enrollmentReads: number
  readonly goalCorrections: Array<Readonly<Record<string, unknown>>>
}

interface MockContentStudioState {
  publicationReads: number
  readonly reviewDecisions: Array<Readonly<Record<string, unknown>>>
  workspaceReads: number
}

interface MockMediaLibraryState {
  assetReads: number
  readonly attachmentBodies: Array<Readonly<Record<string, unknown>>>
  finalizeRequests: number
  readonly partCommandIds: Array<string | undefined>
  partRequests: number
  previewRequests: number
  readonly rightsBodies: Array<Readonly<Record<string, unknown>>>
  startRequests: number
}

async function mockOrganizationApi(page: Page): Promise<MockOrganizationState> {
  let branchCreated = false
  let invitationIssues = 0
  let mfaFresh = false
  const state: MockOrganizationState = {
    branchCommandIds: [],
    branchReads: 0,
  }

  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'organization-e2e-csrf-token',
    },
  ])
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())

    if (url.pathname === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'organization_admin',
          organization: {
            id: ids.organization,
            kind: 'institution',
            name: 'Hithaishi Learning Hub',
          },
          role_keys: ['institution-admin'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })

      return
    }

    if (url.pathname === '/api/v1/operator/overview') {
      await json(route, {
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'people'],
        organization: {
          id: ids.organization,
          name: 'Hithaishi Learning Hub',
          timezone: 'Asia/Kolkata',
        },
        panels: [
          'organization_settings',
          'branches',
          'institution_invites',
        ],
        search_available: false,
      })

      return
    }

    if (url.pathname === '/api/v1/operator/organization') {
      await json(route, {
        data: {
          allowed_actions: [],
          id: ids.organization,
          kind: 'institution',
          name: 'Hithaishi Learning Hub',
          status: 'active',
          timezone: 'Asia/Kolkata',
        },
      })

      return
    }

    if (
      url.pathname === '/api/v1/operator/branches' &&
      request.method() === 'GET'
    ) {
      state.branchReads += 1

      if (state.branchReads === 1) {
        await json(route, { message_key: 'problem.unavailable' }, 503)
      } else {
        await json(route, {
          allowed_actions: ['branch.create'],
          data: [
            branchRecord(),
            ...(branchCreated
              ? [
                  branchRecord({
                    allowed_actions: [],
                    id: '01J90000000000000000000014',
                    key: 'north',
                    name: 'North Branch',
                  }),
                ]
              : []),
          ],
          next_cursor: null,
        })
      }

      return
    }

    if (
      url.pathname === '/api/v1/operator/branches' &&
      request.method() === 'POST'
    ) {
      state.branchCommandIds.push(request.headers()['idempotency-key'])
      branchCreated = true
      await json(
        route,
        {
          data: branchRecord({
            allowed_actions: [],
            id: '01J90000000000000000000014',
            key: 'north',
            name: 'North Branch',
          }),
          replayed: false,
        },
        201,
      )

      return
    }

    if (
      url.pathname ===
      `/api/v1/operator/branches/${ids.branch}/archivals`
    ) {
      await json(
        route,
        {
          message_key: 'problem.organization_administration.stale_state',
        },
        409,
      )

      return
    }

    if (url.pathname === '/api/v1/operator/institution-invites') {
      if (request.method() === 'GET') {
        await json(route, {
          allowed_actions: ['invitation.issue'],
          data: [],
          next_cursor: null,
        })
      } else {
        invitationIssues += 1
        await json(
          route,
          {
            activation_code:
              invitationIssues === 1 ? 'GE-ONE-TIME-E2E-CODE' : null,
            data: invitationRecord(),
            replayed: invitationIssues !== 1,
          },
          invitationIssues === 1 ? 201 : 200,
        )
      }

      return
    }

    if (
      url.pathname === '/api/v1/auth/password/status'
      && request.method() === 'GET'
    ) {
      await json(route, {
        credential_ready: true,
        mode: 'password_only',
        password_confirmed_at: mfaFresh ? '2026-08-09T10:00:30Z' : null,
        username: 'operator.one',
      })

      return
    }

    if (
      url.pathname === '/api/v1/auth/sessions'
      && request.method() === 'GET'
    ) {
      await json(route, {
        data: [
          {
            absolute_expires_at: '2026-08-12T10:00:00Z',
            created_at: '2026-08-11T10:00:00Z',
            current: true,
            expires_at: '2026-08-11T11:00:00Z',
            id: ids.challenge,
            last_activity_at: '2026-08-11T10:00:00Z',
            mfa_verified_at: null,
            mfa_verified_method: null,
            mfa_verified_scope: null,
            password_confirmed_at: mfaFresh
              ? '2026-08-09T10:00:30Z'
              : null,
            privileged: true,
          },
        ],
      })

      return
    }

    if (
      url.pathname === '/api/v1/auth/password/confirmations'
      && request.method() === 'POST'
    ) {
      mfaFresh = true
      await json(route, {
        confirmed_at: '2026-08-09T10:00:30Z',
        expires_at: '2026-08-09T10:05:30Z',
      })

      return
    }

    await json(route, { message_key: 'problem.not_found' }, 404)
  })

  return state
}

function branchRecord(
  overrides: Readonly<Record<string, unknown>> = {},
): Readonly<Record<string, unknown>> {
  return {
    allowed_actions: ['branch.archive'],
    created_at: '2026-08-09T09:00:00Z',
    id: ids.branch,
    key: 'central',
    name: 'Central Branch',
    status: 'active',
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
    ...overrides,
  }
}

function invitationRecord(): Readonly<Record<string, unknown>> {
  return {
    accepted_at: null,
    allowed_actions: ['invitation.revoke'],
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-12T10:00:00Z',
    id: ids.invitation,
    membership_type: 'trainer',
    status: 'pending',
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}

async function mockLearnerApi(page: Page): Promise<MockLearnerState> {
  let goalCorrected = false
  const state: MockLearnerState = {
    enrollmentReads: 0,
    goalCorrections: [],
  }

  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'learner-e2e-csrf-token',
    },
  ])
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())

    if (await fulfillPasswordSecurity(route, url.pathname)) {
      return
    }

    if (url.pathname === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'organization_admin',
          organization: {
            id: ids.organization,
            kind: 'institution',
            name: 'Hithaishi Learning Hub',
          },
          role_keys: ['institution-admin'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })

      return
    }

    if (url.pathname === '/api/v1/operator/overview') {
      await json(route, {
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'learners'],
        organization: {
          id: ids.organization,
          name: 'Hithaishi Learning Hub',
          timezone: 'Asia/Kolkata',
        },
        panels: [
          'learner_directory',
          'learner_enrollments',
          'learner_invitations',
        ],
        search_available: false,
      })

      return
    }

    if (
      url.pathname === '/api/v1/operator/learners' &&
      request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        choices: {
          cohorts: [learnerChoice(ids.cohort, 'conversation-a', 'Conversation A', 2)],
        },
        data: [learnerRecord(goalCorrected)],
        next_cursor: null,
      })

      return
    }

    if (
      url.pathname === `/api/v1/operator/learners/${ids.learner}` &&
      request.method() === 'GET'
    ) {
      await json(route, { data: learnerRecord(goalCorrected) })

      return
    }

    if (
      url.pathname ===
        `/api/v1/operator/learners/${ids.learner}/enrollments` &&
      request.method() === 'GET'
    ) {
      state.enrollmentReads += 1

      if (state.enrollmentReads === 1) {
        await json(route, { message_key: 'problem.unavailable' }, 503)
      } else {
        await json(route, {
          allowed_actions: [],
          data: [learnerEnrollmentRecord()],
          next_cursor: null,
        })
      }

      return
    }

    if (
      url.pathname ===
        `/api/v1/operator/learners/${ids.learner}/goal-corrections` &&
      request.method() === 'POST'
    ) {
      const body = request.postDataJSON() as Readonly<Record<string, unknown>>
      state.goalCorrections.push(body)
      goalCorrected = true
      await json(route, {
        data: learnerRecord(true),
        replayed: false,
      }, 201)

      return
    }

    if (url.pathname === '/api/v1/operator/learner-invitations') {
      if (request.method() === 'GET') {
        await json(route, {
          allowed_actions: ['learner_invitation.issue'],
          choices: {
            cohorts: [learnerChoice(ids.cohort, 'conversation-a', 'Conversation A', 2)],
            roles: [learnerChoice(ids.role, 'learner', 'Learner', 3)],
          },
          data: [],
          next_cursor: null,
        })
      } else {
        await json(route, {
          activation_code: 'GE-LEARNER-E2E-ONLY-ONCE',
          data: learnerInvitationRecord(),
          replayed: false,
        }, 201)
      }

      return
    }

    if (url.pathname === '/api/v1/auth/mfa' && request.method() === 'GET') {
      await json(route, {
        can_enroll: false,
        factor_id: null,
        factor_status: 'none',
        fresh_assertion_expires_at: null,
        fresh_assertion_scope: null,
        pending_expires_at: null,
        recovery_codes_remaining: 0,
        requires_support: false,
      })

      return
    }

    await json(route, { message_key: 'problem.not_found' }, 404)
  })

  return state
}

function learnerChoice(
  id: string,
  key: string,
  name: string,
  version: number,
): Readonly<Record<string, unknown>> {
  return { id, key, name, version }
}

function learnerRecord(goalCorrected: boolean): Readonly<Record<string, unknown>> {
  return {
    allowed_actions: [
      'learner.goal_correct',
      'learner.suspend',
      'learner_enrollment.create',
    ],
    attempt_count: 4,
    display_name: 'Asha Learner',
    ended_at: null,
    joined_at: '2026-08-01T10:00:00Z',
    latest_attempt_at: '2026-08-09T10:00:00Z',
    membership_id: ids.learner,
    profile: {
      adult_confirmed: true,
      current_goal_key: goalCorrected
        ? 'workplace-confidence'
        : 'everyday-conversation',
      current_goal_version: goalCorrected ? 2 : 1,
      id: '01J90000000000000000000024',
      onboarding_completed: true,
    },
    status: 'active',
    version: 2,
  }
}

function learnerEnrollmentRecord(): Readonly<Record<string, unknown>> {
  return {
    activated_at: null,
    allowed_actions: [],
    cohort: {
      ...learnerChoice(ids.cohort, 'conversation-a', 'Conversation A', 2),
      status: 'active',
    },
    curriculum_pin: null,
    ended_at: null,
    enrolled_at: '2026-08-09T09:00:00Z',
    id: ids.enrollment,
    learner_membership_id: ids.learner,
    status: 'pending_curriculum',
    trainers: [],
    version: 1,
  }
}

function learnerInvitationRecord(): Readonly<Record<string, unknown>> {
  return {
    accepted_at: null,
    allowed_actions: ['learner_invitation.revoke'],
    cohort: null,
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-10T10:00:00Z',
    id: ids.invitation,
    revoked_at: null,
    role: {
      ...learnerChoice(ids.role, 'learner', 'Learner', 3),
      status: 'active',
    },
    status: 'pending',
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}

async function mockContentStudioApi(page: Page): Promise<MockContentStudioState> {
  const state: MockContentStudioState = {
    publicationReads: 0,
    reviewDecisions: [],
    workspaceReads: 0,
  }

  await page.context().addCookies([{
    name: 'XSRF-TOKEN',
    url: 'http://127.0.0.1:4173',
    value: 'content-studio-e2e-csrf-token',
  }])
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())

    if (await fulfillPasswordSecurity(route, url.pathname)) {
      return
    }

    if (url.pathname === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'trainer',
          organization: {
            id: ids.organization,
            kind: 'institution',
            name: 'Hithaishi Learning Hub',
          },
          role_keys: ['content-governor'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })
      return
    }

    if (url.pathname === '/api/v1/operator/overview') {
      await json(route, {
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'learning'],
        organization: {
          id: ids.organization,
          name: 'Hithaishi Learning Hub',
          timezone: 'Asia/Kolkata',
        },
        panels: ['content_studio'],
        search_available: false,
      })
      return
    }

    if (
      url.pathname === '/api/v1/operator/content/workspaces'
      && request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        data: [contentWorkspaceSummary()],
        next_cursor: null,
      })
      return
    }

    if (
      url.pathname === `/api/v1/operator/content/workspaces/${ids.version}`
      && request.method() === 'GET'
    ) {
      state.workspaceReads += 1
      await json(route, { data: contentWorkspaceDetail() })
      return
    }

    if (
      url.pathname === `/api/v1/operator/content/workspaces/${ids.version}/preview`
    ) {
      await json(route, {
        data: {
          content_document_id: ids.contentDocument,
          content_type: 'instruction',
          document_key: 'greeting-instruction',
          english: {
            content_hash: 'a'.repeat(64),
            locale: 'en-IN',
            payload: {
              schema: 'global-english.governed-text.v1',
              text: 'Hello from the exact English head.',
            },
            revision_id: ids.contentRevision,
            revision_number: 1,
          },
          kannada: null,
          localization_requirement: 'kn_required',
          media: [],
          read_only: false,
        },
      })
      return
    }

    if (
      url.pathname === `/api/v1/operator/content/workspaces/${ids.version}/history`
    ) {
      await json(route, {
        data: [{
          actor_membership_id: ids.membership,
          event_kind: 'workspace.created',
          id: ids.contentDraft,
          occurred_at: '2026-08-09T09:00:00Z',
        }],
        next_cursor: null,
      })
      return
    }

    if (url.pathname === '/api/v1/operator/content/review-queues') {
      await json(route, {
        allowed_actions: [],
        data: url.searchParams.get('queue') === 'structure' ? [{
          allowed_actions: ['review_structure'],
          appointment_choices: [{
            authority_kind: 'english_academic_reviewer',
            id: ids.contentReviewAppointment,
          }],
          curriculum_version_id: ids.version,
          graph_hash: 'b'.repeat(64),
          graph_revision_id: ids.contentGraph,
          graph_revision_number: 1,
          latest_decision: null,
          program_id: ids.contentProgram,
          program_key: 'synthetic-content',
          version_key: 'draft-1',
        }] : [],
        next_cursor: null,
      })
      return
    }

    if (
      url.pathname === '/api/v1/operator/content/publications'
      && request.method() === 'GET'
    ) {
      state.publicationReads += 1
      if (state.publicationReads === 1) {
        await json(route, { message_key: 'problem.unavailable' }, 503)
      } else {
        await json(route, { allowed_actions: [], items: [], next_cursor: null })
      }
      return
    }

    if (
      url.pathname === `/api/v1/operator/content/workspaces/${ids.version}/graph-revisions`
      && request.method() === 'POST'
    ) {
      await json(route, {
        message_key: 'problem.content_workspace.stale_version',
      }, 409)
      return
    }

    if (
      url.pathname === '/api/v1/operator/content/reviews/structure'
      && request.method() === 'POST'
    ) {
      state.reviewDecisions.push(
        request.postDataJSON() as Readonly<Record<string, unknown>>,
      )
      await json(route, { curriculum_graph_review_id: ids.contentGraph }, 201)
      return
    }

    if (url.pathname === '/api/v1/auth/mfa') {
      await json(route, {
        can_enroll: true,
        factor_id: null,
        factor_status: 'none',
        fresh_assertion_expires_at: null,
        fresh_assertion_scope: null,
        pending_expires_at: null,
        recovery_codes_remaining: 0,
        requires_support: false,
      })
      return
    }

    await json(route, { message_key: 'problem.not_found' }, 404)
  })

  return state
}

function contentWorkspaceSummary(): Readonly<Record<string, unknown>> {
  return {
    active_publication_id: null,
    allowed_actions: ['edit_graph'],
    blocker_codes: ['workspace_incomplete'],
    current_draft_revision_id: ids.contentDraft,
    curriculum_version_id: ids.version,
    descriptor_version: 1,
    graph_hash: 'b'.repeat(64),
    is_complete: false,
    program_id: ids.contentProgram,
    program_internal_name: 'Synthetic Content Workspace',
    program_key: 'synthetic-content',
    publication_head_version: 0,
    publication_suspended: false,
    source_curriculum_publication_id: null,
    source_curriculum_version_id: null,
    state: 'draft',
    submitted_graph_revision_id: null,
    version_key: 'draft-1',
    workspace_version: 1,
  }
}

function contentWorkspaceDetail(): Readonly<Record<string, unknown>> {
  return {
    ...contentWorkspaceSummary(),
    allowed_actions: ['edit_graph'],
    appointment_choices: { english: [], kannada: [] },
    document_count: 1,
    documents: [{
      content_document_id: ids.contentDocument,
      content_type: 'instruction',
      document_key: 'greeting-instruction',
      learner_visible: true,
      localization_requirement: 'kn_required',
      payload_schema: 'global-english.governed-text.v1',
      source_content_hash: 'a'.repeat(64),
      source_revision_id: ids.contentRevision,
      source_revision_number: 1,
    }],
    documents_truncated: false,
    graph: {
      authored_at: '2026-08-09T09:00:00Z',
      authored_by_membership_id: ids.membership,
      blocker_codes: ['workspace_incomplete'],
      graph_hash: 'b'.repeat(64),
      is_complete: false,
      revision_id: ids.contentDraft,
      revision_number: 1,
      value: {
        curriculum_version_id: ids.version,
        delivery_bundles: [],
        items: [],
        lessons: [],
        levels: [],
        modules: [],
        objectives: [],
        organization_id: ids.organization,
        schema: 'global-english.curriculum-draft-graph.v2',
      },
    },
    readiness: {
      blocker_codes: ['workspace_incomplete'],
      ready: false,
    },
  }
}

async function mockMediaLibraryApi(page: Page): Promise<MockMediaLibraryState> {
  const state: MockMediaLibraryState = {
    assetReads: 0,
    attachmentBodies: [],
    finalizeRequests: 0,
    partCommandIds: [],
    partRequests: 0,
    previewRequests: 0,
    rightsBodies: [],
    startRequests: 0,
  }

  await page.context().addCookies([{
    name: 'XSRF-TOKEN',
    url: 'http://127.0.0.1:4173',
    value: 'media-library-e2e-csrf-token',
  }])
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())

    if (await fulfillPasswordSecurity(route, url.pathname)) {
      return
    }

    if (url.pathname === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'organization_admin',
          organization: {
            id: ids.organization,
            kind: 'institution',
            name: 'Hithaishi Learning Hub',
          },
          role_keys: ['governed-media-operator'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })
      return
    }

    if (url.pathname === '/api/v1/operator/overview') {
      await json(route, {
        generated_at: '2026-08-10T07:00:00+00:00',
        navigation: ['overview', 'learning'],
        organization: {
          id: ids.organization,
          name: 'Hithaishi Learning Hub',
          timezone: 'Asia/Kolkata',
        },
        panels: ['media_library'],
        search_available: false,
      })
      return
    }

    if (url.pathname === '/api/v1/operator/media/manifest') {
      await json(route, mediaManifest())
      return
    }

    if (
      url.pathname === '/api/v1/operator/media/assets'
      && request.method() === 'GET'
    ) {
      state.assetReads += 1
      if (state.assetReads === 1) {
        await json(route, { message_key: 'problem.unavailable' }, 503)
      } else {
        await json(route, {
          allowed_actions: [],
          data: [mediaAssetSummary()],
          next_cursor: null,
        })
      }
      return
    }

    if (
      url.pathname === '/api/v1/operator/media/uploads'
      && request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        data: [
          mediaUploadSummary(
            ids.mediaCleanUpload,
            'Clean quarantine candidate',
            'scan_clean',
          ),
          mediaUploadSummary(
            ids.mediaRejectedUpload,
            'Rejected scan candidate',
            'scan_failed',
          ),
        ],
        next_cursor: null,
      })
      return
    }

    if (
      url.pathname === `/api/v1/operator/media/assets/${ids.mediaAsset}`
      && request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        data: mediaAssetDetail(),
      })
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/assets/${ids.mediaAsset}/preview`
    ) {
      state.previewRequests += 1
      await route.fulfill({
        body: 'RIFF0000WAVEfmt governed-private-preview',
        headers: {
          'Cache-Control': 'private, no-store',
          'Content-Type': 'audio/wav',
          'X-Content-SHA256': 'a'.repeat(64),
        },
        status: 200,
      })
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/uploads/${ids.mediaCleanUpload}`
      && request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        data: mediaUploadDetail(
          ids.mediaCleanUpload,
          'Clean quarantine candidate',
          'scan_clean',
        ),
      })
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/uploads/${ids.mediaRejectedUpload}`
      && request.method() === 'GET'
    ) {
      await json(route, {
        allowed_actions: [],
        data: mediaUploadDetail(
          ids.mediaRejectedUpload,
          'Rejected scan candidate',
          'scan_failed',
        ),
      })
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/uploads/${ids.mediaCleanUpload}/preview`
    ) {
      state.previewRequests += 1
      await route.fulfill({
        body: 'RIFF0000WAVEfmt clean-quarantine-preview',
        headers: {
          'Cache-Control': 'private, no-store',
          'Content-Type': 'audio/wav',
          'X-Content-SHA256': 'b'.repeat(64),
        },
        status: 200,
      })
      return
    }

    if (
      url.pathname === '/api/v1/operator/media/uploads'
      && request.method() === 'POST'
    ) {
      state.startRequests += 1
      await json(route, mediaCommand({
        id: ids.mediaNewUpload,
        state: 'uploading',
        version: 1,
      }), 201)
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/uploads/${ids.mediaNewUpload}/parts`
      && request.method() === 'POST'
    ) {
      state.partRequests += 1
      state.partCommandIds.push(request.headers()['idempotency-key'])
      if (state.partRequests === 1) {
        await json(route, {
          message_key: 'problem.media_library.storage_failure',
        }, 409)
      } else {
        await json(route, mediaCommand({ upload_version: 2 }), 201)
      }
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/uploads/${ids.mediaNewUpload}/finalizations`
      && request.method() === 'POST'
    ) {
      state.finalizeRequests += 1
      await json(route, mediaCommand({
        upload_state: 'scan_clean',
        upload_version: 4,
      }), 201)
      return
    }

    if (
      url.pathname === '/api/v1/operator/media/rights-decisions'
      && request.method() === 'POST'
    ) {
      state.rightsBodies.push(
        request.postDataJSON() as Readonly<Record<string, unknown>>,
      )
      await json(route, {
        message_key: 'problem.media_library.stale_lifecycle',
      }, 409)
      return
    }

    if (
      url.pathname
        === `/api/v1/operator/media/assets/${ids.mediaAsset}/attachments`
      && request.method() === 'POST'
    ) {
      state.attachmentBodies.push(
        request.postDataJSON() as Readonly<Record<string, unknown>>,
      )
      await json(route, mediaCommand({
        media_attachment_event_id: ids.mediaSource,
      }), 201)
      return
    }

    if (url.pathname === '/api/v1/auth/mfa') {
      await json(route, {
        can_enroll: false,
        factor_id: ids.mediaAppointment,
        factor_status: 'active',
        fresh_assertion_expires_at: '2026-08-10T07:10:00Z',
        fresh_assertion_scope: 'privileged_actions',
        pending_expires_at: null,
        recovery_codes_remaining: 10,
        requires_support: false,
      })
      return
    }

    await json(route, { message_key: 'problem.not_found' }, 404)
  })

  return state
}

function mediaManifest(): Readonly<Record<string, unknown>> {
  return {
    allowed_actions: [
      'start_upload',
      'review_quarantine',
      'record_rights_decision',
      'attach_asset',
    ],
    generated_at: '2026-08-10T07:00:00+00:00',
    limits: {
      clean_review_expiry_days: 7,
      lesson_audio_maximum_bytes: 20_000_000,
      lesson_video_maximum_bytes: 20_000_000,
      model_audio_maximum_bytes: 10_000_000,
      part_size_bytes: 1_048_576,
      upload_expiry_hours: 24,
    },
    organization_id: ids.organization,
    rights_attestation: {
      approval_reference: 'APP-0044',
      creates_asset_approval: false,
      evidence_sha256: 'f'.repeat(64),
    },
    supported_kinds: ['model_audio', 'lesson_audio', 'lesson_video'],
  }
}

function mediaAssetSummary(): Readonly<Record<string, unknown>> {
  return {
    asset_hash: 'a'.repeat(64),
    asset_kind: 'model_audio',
    byte_size: 36,
    lifecycle_version: 1,
    media_asset_id: ids.mediaAsset,
    metadata_content_hash: 'c'.repeat(64),
    metadata_revision_id: ids.mediaMetadata,
    metadata_version: 1,
    mime_type: 'audio/wav',
    operator_label: 'Foundation greeting',
    predecessor_media_asset_id: null,
    selected_scan_id: ids.mediaScan,
    state: 'active',
    updated_at: '2026-08-10T07:00:00+00:00',
    uploaded_at: '2026-08-10T06:00:00+00:00',
  }
}

function mediaUploadSummary(
  id: string,
  safeLabel: string,
  state: 'scan_clean' | 'scan_failed',
): Readonly<Record<string, unknown>> {
  return {
    accepted_asset_id: null,
    asset_kind: 'model_audio',
    candidate_asset_id: ids.mediaAsset,
    decided_at: null,
    decision_reason_key: state === 'scan_failed'
      ? 'media_malware_detected'
      : null,
    expected_part_count: 1,
    expires_at: '2026-08-11T07:00:00+00:00',
    id,
    part_size_bytes: 1_048_576,
    received_part_count: 1,
    review_expires_at: state === 'scan_clean'
      ? '2026-08-17T07:00:00+00:00'
      : null,
    safe_label: safeLabel,
    selected_scan_id: ids.mediaScan,
    state,
    total_bytes: 36,
    updated_at: '2026-08-10T07:00:00+00:00',
    version: 4,
    whole_object_hash: 'b'.repeat(64),
  }
}

function mediaUploadDetail(
  id: string,
  safeLabel: string,
  state: 'scan_clean' | 'scan_failed',
): Readonly<Record<string, unknown>> {
  return {
    ...mediaUploadSummary(id, safeLabel, state),
    events: [{
      event_type: state === 'scan_clean' ? 'scan.clean' : 'scan.failed',
      id: ids.auditReviewed,
    }],
    parts: [{ id: ids.contentDraft, part_number: 1 }],
    scans: [{
      id: ids.mediaScan,
      inspection_outcome: 'passed',
      malware_outcome: state === 'scan_clean' ? 'clean' : 'infected',
    }],
  }
}

function mediaAssetDetail(): Readonly<Record<string, unknown>> {
  return {
    asset_hash: 'a'.repeat(64),
    asset_kind: 'model_audio',
    byte_size: 36,
    events: [],
    kannada_variant: {
      content_hash: 'd'.repeat(64),
      id: ids.mediaVariant,
      locale: 'kn-IN',
      revision_number: 1,
      source_content_hash: 'c'.repeat(64),
      text_alternative: null,
      transcript: 'ನಮಸ್ಕಾರ',
      translated_at: '2026-08-10T06:30:00+00:00',
      variant_version: 1,
    },
    lifecycle: {
      managed_at: '2026-08-10T06:00:00+00:00',
      predecessor_media_asset_id: null,
      retired_at: null,
      retirement_reason_key: null,
      selected_scan_id: ids.mediaScan,
      state: 'active',
      version: 1,
    },
    media_asset_id: ids.mediaAsset,
    metadata: {
      authored_at: '2026-08-10T06:20:00+00:00',
      content_hash: 'c'.repeat(64),
      id: ids.mediaMetadata,
      metadata_version: 1,
      operator_label: 'Foundation greeting',
      revision_number: 1,
      text_alternative: null,
      transcript: 'Hello',
    },
    metadata_reviews: [
      mediaReview(ids.mediaReviewEn, 'academic_en'),
      mediaReview(ids.mediaReviewKn, 'kannada'),
    ],
    mime_type: 'audio/wav',
    rights_revisions: [{
      confirmed_at: '2026-08-10T06:45:00+00:00',
      deletion_constraint_key: 'preserve_while_referenced',
      derivative_use_allowed: false,
      effective_at: '2026-08-10T06:45:00+00:00',
      evidence_hash: 'f'.repeat(64),
      evidence_status: 'approved',
      expires_at: null,
      id: ids.mediaRights,
      learner_delivery_allowed: true,
      license_or_contract_reference: 'APP-0044',
      permitted_release_channels: ['android', 'web'],
      permitted_territories: ['IN'],
      program_id: ids.contentProgram,
      provenance_reference: 'APP-0044',
      retention_policy_key: 'accepted_media',
      revision_number: 1,
      rights_basis_key: 'owner_attestation',
      rights_holder_reference: 'project-owner',
      sharing_allowed: false,
    }],
    scan: {
      id: ids.mediaScan,
      inspection_outcome: 'passed',
      malware_outcome: 'clean',
    },
    uploaded_at: '2026-08-10T06:00:00+00:00',
    usages: [],
  }
}

function mediaReview(
  id: string,
  kind: 'academic_en' | 'kannada',
): Readonly<Record<string, unknown>> {
  return {
    authority_appointment_id: ids.mediaAppointment,
    decision: 'approved',
    id,
    metadata_revision_id: ids.mediaMetadata,
    metadata_variant_id: kind === 'kannada' ? ids.mediaVariant : null,
    program_id: ids.contentProgram,
    rationale_key: null,
    review_kind: kind,
    reviewed_at: '2026-08-10T06:40:00+00:00',
    reviewer_membership_id: ids.membership,
  }
}

function mediaCommand(
  values: Readonly<Record<string, unknown>>,
): Readonly<Record<string, unknown>> {
  return {
    replayed: false,
    result_id: ids.mediaNewUpload,
    result_type: 'media_upload',
    ...values,
  }
}

async function mockOperatorApi(page: Page): Promise<MockOperatorState> {
  const state: MockOperatorState = {
    auditCursors: [],
    searchRequests: [],
    supportRequests: 0,
  }

  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'operator-e2e-csrf-token',
    },
  ])
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())

    if (await fulfillPasswordSecurity(route, url.pathname)) {
      return
    }

    if (url.pathname === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'trainer',
          organization: {
            id: ids.organization,
            kind: 'institution',
            name: 'Hithaishi Learning Hub',
          },
          role_keys: ['custom-governed-operator'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })

      return
    }

    if (url.pathname === '/api/v1/operator/overview') {
      await json(route, {
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'learning', 'operations', 'safety'],
        organization: {
          id: ids.organization,
          name: 'Hithaishi Learning Hub',
          timezone: 'Asia/Kolkata',
        },
        panels: [
          'pilot_analytics',
          'review_queue',
          'publish_status',
          'support_diagnostics',
          'audit',
        ],
        search_available: true,
      })

      return
    }

    if (url.pathname === '/api/v1/operator/pilot-analytics') {
      await json(route, {
        demonstrated_learning: {
          mastery_by_knowledge_state: { emerging: 1, mastered: 1 },
          mastery_state_count: 2,
          review_schedule_count: 1,
        },
        learning_metrics: {
          ai_fallback_count: 0,
          latency_sample_count: 0,
          quiz_retry_count: 0,
          review_return_attempted_count: 0,
          review_return_due_count: 0,
          session_cost_microunits_total: 0,
          speaking_turn_count: 0,
          stage_completion_count: 0,
        },
        distinction_note: 'usage_counts_are_not_demonstrated_learning',
        generated_at: '2026-08-09T10:00:00+00:00',
        usage: {
          conversation_session_count: 3,
          conversation_sessions_by_status: { ended: 3 },
          formative_attempt_count: 4,
          formative_attempts_by_status: { submitted: 4 },
          learner_membership_count: 2,
        },
      })

      return
    }

    if (url.pathname === '/api/v1/operator/content/drafts') {
      await json(route, {
        items: [
          {
            curriculum_version_id: ids.version,
            head_manifest_hash: null,
            head_publication_id: null,
            head_publication_number: null,
            head_version: 0,
            latest_english_decision: null,
            latest_kannada_decision: null,
            program_id: '01J90000000000000000000009',
            program_internal_name: 'Synthetic Foundation Preview',
            program_key: 'synthetic-foundation-preview',
            publication_status: 'unpublished',
            version_key: 'draft-1',
          },
        ],
      })

      return
    }

    if (url.pathname === '/api/v1/operator/content/publications') {
      await json(route, {
        items: [
          {
            created_at: '2026-08-09T10:00:00+00:00',
            curriculum_version_id: ids.version,
            head_version: 1,
            is_head: true,
            manifest_hash: 'a'.repeat(64),
            program_id: '01J90000000000000000000009',
            program_key: 'synthetic-foundation-preview',
            publication_id: ids.publication,
            publication_number: 1,
            version_key: 'draft-1',
          },
        ],
      })

      return
    }

    if (url.pathname === '/api/v1/operator/support/diagnostics') {
      state.supportRequests += 1

      if (state.supportRequests === 1) {
        await json(route, { title: 'Unavailable' }, 503)
      } else {
        await json(route, {
          generated_at: '2026-08-09T10:00:00+00:00',
          items: [
            {
              kind: 'stuck_in_progress',
              observed_at: '2026-08-09T09:00:00+00:00',
              status: 'in_progress',
              subject_id: '01J90000000000000000000010',
              subject_type: 'learner_attempt',
            },
          ],
        })
      }

      return
    }

    if (url.pathname === '/api/v1/operator/audit-events') {
      const cursor = url.searchParams.get('cursor')
      state.auditCursors.push(cursor)
      await json(
        route,
        cursor === null
          ? {
              items: [auditEvent(ids.auditActivated, 'activated')],
              next_cursor: 'audit-cursor',
            }
          : {
              items: [auditEvent(ids.auditReviewed, 'reviewed')],
              next_cursor: null,
            },
      )

      return
    }

    if (url.pathname === '/api/v1/operator/search') {
      const cursor = url.searchParams.get('cursor')
      state.searchRequests.push({
        cursor,
        query: url.searchParams.get('q'),
      })
      await json(
        route,
        cursor === null
          ? {
              generated_at: '2026-08-09T10:00:00+00:00',
              items: [
                {
                  id: ids.version,
                  kind: 'curriculum_version',
                  occurred_at: null,
                  primary_text: 'Synthetic Foundation Preview',
                  secondary_text:
                    'synthetic-foundation-preview / draft-1',
                  section: 'learning',
                },
              ],
              next_cursor: 'search-cursor',
            }
          : {
              generated_at: '2026-08-09T10:00:01+00:00',
              items: [
                {
                  id: ids.publication,
                  kind: 'curriculum_publication',
                  occurred_at: '2026-08-09T10:00:00+00:00',
                  primary_text: 'Synthetic Foundation Preview',
                  secondary_text:
                    'synthetic-foundation-preview / draft-1 · #1',
                  section: 'learning',
                },
              ],
              next_cursor: null,
            },
      )

      return
    }

    if (url.pathname === '/api/v1/auth/mfa') {
      await json(route, {
        can_enroll: true,
        factor_id: null,
        factor_status: 'none',
        fresh_assertion_expires_at: null,
        fresh_assertion_scope: null,
        pending_expires_at: null,
        recovery_codes_remaining: 0,
        requires_support: false,
      })

      return
    }

    await json(route, { title: 'Not found' }, 404)
  })

  return state
}

async function fulfillPasswordSecurity(
  route: Route,
  path: string,
): Promise<boolean> {
  if (path === '/api/v1/auth/password/status') {
    await json(route, {
      credential_ready: true,
      mode: 'password_only',
      password_confirmed_at: null,
      username: 'operator.one',
    })
    return true
  }
  if (path === '/api/v1/auth/sessions') {
    await json(route, { data: [] })
    return true
  }
  return false
}

function auditEvent(id: string, kind: 'activated' | 'reviewed') {
  return {
    action:
      kind === 'activated'
        ? 'curriculum.publication.activated'
        : 'curriculum.content_review.recorded',
    actor_membership_id: ids.actor,
    correlation_id: '11111111-1111-4111-8111-111111111111',
    id,
    occurred_at: '2026-08-09T10:00:00+00:00',
    subject_id: ids.publication,
    subject_type:
      kind === 'activated'
        ? 'curriculum_publication'
        : 'content_review',
  }
}

async function json(
  route: Route,
  body: unknown,
  status = 200,
): Promise<void> {
  await route.fulfill({
    headers: { 'Cache-Control': 'private, no-store' },
    json: body,
    status,
  })
}

function message(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
