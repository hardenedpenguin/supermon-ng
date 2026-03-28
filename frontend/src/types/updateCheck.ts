/** GitHub latest-release check (from bootstrap `updateCheck`) */
export interface UpdateCheckPayload {
  enabled: boolean
  installedVersion: string | null
  updateAvailable: boolean
  latestVersion: string | null
  latestTag: string | null
  releaseUrl: string | null
  publishedAt: string | null
  checkedAt: string | null
  checkFailed: boolean
}
