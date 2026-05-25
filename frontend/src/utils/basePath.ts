/** Default subdirectory install path (must match AppBasePath::INSTALL_SUBDIR in PHP). */
export const INSTALL_SUBDIR = '/supermon-ng'

/**
 * Public URL prefix for Supermon-NG.
 * One release build works for both dedicated vhost (/) and subdirectory (/supermon-ng):
 * - Optional <meta name="app-base" content="..."> set from APP_BASE_PATH at install/update
 * - Otherwise inferred from the current browser URL
 */
export function viteBaseUrl(): string {
  const meta = document.querySelector('meta[name="app-base"]')?.getAttribute('content')
  // Meta present (including content="/") = configured at install; only use auto-detect when attribute is absent
  if (meta !== null) {
    return normalizeBaseUrl(meta)
  }
  return detectBaseFromLocation()
}

function normalizeBaseUrl(raw: string): string {
  const trimmed = raw.trim()
  if (trimmed === '' || trimmed === '/') {
    return '/'
  }
  return trimmed.endsWith('/') ? trimmed : `${trimmed}/`
}

function detectBaseFromLocation(): string {
  const path = window.location.pathname
  if (path === INSTALL_SUBDIR || path.startsWith(`${INSTALL_SUBDIR}/`)) {
    return `${INSTALL_SUBDIR}/`
  }
  return '/'
}

/** Path prefix without trailing slash; empty string at site root. */
export function appBasePath(): string {
  const base = viteBaseUrl()
  if (base === '/') {
    return ''
  }
  return base.replace(/\/+$/, '')
}

/** Join base prefix with a path segment for browser URLs. */
export function appUrl(path: string = ''): string {
  const segment = path.replace(/^\/+/, '')
  const prefix = appBasePath()
  if (!segment) {
    return prefix || '/'
  }
  return `${prefix}/${segment}`.replace(/\/+/g, '/')
}
