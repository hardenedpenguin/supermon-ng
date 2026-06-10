<template>
  <div
    v-if="show"
    class="update-banner"
    role="status"
    aria-live="polite"
  >
    <div class="update-banner__inner">
      <span class="update-banner__text">
        A new Supermon-ng release is available:
        <strong>v{{ latestLabel }}</strong>
        <span v-if="installedLabel" class="update-banner__muted">
          (this server: v{{ installedLabel }})
        </span>
      </span>
      <div class="update-banner__actions">
        <a
          v-if="releaseUrl"
          :href="releaseUrl"
          class="update-banner__link"
          target="_blank"
          rel="noopener noreferrer"
        >
          View release
        </a>
        <button
          type="button"
          class="update-banner__dismiss"
          aria-label="Dismiss update notice"
          @click="dismiss"
        >
          Dismiss
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/utils/api'
import type { UpdateCheckPayload } from '@/types'

const updateCheck = ref<UpdateCheckPayload | null>(null)
const dismissed = ref(false)

onMounted(async () => {
  try {
    const response = await api.get('/version/check')
    if (response.data.success && response.data.data) {
      updateCheck.value = response.data.data as UpdateCheckPayload
    }
  } catch {
    // Non-critical; banner stays hidden
  }
})

const storageKey = computed(() => {
  const tag = updateCheck.value?.latestTag ?? updateCheck.value?.latestVersion
  return tag ? `supermon-ng-dismiss-update:${tag}` : ''
})

function loadDismissed(): boolean {
  if (!storageKey.value || typeof localStorage === 'undefined') return false
  return localStorage.getItem(storageKey.value) === '1'
}

watch(
  updateCheck,
  () => {
    dismissed.value = loadDismissed()
  },
  { immediate: true }
)

function dismiss(): void {
  dismissed.value = true
  if (storageKey.value) {
    localStorage.setItem(storageKey.value, '1')
  }
}

const show = computed(() => {
  const u = updateCheck.value
  if (!u?.enabled || !u.updateAvailable || u.checkFailed) return false
  if (dismissed.value) return false
  return true
})

const latestLabel = computed(
  () => updateCheck.value?.latestVersion ?? updateCheck.value?.latestTag ?? 'new'
)

const installedLabel = computed(() => updateCheck.value?.installedVersion ?? '')

const releaseUrl = computed(() => updateCheck.value?.releaseUrl ?? null)
</script>

<style scoped>
.update-banner {
  background: linear-gradient(90deg, #5c4a1a 0%, #6b4e0a 100%);
  color: #fff8e1;
  border-bottom: 1px solid rgba(255, 193, 7, 0.45);
  font-size: 0.9rem;
  line-height: 1.35;
}

.update-banner__inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0.5rem 1rem;
}

.update-banner__text {
  flex: 1 1 auto;
}

.update-banner__muted {
  opacity: 0.85;
  font-weight: normal;
}

.update-banner__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.update-banner__link {
  color: #ffe082;
  text-decoration: underline;
}

.update-banner__link:hover {
  color: #fff;
}

.update-banner__dismiss {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: inherit;
  padding: 0.25rem 0.6rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.85rem;
}

.update-banner__dismiss:hover {
  background: rgba(0, 0, 0, 0.4);
}
</style>
