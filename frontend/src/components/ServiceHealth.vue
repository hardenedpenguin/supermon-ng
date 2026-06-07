<template>
  <div v-if="open" class="service-health-modal" @click="closeModal">
    <div class="service-health-content" @click.stop>
      <div class="service-health-header">
        <h3>Service Health</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="service-health-body">
        <div v-if="loading" class="loading">Checking services...</div>
        <div v-else-if="error" class="error">{{ error }}</div>
        <template v-else-if="health">
          <div :class="['overall', health.healthy ? 'ok' : 'warn']">
            {{ health.healthy ? 'All core services healthy' : 'One or more services need attention' }}
          </div>
          <table class="health-table">
            <thead>
              <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(check, key) in health.checks" :key="key">
                <td>{{ formatLabel(String(key)) }}</td>
                <td>
                  <span :class="['badge', check.ok ? 'ok' : 'bad']">
                    {{ check.ok ? 'OK' : 'Issue' }}
                  </span>
                </td>
                <td class="details">
                  <span>{{ check.state }}</span>
                  <span v-if="check.enabled"> · {{ check.enabled }}</span>
                  <div v-if="check.hint" class="hint">{{ check.hint }}</div>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="checked-at">Checked {{ formatTime(health.checked_at) }}</p>
        </template>
      </div>

      <div class="service-health-footer">
        <button class="submit" @click="loadHealth">Refresh</button>
        <button class="submit" @click="closeModal">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { api } from '@/utils/api'
import type { AxiosErrorResponse } from '@/types/api'

type HealthCheck = {
  ok: boolean
  state: string
  enabled?: string | null
  hint?: string | null
}

type HealthData = {
  healthy: boolean
  checks: Record<string, HealthCheck>
  checked_at: string
}

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const loading = ref(false)
const error = ref<string | null>(null)
const health = ref<HealthData | null>(null)

const closeModal = () => emit('update:open', false)

const formatLabel = (key: string) =>
  key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

const formatTime = (iso: string) => {
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

const loadHealth = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/system/health')
    if (response.data.success) {
      health.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load health status'
    }
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Failed to load health status'
  } finally {
    loading.value = false
  }
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    loadHealth()
  }
})
</script>

<style scoped>
.service-health-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.service-health-content {
  background: var(--background-color);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: 92%;
  max-width: 720px;
  max-height: 85vh;
  overflow: auto;
}

.service-health-header,
.service-health-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}

.service-health-footer {
  border-bottom: none;
  border-top: 1px solid var(--border-color);
  gap: 8px;
  justify-content: flex-end;
}

.service-health-header h3 {
  margin: 0;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--text-color);
  cursor: pointer;
}

.service-health-body {
  padding: 20px;
}

.overall {
  padding: 10px 12px;
  border-radius: 6px;
  margin-bottom: 16px;
  font-weight: 600;
}

.overall.ok {
  background: rgba(40, 167, 69, 0.15);
  color: #1f6b34;
}

.overall.warn {
  background: rgba(255, 193, 7, 0.15);
  color: #7a5b00;
}

.health-table {
  width: 100%;
  border-collapse: collapse;
}

.health-table th,
.health-table td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--border-color);
  text-align: left;
  color: var(--text-color);
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
}

.badge.ok {
  background: rgba(40, 167, 69, 0.2);
  color: #1f6b34;
}

.badge.bad {
  background: rgba(220, 53, 69, 0.2);
  color: #842029;
}

.details .hint {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 4px;
}

.checked-at {
  margin-top: 12px;
  font-size: 12px;
  opacity: 0.8;
  color: var(--text-color);
}

.submit {
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border: 1px solid var(--border-color);
  padding: 5px 10px;
  border-radius: 15px;
  cursor: pointer;
  font-weight: bold;
}

.loading,
.error {
  text-align: center;
  color: var(--text-color);
}

.error {
  color: var(--error-color);
}
</style>
