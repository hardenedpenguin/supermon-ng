<template>
  <div
    v-if="visible"
    class="connection-status"
    :class="statusClass"
    role="status"
    aria-live="polite"
  >
    <span class="connection-status-label">{{ statusLabel }}</span>
    <span v-if="ageLabel" class="connection-status-age">{{ ageLabel }}</span>
    <button
      v-if="realTimeStore.error"
      type="button"
      class="connection-status-dismiss"
      @click="realTimeStore.clearError()"
      aria-label="Dismiss connection message"
    >
      ×
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRealTimeStore } from '@/stores/realTime'

const props = defineProps<{
  nodeIds: string[]
}>()

const realTimeStore = useRealTimeStore()
const now = ref(Date.now())
let tickTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  tickTimer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onUnmounted(() => {
  if (tickTimer) {
    clearInterval(tickTimer)
    tickTimer = null
  }
})

const primaryNodeId = computed(() => {
  const ids = props.nodeIds.filter(Boolean)
  if (ids.length > 0) {
    return String(ids[0])
  }
  if (realTimeStore.monitoringNodes.length > 0) {
    return String(realTimeStore.monitoringNodes[0])
  }
  return ''
})

const mode = computed(() => {
  const nodeId = primaryNodeId.value
  if (!nodeId) {
    return 'offline' as const
  }
  // wsMonitoringModes is reactive; webSocketService internal state is not.
  return realTimeStore.wsMonitoringModes[nodeId]
    ?? realTimeStore.getNodeMonitoringMode(nodeId)
})

const visible = computed(() => {
  return Boolean(primaryNodeId.value) || Boolean(realTimeStore.error)
})

const statusClass = computed(() => `connection-status--${mode.value}`)

const statusLabel = computed(() => {
  if (realTimeStore.error) {
    return realTimeStore.error
  }

  const node = primaryNodeId.value
  const prefix = node ? `Node ${node}: ` : ''

  switch (mode.value) {
    case 'live':
      return `${prefix}Live (WebSocket)`
    case 'connecting':
      return `${prefix}Connecting…`
    case 'polling':
      return `${prefix}Polling (AMI fallback)`
    default:
      return `${prefix}Offline`
  }
})

const ageLabel = computed(() => {
  if (!realTimeStore.lastUpdateTime || realTimeStore.error) {
    return ''
  }
  const seconds = Math.max(0, Math.floor((now.value - realTimeStore.lastUpdateTime) / 1000))
  if (seconds < 2) {
    return 'Updated just now'
  }
  if (seconds < 60) {
    return `Updated ${seconds}s ago`
  }
  const minutes = Math.floor(seconds / 60)
  return `Updated ${minutes}m ago`
})
</script>

<style scoped>
.connection-status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin: 0.35rem auto 0.5rem;
  padding: 0.35rem 0.75rem;
  max-width: 42rem;
  border-radius: 4px;
  font-size: 0.85rem;
  border: 1px solid transparent;
}

.connection-status--live {
  background: rgba(40, 167, 69, 0.15);
  border-color: rgba(40, 167, 69, 0.35);
  color: #1f6b34;
}

.connection-status--connecting,
.connection-status--polling {
  background: rgba(255, 193, 7, 0.15);
  border-color: rgba(255, 193, 7, 0.4);
  color: #7a5b00;
}

.connection-status--offline {
  background: rgba(220, 53, 69, 0.12);
  border-color: rgba(220, 53, 69, 0.35);
  color: #842029;
}

.connection-status-age {
  opacity: 0.85;
  font-size: 0.8rem;
}

.connection-status-dismiss {
  margin-left: 0.25rem;
  border: none;
  background: transparent;
  color: inherit;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0 0.2rem;
}
</style>
