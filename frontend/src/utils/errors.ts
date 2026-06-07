/** Extract a user-facing message from an API or network error. */
export function apiErrorMessage(err: unknown, fallback: string): string {
  if (err && typeof err === 'object') {
    const ax = err as {
      response?: { data?: { message?: string; error?: string } }
      message?: string
    }
    const data = ax.response?.data
    if (data?.message) {
      return data.message
    }
    if (data?.error) {
      return data.error
    }
    if (ax.message) {
      return ax.message
    }
  }
  return fallback
}
