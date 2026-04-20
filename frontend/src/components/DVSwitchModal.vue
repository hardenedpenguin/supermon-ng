<template>
  <div v-if="isVisible" class="dvswitch-modal-overlay" @click="closeModal">
    <div class="dvswitch-modal" @click.stop>
      <div class="dvswitch-modal-header">
        <h2>DVSwitch Mode Switcher</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="dvswitch-modal-content">
        <!-- Loading State -->
        <div v-if="loading" class="loading-message">
          Loading modes...
        </div>
        
        <!-- Error Message -->
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        
        <!-- Node and Mode Selection -->
        <div v-if="!loading && !error" class="dvswitch-section">
          <div v-if="presets.length" class="dvswitch-presets">
            <div class="dvswitch-presets-header">
              <span class="dvswitch-presets-title">Quick presets</span>
              <button
                type="button"
                class="preset-save-button"
                :disabled="!canSavePreset || switching"
                title="Save current node, mode, and talkgroup as a preset"
                @click="saveCurrentAsPreset"
              >
                Save preset
              </button>
            </div>
            <div class="dvswitch-presets-chips">
              <div
                v-for="p in presets"
                :key="p.id"
                class="preset-chip-wrap"
              >
                <button
                  type="button"
                  class="preset-chip"
                  :disabled="switching"
                  :title="presetTooltip(p)"
                  @click="applyPreset(p)"
                >
                  {{ p.name }}
                </button>
                <button
                  type="button"
                  class="preset-go"
                  :disabled="switching"
                  title="Load this preset and apply it to the node now"
                  @click.stop="runPreset(p)"
                >
                  Go
                </button>
                <button
                  type="button"
                  class="preset-remove"
                  :disabled="switching"
                  aria-label="Remove preset"
                  @click.stop="removePreset(p.id)"
                >
                  &times;
                </button>
              </div>
            </div>
          </div>
          <p v-else class="dvswitch-presets-hint">
            Choose a node and mode, then use Save preset for one-click shortcuts.
            <button
              type="button"
              class="linklike"
              :disabled="!canSavePreset || switching"
              @click="saveCurrentAsPreset"
            >
              Save preset
            </button>
          </p>

          <!-- Node Selection -->
          <div class="dvswitch-input-section">
            <label for="dvswitch-node">Node:</label>
            <select 
              id="dvswitch-node"
              v-model="selectedNode" 
              @change="onNodeChange"
              :disabled="switching"
            >
              <option value="">Select a node</option>
              <option v-for="node in availableNodes" :key="node.id" :value="node.id">
                Node {{ node.id }} {{ node.system ? `(${node.system})` : '' }}
              </option>
            </select>
          </div>
          
          <!-- Mode Selection (only show after node is selected) -->
          <div v-if="selectedNode" class="dvswitch-input-section">
            <label for="dvswitch-mode">Mode:</label>
            <select 
              id="dvswitch-mode"
              v-model="selectedMode" 
              @change="onModeChange"
              :disabled="switching || loadingModes"
            >
              <option value="">{{ loadingModes ? 'Loading modes...' : 'Select a mode' }}</option>
              <option v-for="mode in modes" :key="mode.name" :value="mode.name">
                {{ mode.name }}
              </option>
            </select>
          </div>

          <!-- DMR / multi-network: choose BrandMeister vs TGIF (from YAML `network` on each row) -->
          <div v-if="selectedMode && networkFilterChoices.length >= 2" class="dvswitch-input-section">
            <label for="dvswitch-network">{{ networkFilterLabel }}:</label>
            <select
              id="dvswitch-network"
              v-model="selectedNetwork"
              @change="onNetworkFilterChange"
              :disabled="switching || loadingModes"
            >
              <option
                v-for="opt in networkFilterChoices"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
          
          <!-- Talkgroup Selection -->
          <div v-if="selectedMode" class="dvswitch-input-section">
            <label for="dvswitch-talkgroup">Talkgroup:</label>
            <select 
              id="dvswitch-talkgroup"
              v-model="selectedTalkgroup" 
              @change="onTalkgroupChange"
              :disabled="switching"
            >
              <option value="">Select a talkgroup</option>
              <option v-for="tg in filteredTalkgroups" :key="tg.tgid" :value="tg.tgid">
                {{ tg.alias }}<template v-if="(tg.network || '').trim()"> ({{ tg.network }})</template>
              </option>
              <option value="__CUSTOM__">Custom TG</option>
            </select>
            
            <!-- Custom Talkgroup Input (shown when Custom TG is selected) -->
            <div v-if="selectedTalkgroup === '__CUSTOM__'" class="custom-tg-input">
              <input
                id="custom-talkgroup"
                v-model="customTalkgroup"
                type="text"
                placeholder="Enter custom talkgroup ID"
                :disabled="switching"
                class="custom-tg-field"
              />
            </div>
            
            <!-- TGIF Network Note -->
            <div v-if="selectedMode === 'DMR' && effectiveTalkgroup" class="tgif-note">
              <small>ℹ️ Note: On some DMR networks (including TGIF), you may need a key-up (transmission) after tuning for the change to take effect on the network.</small>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="dvswitch-buttons">
            <button
              type="button"
              @click="applySelection"
              :disabled="!selectedNode || !selectedMode || switching"
              class="action-button action-button-primary"
              :title="applyButtonTitle"
            >
              {{ switching ? 'Applying…' : applyButtonLabel }}
            </button>
            
            <button 
              v-if="selectedNode && selectedMode && effectiveTalkgroup"
              type="button"
              @click="switchTalkgroup" 
              :disabled="!effectiveTalkgroup || switching"
              class="action-button"
              title="Tune only (no mode change). Use when you are already in this mode."
            >
              {{ switching ? 'Switching…' : 'Tune only' }}
            </button>
            
            <button type="button" @click="closeModal" class="cancel-button">Cancel</button>
          </div>
          
          <!-- Success Message -->
          <div v-if="successMessage" class="success-message">
            {{ successMessage }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '@/utils/api'

const STORAGE_PRESETS = 'supermon_dvswitch_presets_v1'
const STORAGE_LAST = 'supermon_dvswitch_last_v1'

interface Mode {
  name: string
  talkgroups: Talkgroup[]
}

interface Talkgroup {
  tgid: string
  alias: string
  /** Optional label (e.g. BrandMeister, TGIF) for filtering when a mode lists multiple networks. */
  network?: string
}

interface DvswitchNode {
  id: string
  host?: string
  system?: string
}

interface DvswitchPreset {
  id: string
  name: string
  nodeId: string
  mode: string
  talkgroup: string
  /** Set when presets were saved with a DMR/network filter active */
  network?: string
}

interface LastByNode {
  [nodeId: string]: { mode: string; talkgroup: string; network?: string }
}

const props = defineProps<{
  isVisible: boolean
  localNode?: string
}>()

const emit = defineEmits<{
  'update:isVisible': [value: boolean]
}>()

const availableNodes = ref<DvswitchNode[]>([])
const modes = ref<Mode[]>([])
const talkgroups = ref<Talkgroup[]>([])
const selectedNode = ref('')
const selectedMode = ref('')
const selectedTalkgroup = ref('')
const customTalkgroup = ref('')
/** When YAML supplies multiple `network` labels for one mode, user picks BM vs TGIF (etc.) before the TG list. */
const selectedNetwork = ref('')
const loading = ref(false)
const loadingModes = ref(false)
const switching = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const presets = ref<DvswitchPreset[]>([])
/** When true, the next modes load skips restoring last-used mode/TG (e.g. when applying a preset). */
const suppressRestoreOnce = ref(false)

function loadPresetsFromStorage(): DvswitchPreset[] {
  try {
    const raw = localStorage.getItem(STORAGE_PRESETS)
    if (!raw) return []
    const parsed = JSON.parse(raw) as unknown
    if (!Array.isArray(parsed)) return []
    return parsed
      .filter(
        (p): p is DvswitchPreset =>
          typeof p === 'object' &&
          p !== null &&
          typeof (p as DvswitchPreset).id === 'string' &&
          typeof (p as DvswitchPreset).name === 'string' &&
          typeof (p as DvswitchPreset).nodeId === 'string' &&
          typeof (p as DvswitchPreset).mode === 'string' &&
          typeof (p as DvswitchPreset).talkgroup === 'string'
      )
      .map((p) => ({
        ...p,
        talkgroup: p.talkgroup ?? '',
        network: typeof (p as DvswitchPreset).network === 'string' ? (p as DvswitchPreset).network : undefined,
      }))
  } catch {
    return []
  }
}

function persistPresets() {
  try {
    localStorage.setItem(STORAGE_PRESETS, JSON.stringify(presets.value))
  } catch {
    /* ignore quota */
  }
}

function loadLastByNode(): LastByNode {
  try {
    const raw = localStorage.getItem(STORAGE_LAST)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as unknown
    return typeof parsed === 'object' && parsed !== null ? (parsed as LastByNode) : {}
  } catch {
    return {}
  }
}

// Computed property to get the effective talkgroup (either from dropdown or custom input)
const effectiveTalkgroup = computed(() => {
  if (selectedTalkgroup.value === '__CUSTOM__') {
    return customTalkgroup.value.trim()
  }
  return selectedTalkgroup.value
})

type NetworkChoice = { value: string; label: string }

const networkFilterChoices = computed((): NetworkChoice[] => {
  const list = talkgroups.value
  const distinct = [
    ...new Set(list.map((t) => (t.network || '').trim()).filter(Boolean)),
  ].sort((a, b) => a.localeCompare(b))
  const hasUnlabeled = list.some((t) => !(t.network || '').trim())
  const choices: NetworkChoice[] = distinct.map((d) => ({ value: d, label: d }))
  if (hasUnlabeled && distinct.length > 0) {
    choices.push({ value: '__other__', label: 'Other' })
  }
  return choices.length >= 2 ? choices : []
})

const filteredTalkgroups = computed(() => {
  if (networkFilterChoices.value.length < 2) {
    return talkgroups.value
  }
  if (selectedNetwork.value === '__other__') {
    return talkgroups.value.filter((t) => !(t.network || '').trim())
  }
  if (selectedNetwork.value === '') {
    return talkgroups.value
  }
  return talkgroups.value.filter((t) => (t.network || '').trim() === selectedNetwork.value)
})

const networkFilterLabel = computed(() =>
  selectedMode.value === 'DMR' ? 'DMR network' : 'Network',
)

function syncNetworkDefault(preferred?: string) {
  const choices = networkFilterChoices.value
  if (choices.length < 2) {
    selectedNetwork.value = ''
    return
  }
  const p = (preferred || '').trim()
  if (p && choices.some((c) => c.value === p)) {
    selectedNetwork.value = p
    return
  }
  selectedNetwork.value = choices[0]?.value ?? ''
}

function persistLastForNode(nodeId: string, mode: string, talkgroup: string) {
  if (!nodeId || !mode) return
  try {
    const map = loadLastByNode()
    const entry: { mode: string; talkgroup: string; network?: string } = {
      mode,
      talkgroup: talkgroup.trim(),
    }
    if (networkFilterChoices.value.length >= 2 && selectedNetwork.value !== '') {
      entry.network = selectedNetwork.value
    }
    map[nodeId] = entry
    localStorage.setItem(STORAGE_LAST, JSON.stringify(map))
  } catch {
    /* ignore */
  }
}

const canSavePreset = computed(
  () => !!(selectedNode.value && selectedMode.value)
)

const applyButtonLabel = computed(() => {
  if (effectiveTalkgroup.value) return 'Apply mode & talkgroup'
  return 'Apply mode'
})

const applyButtonTitle = computed(() => {
  if (effectiveTalkgroup.value) {
    return 'Switch mode then tune in one step (single request to the server)'
  }
  return 'Switch to the selected mode'
})

function presetTooltip(p: DvswitchPreset): string {
  const net = p.network ? ` [${p.network}]` : ''
  const tg = p.talkgroup ? ` — ${p.talkgroup}` : ''
  return `Node ${p.nodeId}, ${p.mode}${net}${tg}`
}

function onNetworkFilterChange() {
  selectedTalkgroup.value = ''
  customTalkgroup.value = ''
}

const closeModal = () => {
  emit('update:isVisible', false)
  // Reset state when closing
  setTimeout(() => {
    selectedNode.value = ''
    selectedMode.value = ''
    selectedTalkgroup.value = ''
    customTalkgroup.value = ''
    selectedNetwork.value = ''
    modes.value = []
    talkgroups.value = []
    error.value = null
    successMessage.value = null
    suppressRestoreOnce.value = false
  }, 300)
}

async function fetchTalkgroupsForSelectedMode(preferredNetwork?: string): Promise<void> {
  if (!selectedMode.value || !selectedNode.value) {
    talkgroups.value = []
    syncNetworkDefault()
    return
  }
  loadingModes.value = true
  error.value = null
  try {
    const response = await api.get(
      `/dvswitch/node/${encodeURIComponent(selectedNode.value)}/mode/${encodeURIComponent(selectedMode.value)}/talkgroups`
    )
    if (response.data.success) {
      talkgroups.value = response.data.data || []
      syncNetworkDefault(preferredNetwork)
    } else {
      error.value = response.data.message || 'Failed to load talkgroups'
    }
  } catch (err: unknown) {
    error.value =
      (err as { response?: { data?: { message?: string } } }).response?.data?.message ||
      'Error loading talkgroups'
    console.error('Error loading talkgroups:', err)
  } finally {
    loadingModes.value = false
  }
}

function setTalkgroupFromString(tg: string) {
  const t = tg.trim()
  if (!t) {
    selectedTalkgroup.value = ''
    customTalkgroup.value = ''
    return
  }
  const match = talkgroups.value.find((x) => x.tgid === t)
  if (match) {
    selectedTalkgroup.value = match.tgid
    customTalkgroup.value = ''
  } else {
    selectedTalkgroup.value = '__CUSTOM__'
    customTalkgroup.value = t
  }
}

async function restoreLastForCurrentNode(): Promise<void> {
  const nodeId = selectedNode.value
  if (!nodeId) return
  const map = loadLastByNode()
  const last = map[nodeId]
  if (!last?.mode) return
  if (!modes.value.some((m) => m.name === last.mode)) return
  selectedMode.value = last.mode
  await fetchTalkgroupsForSelectedMode(last.network)
  setTalkgroupFromString(last.talkgroup || '')
}

async function applyPreset(p: DvswitchPreset) {
  error.value = null
  successMessage.value = null
  const nodeOk = availableNodes.value.some((n) => n.id === p.nodeId)
  if (!nodeOk) {
    error.value = `Preset "${p.name}" uses node ${p.nodeId}, which is not available.`
    return
  }
  suppressRestoreOnce.value = true
  selectedNode.value = p.nodeId
  await onNodeChange()
  if (!modes.value.some((m) => m.name === p.mode)) {
    error.value = `Preset "${p.name}" uses mode ${p.mode}, which is not on this node.`
    return
  }
  selectedMode.value = p.mode
  await fetchTalkgroupsForSelectedMode(p.network)
  setTalkgroupFromString(p.talkgroup || '')
}

function saveCurrentAsPreset() {
  if (!canSavePreset.value) return
  const name = window.prompt('Preset name (shown on the button)', selectedMode.value)
  if (name === null || name.trim() === '') return
  const id =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `p-${Date.now()}`
  const preset: DvswitchPreset = {
    id,
    name: name.trim(),
    nodeId: selectedNode.value,
    mode: selectedMode.value,
    talkgroup: effectiveTalkgroup.value,
  }
  if (networkFilterChoices.value.length >= 2 && selectedNetwork.value) {
    preset.network = selectedNetwork.value
  }
  presets.value = [...presets.value, preset]
  persistPresets()
  successMessage.value = `Saved preset "${name.trim()}"`
}

function removePreset(id: string) {
  presets.value = presets.value.filter((p) => p.id !== id)
  persistPresets()
}

async function runPreset(p: DvswitchPreset) {
  await applyPreset(p)
  if (error.value) return
  await applySelection()
}

const loadNodes = async () => {
  loading.value = true
  error.value = null
  presets.value = loadPresetsFromStorage()

  try {
    const response = await api.get('/dvswitch/nodes')
    if (response.data.success) {
      availableNodes.value = response.data.data || []
      if (availableNodes.value.length === 0) {
        error.value = 'No nodes with DVSwitch configured found.'
      } else if (availableNodes.value.length === 1) {
        // Auto-select the single node
        selectedNode.value = availableNodes.value[0].id
        // Automatically load modes for the selected node
        await onNodeChange()
      } else if (props.localNode) {
        // If a local node is provided and it's in the available nodes, select it
        const localNodeInList = availableNodes.value.find(node => node.id === props.localNode)
        if (localNodeInList) {
          selectedNode.value = localNodeInList.id
          await onNodeChange()
        }
      }
    } else {
      error.value = response.data.message || 'Failed to load nodes'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error loading nodes. Make sure DVSwitch is configured.'
    console.error('Error loading DVSwitch nodes:', err)
  } finally {
    loading.value = false
  }
}

const onNodeChange = async () => {
  // Reset mode and talkgroup when node changes
  selectedMode.value = ''
  selectedTalkgroup.value = ''
  customTalkgroup.value = ''
  selectedNetwork.value = ''
  modes.value = []
  talkgroups.value = []
  
  if (!selectedNode.value) {
    return
  }
  
  await loadModes()
}

const loadModes = async () => {
  if (!selectedNode.value) return
  
  loadingModes.value = true
  error.value = null
  
  try {
    const response = await api.get(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/modes`)
    if (response.data.success) {
      modes.value = response.data.data || []
      const skipRestore = suppressRestoreOnce.value
      if (skipRestore) {
        suppressRestoreOnce.value = false
      } else {
        await restoreLastForCurrentNode()
      }
    } else {
      error.value = response.data.message || 'Failed to load modes'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error loading modes. Make sure DVSwitch is configured for this node.'
    console.error('Error loading DVSwitch modes:', err)
  } finally {
    loadingModes.value = false
  }
}

const onModeChange = async () => {
  if (!selectedMode.value || !selectedNode.value) {
    talkgroups.value = []
    selectedTalkgroup.value = ''
    customTalkgroup.value = ''
    selectedNetwork.value = ''
    syncNetworkDefault()
    return
  }

  selectedTalkgroup.value = ''
  customTalkgroup.value = ''
  selectedNetwork.value = ''
  await fetchTalkgroupsForSelectedMode()
}

const onTalkgroupChange = () => {
  // Clear custom talkgroup when switching away from custom option
  if (selectedTalkgroup.value !== '__CUSTOM__') {
    customTalkgroup.value = ''
  }
}

const applySelection = async () => {
  if (!selectedMode.value || !selectedNode.value) return

  switching.value = true
  error.value = null
  successMessage.value = null

  try {
    const payload: { node: string; talkgroup?: string } = {
      node: selectedNode.value,
    }
    if (effectiveTalkgroup.value) {
      payload.talkgroup = effectiveTalkgroup.value
    }
    const response = await api.post(
      `/dvswitch/node/${encodeURIComponent(selectedNode.value)}/mode/${encodeURIComponent(selectedMode.value)}`,
      payload
    )
    if (response.data.success) {
      successMessage.value =
        response.data.data?.message ||
        `Applied mode ${selectedMode.value}${effectiveTalkgroup.value ? ` and talkgroup ${effectiveTalkgroup.value}` : ''} on node ${selectedNode.value}`
      if (response.data.data?.talkgroups) {
        talkgroups.value = response.data.data.talkgroups
        syncNetworkDefault(selectedNetwork.value)
      } else {
        await fetchTalkgroupsForSelectedMode(selectedNetwork.value)
      }
      persistLastForNode(selectedNode.value, selectedMode.value, effectiveTalkgroup.value)
    } else {
      error.value = response.data.message || 'Failed to apply'
    }
  } catch (err: unknown) {
    error.value =
      (err as { response?: { data?: { message?: string } } }).response?.data?.message ||
      'Error applying DVSwitch settings'
    console.error('Error applying DVSwitch:', err)
  } finally {
    switching.value = false
  }
}

const switchTalkgroup = async () => {
  const tgid = effectiveTalkgroup.value
  if (!tgid || !selectedNode.value) return
  
  switching.value = true
  error.value = null
  successMessage.value = null
  
  try {
    const encodedTgid = encodeURIComponent(tgid)
    const response = await api.post(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/tune/${encodedTgid}`, {
      node: selectedNode.value
    })
    if (response.data.success) {
      successMessage.value = response.data.data?.message || `Switched node ${selectedNode.value} to talkgroup: ${tgid}`
      persistLastForNode(selectedNode.value, selectedMode.value, tgid)
    } else {
      error.value = response.data.message || 'Failed to switch talkgroup'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error switching talkgroup'
    console.error('Error switching talkgroup:', err)
  } finally {
    switching.value = false
  }
}

// Load nodes when modal opens
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadNodes()
  }
})

watch(
  () => [selectedNetwork.value, filteredTalkgroups.value, selectedTalkgroup.value] as const,
  () => {
    if (!selectedTalkgroup.value || selectedTalkgroup.value === '__CUSTOM__') return
    if (!filteredTalkgroups.value.some((t) => t.tgid === selectedTalkgroup.value)) {
      selectedTalkgroup.value = ''
    }
  },
)

onMounted(() => {
  if (props.isVisible) {
    loadNodes()
  }
})
</script>

<style scoped>
.dvswitch-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.dvswitch-modal {
  background: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  color: var(--text-color);
}

.dvswitch-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--table-header-bg);
}

.dvswitch-modal-header h2 {
  margin: 0;
  font-size: 1.5em;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 2em;
  cursor: pointer;
  color: var(--text-color);
  padding: 0;
  width: 30px;
  height: 30px;
  line-height: 1;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.close-button:hover {
  opacity: 1;
  color: var(--primary-color);
}

.dvswitch-modal-content {
  padding: 20px;
}

.loading-message,
.error-message,
.success-message {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
  text-align: center;
}

.loading-message {
  background-color: var(--link-color);
  color: var(--background-color);
  opacity: 0.2;
}

.error-message {
  background-color: var(--error-color);
  color: var(--background-color);
}

.success-message {
  background-color: var(--success-color);
  color: var(--background-color);
}

.dvswitch-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.dvswitch-input-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dvswitch-input-section label {
  font-weight: bold;
  color: var(--text-color);
}

.dvswitch-input-section select {
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1em;
  background-color: var(--input-bg);
  color: var(--input-text);
}

.dvswitch-input-section select:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.6;
}

.custom-tg-input {
  margin-top: 8px;
}

.custom-tg-field {
  width: 100%;
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1em;
  background-color: var(--input-bg);
  color: var(--input-text);
}

.custom-tg-field:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.6;
}

.custom-tg-field:focus {
  outline: none;
  border-color: var(--primary-color);
}

.dvswitch-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.action-button,
.cancel-button {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-size: 1em;
  cursor: pointer;
  transition: background-color 0.2s, opacity 0.2s;
}

.action-button {
  background-color: var(--link-color);
  color: var(--background-color);
}

.action-button:hover:not(:disabled) {
  opacity: 0.9;
  filter: brightness(1.1);
}

.action-button:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.5;
}

.cancel-button {
  background-color: var(--border-color);
  color: var(--text-color);
}

.cancel-button:hover {
  background-color: var(--primary-color);
  color: var(--background-color);
}

.tgif-note {
  margin-top: 8px;
  padding: 8px 12px;
  background-color: var(--link-color);
  color: var(--background-color);
  border-radius: 4px;
  font-size: 0.9em;
  opacity: 0.9;
}

.tgif-note small {
  display: block;
  line-height: 1.4;
}

.dvswitch-presets {
  margin-bottom: 8px;
}

.dvswitch-presets-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.dvswitch-presets-title {
  font-weight: bold;
  color: var(--text-color);
}

.preset-save-button {
  padding: 6px 12px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: var(--input-bg);
  color: var(--text-color);
  font-size: 0.9em;
  cursor: pointer;
}

.preset-save-button:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.preset-save-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dvswitch-presets-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.preset-chip-wrap {
  display: inline-flex;
  align-items: stretch;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.preset-chip {
  padding: 6px 10px;
  border: none;
  background: var(--table-header-bg);
  color: var(--text-color);
  font-size: 0.9em;
  cursor: pointer;
}

.preset-chip:hover:not(:disabled) {
  background: var(--primary-color);
  color: var(--background-color);
}

.preset-chip:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.preset-go {
  padding: 6px 8px;
  border: none;
  border-left: 1px solid var(--border-color);
  background: var(--link-color);
  color: var(--background-color);
  font-size: 0.8em;
  font-weight: 600;
  cursor: pointer;
}

.preset-go:hover:not(:disabled) {
  filter: brightness(1.08);
}

.preset-go:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.preset-remove {
  padding: 0 8px;
  border: none;
  border-left: 1px solid var(--border-color);
  background: var(--border-color);
  color: var(--text-color);
  font-size: 1.1em;
  line-height: 1;
  cursor: pointer;
}

.preset-remove:hover:not(:disabled) {
  background: var(--error-color);
  color: var(--background-color);
}

.dvswitch-presets-hint {
  margin: 0 0 12px;
  font-size: 0.9em;
  color: var(--text-color);
  opacity: 0.9;
}

.linklike {
  margin-left: 6px;
  padding: 0;
  border: none;
  background: none;
  color: var(--link-color);
  text-decoration: underline;
  cursor: pointer;
  font-size: inherit;
}

.linklike:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-button-primary {
  font-weight: 600;
}
</style>

