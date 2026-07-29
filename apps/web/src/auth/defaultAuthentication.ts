import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'

export const defaultAuthentication = new WebSessionAuthenticationAdapter({
  baseUrl: '/api/v1',
})
