<template>
  <div v-if="open" class="link-map-modal" @click="closeModal">
    <div class="link-map-content" @click.stop>
      <div class="link-map-header">
        <h3>Link Map</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="link-map-body">
        <p v-if="hubs.length === 0" class="empty">No monitored nodes with link data yet.</p>
        <div v-for="hub in hubs" :key="hub.id" class="hub-block">
          <div class="hub-node">{{ hub.id }}</div>
          <div class="spokes">
            <div
              v-for="(link, index) in hub.links"
              :key="`${hub.id}-${link.node}-${index}`"
              :class="['remote-node', modeClass(link.mode)]"
            >
              <strong>{{ link.node }}</strong>
              <span>{{ link.info || 'Unknown' }}</span>
              <small>{{ link.direction || '—' }} · {{ formatMode(link.mode) }}</small>
            </div>
            <div v-if="hub.links.length === 0" class="remote-node idle">No active links</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRealTimeStore } from '@/stores/realTime'
import type { ConnectedNode } from '@/types'

const props = defineProps<{
  open: boolean
  nodeIds?: string[]
}>()

const emit = defineEmits<{ 'update:open': [value: boolean] }>()
const realTimeStore = useRealTimeStore()

const closeModal = () => emit('update:open', false)

const hubs = computed(() => {
  const ids = props.nodeIds?.length
    ? props.nodeIds
    : realTimeStore.nodes.map((n) => String(n.id))

  return ids.map((id) => {
    const node = realTimeStore.getNodeById(id)
    const links = (node?.remote_nodes ?? []) as ConnectedNode[]
    return { id, links }
  })
})

const modeClass = (mode: string | number | undefined) => {
  const value = String(mode ?? '')
  if (value.includes('T')) return 'tx'
  if (value.includes('R')) return 'rx'
  return 'idle'
}

const formatMode = (mode: string | number | undefined) => {
  if (mode === undefined || mode === null || mode === '') return 'n/a'
  return String(mode)
}
</script>

<style scoped>
.link-map-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.link-map-content {
  background: var(--background-color);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: 92%;
  max-width: 900px;
  max-height: 85vh;
  overflow: auto;
}

.link-map-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}

.link-map-header h3 {
  margin: 0;
  color: var(--text-color);
}

.link-map-body {
  padding: 20px;
}

.hub-block {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 16px;
  margin-bottom: 24px;
  align-items: start;
}

.hub-node {
  background: var(--local-node-header);
  color: var(--local-node-header-text);
  border: 1px solid var(--local-node-border);
  border-radius: 50%;
  width: 96px;
  height: 96px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
  margin: 0 auto;
}

.spokes {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.remote-node {
  min-width: 140px;
  max-width: 220px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--table-bg);
  color: var(--text-color);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.remote-node.tx {
  background: var(--status-transmitting);
  color: var(--background-color);
}

.remote-node.rx {
  background: var(--status-receiving);
  color: var(--background-color);
}

.remote-node.idle {
  opacity: 0.75;
}

.empty {
  text-align: center;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--text-color);
  cursor: pointer;
}
</style>
