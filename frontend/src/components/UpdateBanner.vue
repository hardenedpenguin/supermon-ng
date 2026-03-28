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
import { computed, ref, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import type { UpdateCheckPayload } from '@/types'

const appStore = useAppStore()
const dismissed = ref(false)

const updateCheck = computed(
  () => appStore.bootstrapData?.updateCheck as UpdateCheckPayload | undefined
)

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
  max-width: 1200px;
  margin: 0 auto;
  padding: 0.55rem 1rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
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
  font-weight: 600;
  text-decoration: underline;
}

.update-banner__link:hover {
  color: #fff8e1;
}

.update-banner__dismiss {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.35);
  color: #fff8e1;
  border-radius: 4px;
  padding: 0.25rem 0.6rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.update-banner__dismiss:hover {
  background: rgba(0, 0, 0, 0.4);
}
</style>
