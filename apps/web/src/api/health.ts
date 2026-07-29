import {
  getLiveness,
  type LivenessResponse,
} from '@global-english/contracts'

type GeneratedLivenessOptions = NonNullable<
  Parameters<typeof getLiveness>[0]
>

export type LivenessRequestOptions = Pick<
  GeneratedLivenessOptions,
  'baseUrl' | 'fetch' | 'headers' | 'signal'
>

export class LivenessUnavailableError extends Error {
  constructor() {
    super('The API liveness check could not be completed.')
    this.name = 'LivenessUnavailableError'
  }
}

export async function fetchLiveness(
  options: LivenessRequestOptions = {},
): Promise<LivenessResponse> {
  const result = await getLiveness({
    ...options,
    throwOnError: false,
  })

  if (result.data === undefined) {
    throw new LivenessUnavailableError()
  }

  return result.data
}
