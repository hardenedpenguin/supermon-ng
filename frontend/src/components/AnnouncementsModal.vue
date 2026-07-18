<template>
  <div v-if="isVisible" class="announce-modal-overlay" @click="closeModal">
    <div class="announce-modal" @click.stop>
      <div class="announce-modal-header">
        <h2>Announcements</h2>
        <button class="close-button" type="button" @click="closeModal">&times;</button>
      </div>

      <div class="announce-modal-content">
        <div v-if="loading" class="status-message loading">Loading...</div>
        <div v-if="error" class="status-message error">{{ error }}</div>
        <div v-if="success" class="status-message success">{{ success }}</div>

        <template v-if="!loading && !error">
          <div class="tab-bar">
            <button
              type="button"
              class="tab-button"
              :class="{ active: activeTab === 'playback' }"
              @click="activeTab = 'playback'"
            >
              Playback
            </button>
            <button
              type="button"
              class="tab-button"
              :class="{ active: activeTab === 'create' }"
              @click="activeTab = 'create'"
            >
              Create
            </button>
            <button
              v-if="canSchedule"
              type="button"
              class="tab-button"
              :class="{ active: activeTab === 'scheduled' }"
              @click="activeTab = 'scheduled'"
            >
              Scheduled
            </button>
          </div>

          <!-- Playback -->
          <section v-show="activeTab === 'playback'" class="tab-panel">
            <div class="field">
              <label for="play-node">Node</label>
              <select id="play-node" v-model="playForm.node" :disabled="busy">
                <option value="">Select node</option>
                <option v-for="node in nodes" :key="node.id" :value="node.id">{{ node.label }}</option>
              </select>
            </div>
            <div class="field-row">
              <div class="field">
                <label>Scope</label>
                <div class="radio-group">
                  <label><input v-model="playForm.scope" type="radio" value="local" :disabled="busy"> Local</label>
                  <label v-if="canGlobal">
                    <input v-model="playForm.scope" type="radio" value="global" :disabled="busy"> Global
                  </label>
                </div>
              </div>
              <div class="field">
                <label>Mode</label>
                <div class="radio-group">
                  <label><input v-model="playForm.mode" type="radio" value="polite" :disabled="busy"> Polite</label>
                  <label><input v-model="playForm.mode" type="radio" value="priority" :disabled="busy"> Priority</label>
                </div>
              </div>
            </div>
            <div class="field">
              <label for="play-file">Announcement</label>
              <select id="play-file" v-model="playForm.file" :disabled="busy">
                <option value="">Select announcement</option>
                <option v-for="file in files" :key="file.name" :value="file.name">{{ file.name }}</option>
              </select>
            </div>
            <div class="actions">
              <button type="button" class="submit action-primary" :disabled="busy || !canPlay" @click="playNow">
                Play now
              </button>
              <button
                type="button"
                class="submit action-danger"
                :disabled="busy || !playForm.file"
                @click="deleteFile(playForm.file)"
              >
                Delete
              </button>
            </div>
          </section>

          <!-- Create -->
          <section v-show="activeTab === 'create'" class="tab-panel">
            <h3 class="section-title">Upload audio</h3>
            <div class="field">
              <label for="upload-file">MP3 or WAV</label>
              <input id="upload-file" ref="uploadInput" type="file" accept=".mp3,.wav,audio/*" :disabled="busy">
            </div>
            <div class="field">
              <label for="upload-name">Name (optional)</label>
              <input id="upload-name" v-model="uploadName" type="text" placeholder="my-announcement" :disabled="busy">
            </div>
            <div class="actions">
              <button type="button" class="submit" :disabled="busy" @click="uploadFile">Upload &amp; install</button>
            </div>

            <h3 class="section-title">Text-to-speech</h3>
            <div class="field">
              <label for="tts-node">Node</label>
              <select id="tts-node" v-model="ttsForm.node" :disabled="busy">
                <option value="">Select node</option>
                <option v-for="node in nodes" :key="'tts-' + node.id" :value="node.id">{{ node.label }}</option>
              </select>
            </div>
            <div class="field">
              <label for="tts-text">Text</label>
              <textarea id="tts-text" v-model="ttsForm.text" rows="3" :disabled="busy" />
            </div>
            <div class="field-row">
              <div class="field">
                <label for="tts-name">File name</label>
                <input id="tts-name" v-model="ttsForm.name" type="text" placeholder="weather-alert" :disabled="busy">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="tts-voice-region">Region</label>
                <select id="tts-voice-region" v-model="voiceRegion" :disabled="busy || installingVoice">
                  <option v-for="region in voiceRegions" :key="region" :value="region">{{ region }}</option>
                </select>
              </div>
              <div class="field voice-show-all">
                <label class="checkbox-label" for="tts-show-all">
                  <input id="tts-show-all" v-model="showAllVoices" type="checkbox" :disabled="busy || installingVoice">
                  Show all voices in region
                </label>
                <span class="voice-count-hint">{{ voiceOptions.length }} shown</span>
              </div>
            </div>
            <div class="field">
              <label for="tts-voice">Voice</label>
              <select id="tts-voice" v-model="ttsForm.voice" :disabled="busy || installingVoice">
                <option v-for="voice in voiceOptions" :key="voice.file" :value="voice.file">
                  {{ voice.label }}{{ voice.installed ? '' : ' (not installed)' }}
                </option>
              </select>
              <p v-if="selectedVoice?.language" class="voice-hint">
                Match your text to {{ selectedVoice.language }}.
                Large downloads (~30–80&nbsp;MB) install on demand.
              </p>
              <button
                v-if="selectedVoice && !selectedVoice.installed && selectedVoice.catalog"
                type="button"
                class="submit voice-install-btn"
                :disabled="busy || installingVoice"
                @click="installSelectedVoice"
              >
                {{ installingVoice ? 'Installing…' : 'Install voice' }}
              </button>
            </div>
            <div class="actions">
              <button type="button" class="submit" :disabled="busy || !canCreateTts" @click="createTts">Generate &amp; install</button>
            </div>
          </section>

          <!-- Scheduled -->
          <section v-show="activeTab === 'scheduled' && canSchedule" class="tab-panel">
            <div v-if="!schedulingEnabled" class="status-message error">
              Scheduling is disabled in announcements.ini.
            </div>
            <h3 class="section-title">Add schedule</h3>
            <div class="field">
              <label for="sched-preset">Preset</label>
              <select id="sched-preset" v-model="schedulePreset" :disabled="busy" @change="applyPreset">
                <option value="custom">Custom</option>
                <option value="daily-7">Every day at 7:00</option>
                <option value="weekdays-7">Weekdays at 7:00</option>
                <option value="weekends-9">Weekends at 9:00</option>
                <option value="hourly">Hourly at :00</option>
              </select>
            </div>

            <div class="field-row cron-fields">
              <div class="field">
                <label for="sched-minute">Minute</label>
                <select id="sched-minute" v-model="scheduleForm.minute" :disabled="busy">
                  <option v-for="m in minuteOptions" :key="'m-' + m" :value="m">{{ m }}</option>
                  <option value="custom">Custom...</option>
                </select>
                <input
                  v-if="scheduleForm.minute === 'custom'"
                  v-model="scheduleForm.minuteCustom"
                  type="text"
                  placeholder="0-59, */15"
                  :disabled="busy"
                >
              </div>
              <div class="field">
                <label for="sched-hour">Hour</label>
                <select id="sched-hour" v-model="scheduleForm.hour" :disabled="busy">
                  <option v-for="h in hourOptions" :key="'h-' + h" :value="h">{{ h }}</option>
                  <option value="custom">Custom...</option>
                </select>
                <input
                  v-if="scheduleForm.hour === 'custom'"
                  v-model="scheduleForm.hourCustom"
                  type="text"
                  placeholder="7-20, */2"
                  :disabled="busy"
                >
              </div>
              <div class="field">
                <label for="sched-dom">Day of month</label>
                <select id="sched-dom" v-model="scheduleForm.dom" :disabled="busy">
                  <option value="*">Every day</option>
                  <option value="1">1st</option>
                  <option value="15">15th</option>
                  <option value="custom">Custom...</option>
                </select>
                <input
                  v-if="scheduleForm.dom === 'custom'"
                  v-model="scheduleForm.domCustom"
                  type="text"
                  placeholder="1,15"
                  :disabled="busy"
                >
              </div>
              <div class="field">
                <label for="sched-month">Month</label>
                <select id="sched-month" v-model="scheduleForm.month" :disabled="busy">
                  <option value="*">Every month</option>
                  <option v-for="m in 12" :key="'mo-' + m" :value="String(m)">{{ monthLabel(m) }}</option>
                </select>
              </div>
              <div class="field">
                <label for="sched-dow">Day of week</label>
                <select id="sched-dow" v-model="scheduleForm.dow" :disabled="busy">
                  <option value="*">Every day</option>
                  <option value="1-5">Mon–Fri</option>
                  <option value="0,6">Sat–Sun</option>
                  <option v-for="d in dowChoices" :key="'dow-' + d.value" :value="d.value">{{ d.label }}</option>
                </select>
              </div>
            </div>

            <div v-if="showWeekOfMonth" class="field">
              <label for="sched-week">Week of month</label>
              <select id="sched-week" v-model="scheduleForm.week" :disabled="busy">
                <option value="*">Every week</option>
                <option v-for="w in 5" :key="'w-' + w" :value="String(w)">{{ weekLabel(w) }}</option>
              </select>
            </div>

            <div class="field">
              <label for="sched-desc">Description</label>
              <input id="sched-desc" v-model="scheduleForm.description" type="text" maxlength="120" :disabled="busy">
            </div>
            <div class="field">
              <label for="sched-file">Announcement</label>
              <select id="sched-file" v-model="scheduleForm.file" :disabled="busy">
                <option value="">Select announcement</option>
                <option v-for="file in files" :key="'s-' + file.name" :value="file.name">{{ file.name }}</option>
              </select>
            </div>
            <div class="field">
              <label for="sched-node">Node</label>
              <select id="sched-node" v-model="scheduleForm.node" :disabled="busy">
                <option value="">Select node</option>
                <option v-for="node in nodes" :key="'sn-' + node.id" :value="node.id">{{ node.label }}</option>
              </select>
            </div>
            <div class="field-row">
              <div class="field">
                <label>Scope</label>
                <div class="radio-group">
                  <label><input v-model="scheduleForm.scope" type="radio" value="local" :disabled="busy"> Local</label>
                  <label v-if="canGlobal">
                    <input v-model="scheduleForm.scope" type="radio" value="global" :disabled="busy"> Global
                  </label>
                </div>
              </div>
              <div class="field">
                <label>Mode</label>
                <div class="radio-group">
                  <label><input v-model="scheduleForm.mode" type="radio" value="polite" :disabled="busy"> Polite</label>
                  <label><input v-model="scheduleForm.mode" type="radio" value="priority" :disabled="busy"> Priority</label>
                </div>
              </div>
            </div>

            <p class="schedule-preview">{{ schedulePreview }}</p>

            <div class="actions">
              <button type="button" class="submit action-primary" :disabled="busy || !canSaveSchedule" @click="saveSchedule">
                Save schedule
              </button>
            </div>

            <h3 class="section-title">Scheduled jobs</h3>
            <div v-if="!schedules.length" class="empty-hint">No scheduled announcements yet.</div>
            <div v-else class="schedule-table-wrap">
              <table class="schedule-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>File</th>
                    <th>Node</th>
                    <th>Scope</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="job in schedules" :key="job.id">
                    <td>{{ job.minute }} {{ job.hour }} {{ job.dom }} {{ job.month }} {{ job.dow }}</td>
                    <td>{{ job.file }}</td>
                    <td>{{ job.node }}</td>
                    <td>{{ job.scope }}</td>
                    <td>{{ job.description }}</td>
                    <td>{{ job.enabled ? 'Enabled' : 'Disabled' }}</td>
                    <td class="schedule-actions">
                      <button type="button" class="submit" :disabled="busy" @click="toggleSchedule(job)">
                        {{ job.enabled ? 'Disable' : 'Enable' }}
                      </button>
                      <button type="button" class="submit action-danger" :disabled="busy" @click="deleteSchedule(job.id)">
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '@/utils/api'
import { useAppStore } from '@/stores/app'

interface AnnounceFile {
  name: string
  size: number
  modified: number
}

interface AnnounceNode {
  id: string
  label: string
}

interface VoiceOption {
  id: string
  file: string
  label: string
  installed: boolean
  catalog: boolean
  region: string
  language: string
  locale: string
  quality: string
  curated: boolean
}

interface ScheduleJob {
  id: string
  enabled: boolean
  minute: string
  hour: string
  dom: string
  month: string
  dow: string
  node: string
  scope: string
  mode: string
  file: string
  description: string
}

const props = defineProps<{
  isVisible: boolean
  localNode?: string
}>()

const emit = defineEmits<{
  'update:isVisible': [value: boolean]
}>()

const appStore = useAppStore()

const loading = ref(false)
const busy = ref(false)
const installingVoice = ref(false)
const error = ref('')
const success = ref('')
const activeTab = ref<'playback' | 'create' | 'scheduled'>('playback')

const files = ref<AnnounceFile[]>([])
const nodes = ref<AnnounceNode[]>([])
const voices = ref<VoiceOption[]>([])
const voiceRegions = ref<string[]>([
  'Americas',
  'Europe',
  'Asia-Pacific',
  'Middle East & Africa',
  'Other',
])
const voiceRegion = ref('Americas')
const showAllVoices = ref(false)
const defaultVoice = ref('en_US-amy-low.onnx')
const schedules = ref<ScheduleJob[]>([])
const minuteOptions = ref<string[]>(['0', '15', '30', '45', '*'])
const hourOptions = ref<string[]>(['7', '7-20', '6-11', '12-17', '17-21', '*'])

const defaults = ref({ mode: 'polite', scope: 'local' })
const schedulingEnabled = ref(true)
const uploadInput = ref<HTMLInputElement | null>(null)
const uploadName = ref('')

const playForm = ref({
  node: '',
  scope: 'local',
  mode: 'polite',
  file: '',
})

const ttsForm = ref({
  node: '',
  text: '',
  name: '',
  voice: 'en_US-amy-low.onnx',
})

const schedulePreset = ref('custom')
const scheduleForm = ref({
  minute: '0',
  minuteCustom: '',
  hour: '7',
  hourCustom: '',
  dom: '*',
  domCustom: '',
  month: '*',
  dow: '*',
  week: '*',
  description: '',
  file: '',
  node: '',
  scope: 'local',
  mode: 'polite',
})

const dowChoices = [
  { value: '0', label: 'Sunday' },
  { value: '1', label: 'Monday' },
  { value: '2', label: 'Tuesday' },
  { value: '3', label: 'Wednesday' },
  { value: '4', label: 'Thursday' },
  { value: '5', label: 'Friday' },
  { value: '6', label: 'Saturday' },
]

const canGlobal = computed(() => appStore.hasPermission('ANNOUNCEGLOBALUSER'))
const canSchedule = computed(() => appStore.hasPermission('ANNOUNCESCHEDUSER'))

const canPlay = computed(() =>
  Boolean(playForm.value.node && playForm.value.file)
)

const canCreateTts = computed(() =>
  Boolean(
    ttsForm.value.node &&
    ttsForm.value.text.trim() &&
    ttsForm.value.name.trim() &&
    selectedVoice.value?.installed
  )
)

const voiceOptions = computed(() => {
  let list = voices.value.filter((voice) => {
    if (!voice.catalog) {
      return true
    }
    return voice.region === voiceRegion.value
  })

  if (!showAllVoices.value) {
    const curated = list.filter((voice) => !voice.catalog || voice.curated)
    if (curated.length > 0) {
      list = curated
    }
  }

  const selected = voices.value.find((voice) => voice.file === ttsForm.value.voice)
  if (selected && !list.some((voice) => voice.file === selected.file)) {
    list = [selected, ...list]
  }

  return [...list].sort((a, b) => {
    if (a.installed !== b.installed) {
      return a.installed ? -1 : 1
    }
    if (a.curated !== b.curated) {
      return a.curated ? -1 : 1
    }
    return a.label.localeCompare(b.label)
  })
})

const selectedVoice = computed(() =>
  voices.value.find((voice) => voice.file === ttsForm.value.voice) ?? null
)

function syncVoiceSelection() {
  if (voiceOptions.value.some((voice) => voice.file === ttsForm.value.voice)) {
    return
  }
  const installed = voiceOptions.value.find((voice) => voice.installed)
  const fallback = installed ?? voiceOptions.value[0]
  if (fallback) {
    ttsForm.value.voice = fallback.file
  }
}

const showWeekOfMonth = computed(() => /^[1-7]$/.test(scheduleForm.value.dow))

const resolvedMinute = computed(() =>
  scheduleForm.value.minute === 'custom' ? scheduleForm.value.minuteCustom : scheduleForm.value.minute
)
const resolvedHour = computed(() =>
  scheduleForm.value.hour === 'custom' ? scheduleForm.value.hourCustom : scheduleForm.value.hour
)
const resolvedDom = computed(() =>
  scheduleForm.value.dom === 'custom' ? scheduleForm.value.domCustom : scheduleForm.value.dom
)

const schedulePreview = computed(() => {
  const f = scheduleForm.value
  const file = f.file || '(no file)'
  const node = f.node || '(no node)'
  return `Play "${file}" on node ${node}, ${f.scope}, ${f.mode}, at ${resolvedMinute.value} ${resolvedHour.value} ${resolvedDom.value} ${f.month} ${f.dow}`
})

const canSaveSchedule = computed(() =>
  Boolean(
    schedulingEnabled.value &&
    scheduleForm.value.file &&
    scheduleForm.value.node &&
    scheduleForm.value.description.trim() &&
    resolvedMinute.value &&
    resolvedHour.value
  )
)

function monthLabel(m: number): string {
  return new Date(2000, m - 1, 1).toLocaleString(undefined, { month: 'short' })
}

function weekLabel(w: number): string {
  const suffix = ['', 'st', 'nd', 'rd', 'th', 'th']
  return `${w}${suffix[w] ?? 'th'} week`
}

function clearMessages() {
  error.value = ''
  success.value = ''
}

function closeModal() {
  emit('update:isVisible', false)
}

function resetForms() {
  const node = props.localNode || nodes.value[0]?.id || ''
  playForm.value = {
    node,
    scope: defaults.value.scope,
    mode: defaults.value.mode,
    file: files.value[0]?.name || '',
  }
  scheduleForm.value.node = node
  scheduleForm.value.scope = defaults.value.scope
  scheduleForm.value.mode = defaults.value.mode
  ttsForm.value.node = node
  ttsForm.value.voice = ttsForm.value.voice || 'en_US-amy-low.onnx'
}

async function loadVoices() {
  try {
    const response = await api.get('/announcements/voices')
    const data = response.data?.data
    voices.value = data?.voices ?? []
    if (Array.isArray(data?.regions) && data.regions.length) {
      voiceRegions.value = data.regions
    }
    if (data?.default) {
      defaultVoice.value = data.default
    }
    const defaultEntry = voices.value.find((voice) => voice.file === defaultVoice.value)
    if (defaultEntry?.region && voiceRegions.value.includes(defaultEntry.region)) {
      voiceRegion.value = defaultEntry.region
    }
    if (!voices.value.some((voice) => voice.file === ttsForm.value.voice)) {
      ttsForm.value.voice = defaultVoice.value
    }
    syncVoiceSelection()
  } catch {
    voices.value = []
  }
}

async function loadSchedules() {
  if (!canSchedule.value) {
    return
  }
  const schedResponse = await api.get('/announcements/schedules')
  schedules.value = schedResponse.data?.data ?? []
}

async function loadData() {
  loading.value = true
  clearMessages()
  try {
    const response = await api.get('/announcements')
    const data = response.data?.data
    files.value = data?.files ?? []
    nodes.value = data?.nodes ?? []
    defaults.value = data?.config?.defaults ?? defaults.value
    schedulingEnabled.value = data?.config?.scheduling?.enabled ?? true
    minuteOptions.value = ['*', ...((data?.config?.presets?.minutes as string[]) ?? ['0', '15', '30', '45'])]
    hourOptions.value = ['*', ...((data?.config?.presets?.hours as string[]) ?? ['7-20'])]
    if (data?.config?.tts?.voice) {
      ttsForm.value.voice = data.config.tts.voice
      defaultVoice.value = data.config.tts.voice
    }
    await loadVoices()
    resetForms()

    await loadSchedules()
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Failed to load announcements'
    error.value = msg
  } finally {
    loading.value = false
  }
}

async function playNow() {
  if (playForm.value.scope === 'global' && !canGlobal.value) {
    error.value = 'Global playback not permitted.'
    return
  }
  busy.value = true
  clearMessages()
  try {
    const response = await api.post('/announcements/play', playForm.value)
    success.value = response.data?.message || 'Playback started.'
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

async function uploadFile() {
  const input = uploadInput.value
  if (!input?.files?.length) {
    error.value = 'Select a file to upload.'
    return
  }
  busy.value = true
  clearMessages()
  try {
    const form = new FormData()
    form.append('file', input.files[0])
    if (uploadName.value.trim()) {
      form.append('name', uploadName.value.trim())
    }
    const response = await api.post('/announcements/upload', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
    success.value = response.data?.message || 'Upload complete.'
    input.value = ''
    uploadName.value = ''
    await loadData()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

async function createTts() {
  if (!selectedVoice.value?.installed) {
    error.value = 'Install the selected voice before generating TTS.'
    return
  }
  busy.value = true
  clearMessages()
  try {
    const response = await api.post('/announcements/tts', ttsForm.value, { timeout: 120000 })
    success.value = response.data?.message || 'TTS created.'
    ttsForm.value.text = ''
    ttsForm.value.name = ''
    await loadData()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

async function installSelectedVoice() {
  const voice = selectedVoice.value
  if (!voice || voice.installed || !voice.catalog) {
    return
  }
  installingVoice.value = true
  clearMessages()
  try {
    const response = await api.post(
      '/announcements/voices/install',
      { voice_id: voice.id },
      { timeout: 300000 }
    )
    success.value = response.data?.message || 'Voice installed.'
    if (response.data?.file) {
      ttsForm.value.voice = response.data.file
    }
    await loadVoices()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    installingVoice.value = false
  }
}

async function deleteFile(name: string) {
  if (!name || !confirm(`Delete announcement "${name}"?`)) {
    return
  }
  busy.value = true
  clearMessages()
  try {
    const response = await api.delete(`/announcements/${encodeURIComponent(name)}`)
    success.value = response.data?.message || 'Deleted.'
    await loadData()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

function applyPreset() {
  switch (schedulePreset.value) {
    case 'daily-7':
      scheduleForm.value.minute = '0'
      scheduleForm.value.hour = '7'
      scheduleForm.value.dom = '*'
      scheduleForm.value.month = '*'
      scheduleForm.value.dow = '*'
      scheduleForm.value.week = '*'
      break
    case 'weekdays-7':
      scheduleForm.value.minute = '0'
      scheduleForm.value.hour = '7'
      scheduleForm.value.dom = '*'
      scheduleForm.value.month = '*'
      scheduleForm.value.dow = '1-5'
      scheduleForm.value.week = '*'
      break
    case 'weekends-9':
      scheduleForm.value.minute = '0'
      scheduleForm.value.hour = '9'
      scheduleForm.value.dom = '*'
      scheduleForm.value.month = '*'
      scheduleForm.value.dow = '0,6'
      scheduleForm.value.week = '*'
      break
    case 'hourly':
      scheduleForm.value.minute = '0'
      scheduleForm.value.hour = '*'
      scheduleForm.value.dom = '*'
      scheduleForm.value.month = '*'
      scheduleForm.value.dow = '*'
      scheduleForm.value.week = '*'
      break
    default:
      break
  }
}

async function saveSchedule() {
  if (scheduleForm.value.scope === 'global' && !canGlobal.value) {
    error.value = 'Global schedules not permitted.'
    return
  }
  if (scheduleForm.value.scope === 'global' && !confirm('Global playback will play on all connected nodes. Continue?')) {
    return
  }

  const useNth = showWeekOfMonth.value && scheduleForm.value.week !== '*'
  busy.value = true
  clearMessages()
  try {
    const payload = {
      minute: resolvedMinute.value,
      hour: resolvedHour.value,
      dom: resolvedDom.value,
      month: scheduleForm.value.month,
      dow: scheduleForm.value.dow,
      week: scheduleForm.value.week,
      use_nth: useNth,
      description: scheduleForm.value.description.trim(),
      file: scheduleForm.value.file,
      node: scheduleForm.value.node,
      scope: scheduleForm.value.scope,
      mode: scheduleForm.value.mode,
    }
    const response = await api.post('/announcements/schedules', payload)
    success.value = response.data?.message || 'Schedule saved.'
    await loadSchedules()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

async function toggleSchedule(job: ScheduleJob) {
  busy.value = true
  clearMessages()
  try {
    const response = await api.patch(`/announcements/schedules/${job.id}/enabled`, {
      enabled: !job.enabled,
    })
    success.value = response.data?.message || 'Schedule updated.'
    await loadSchedules()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

async function deleteSchedule(id: string) {
  if (!confirm('Delete this scheduled job?')) {
    return
  }
  busy.value = true
  clearMessages()
  try {
    const response = await api.delete(`/announcements/schedules/${id}`)
    success.value = response.data?.message || 'Schedule deleted.'
    await loadSchedules()
  } catch (e: unknown) {
    error.value = extractError(e)
  } finally {
    busy.value = false
  }
}

function extractError(e: unknown): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const resp = (e as { response?: { data?: { message?: string } } }).response
    if (resp?.data?.message) {
      return resp.data.message
    }
  }
  return e instanceof Error ? e.message : 'Request failed'
}

watch([voiceRegion, showAllVoices], () => {
  syncVoiceSelection()
})

watch(
  () => props.isVisible,
  (visible) => {
    if (visible) {
      activeTab.value = 'playback'
      loadData()
    } else {
      error.value = ''
      success.value = ''
    }
  }
)

watch(activeTab, async (tab) => {
  if (!props.isVisible || loading.value) {
    return
  }
  try {
    if (tab === 'scheduled') {
      await loadSchedules()
    } else if (tab === 'create') {
      await loadVoices()
      const response = await api.get('/announcements')
      files.value = response.data?.data?.files ?? files.value
    } else if (tab === 'playback') {
      const response = await api.get('/announcements')
      files.value = response.data?.data?.files ?? files.value
    }
  } catch {
    // keep existing data on refresh failure
  }
})
</script>

<style scoped>
.announce-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.announce-modal {
  background: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: min(920px, 94vw);
  max-height: 92vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  color: var(--text-color);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.announce-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
  background: var(--table-header-bg);
}

.announce-modal-header h2 {
  margin: 0;
  font-size: 1.4em;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.8em;
  cursor: pointer;
  color: var(--text-color);
  line-height: 1;
}

.announce-modal-content {
  padding: 16px 20px 20px;
  overflow-y: auto;
}

.tab-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.tab-button {
  border: 1px solid var(--border-color);
  background: var(--background-color);
  color: var(--text-color);
  padding: 8px 14px;
  border-radius: 4px;
  cursor: pointer;
}

.tab-button.active {
  background: var(--link-color);
  color: var(--background-color);
  border-color: var(--link-color);
}

.tab-panel {
  display: block;
}

.section-title {
  margin: 18px 0 10px;
  font-size: 1.05em;
  color: var(--text-color);
}

.field {
  margin-bottom: 12px;
}

.field label {
  display: block;
  margin-bottom: 4px;
  font-size: 0.9em;
}

.field input[type="text"],
.field input[type="file"],
.field select,
.field textarea {
  width: 100%;
  box-sizing: border-box;
}

.voice-install-btn {
  margin-top: 8px;
  width: 100%;
}

.voice-hint {
  margin: 8px 0 0;
  font-size: 0.88em;
  opacity: 0.85;
}

.voice-show-all {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.checkbox-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.voice-count-hint {
  margin-top: 4px;
  font-size: 0.85em;
  opacity: 0.75;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.cron-fields {
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.radio-group {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}

.radio-group label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
}

.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.action-primary {
  font-weight: 600;
}

.action-danger {
  background: #c0392b;
  color: #fff;
}

.status-message {
  padding: 10px 12px;
  border-radius: 4px;
  margin-bottom: 12px;
}

.status-message.loading {
  background: var(--link-color);
  color: var(--background-color);
}

.status-message.error {
  background: rgba(192, 57, 43, 0.15);
  border: 1px solid #c0392b;
}

.status-message.success {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid #27ae60;
}

.schedule-preview {
  margin: 10px 0;
  font-size: 0.92em;
  opacity: 0.9;
}

.empty-hint {
  opacity: 0.8;
  margin-bottom: 12px;
}

.schedule-table-wrap {
  overflow-x: auto;
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85em;
}

.schedule-table th,
.schedule-table td {
  border: 1px solid var(--border-color);
  padding: 6px 8px;
  text-align: left;
}

.schedule-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
</style>
