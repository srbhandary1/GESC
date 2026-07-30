import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'
import { browserOfflineLearningStore } from '../offline/BrowserOfflineLearningStore'

export const defaultAuthentication = new WebSessionAuthenticationAdapter({
  baseUrl: '/api/v1',
  beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
})
