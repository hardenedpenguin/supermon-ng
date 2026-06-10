<template>
  <div v-if="visible" class="setup-wizard-overlay">
    <div class="setup-wizard">
      <h2>Welcome to Supermon-ng</h2>
      <p class="intro">Complete these steps to get your node monitor online.</p>

      <div class="steps">
        <div :class="['step', step >= 1 ? 'active' : '']">1. Admin</div>
        <div :class="['step', step >= 2 ? 'active' : '']">2. Site info</div>
        <div :class="['step', step >= 3 ? 'active' : '']">3. Nodes</div>
        <div :class="['step', step >= 4 ? 'active' : '']">4. Finish</div>
      </div>

      <div v-if="step === 1" class="panel">
        <p>Create the first operator account (writes <code>.htpasswd</code> and updates permissions).</p>
        <label>Username</label>
        <input v-model="username" class="form-input" autocomplete="username" />
        <label>Password</label>
        <input v-model="password" type="password" class="form-input" autocomplete="new-password" />
        <button class="submit" :disabled="busy" @click="createAdmin">Create Admin</button>
      </div>

      <div v-else-if="step === 2" class="panel">
        <p>Configure <code>user_files/global.inc</code> — callsign, location, and dashboard titles.</p>
        <label>Callsign</label>
        <input v-model="globalForm.call" class="form-input" placeholder="W5GLE" />
        <label>Operator name</label>
        <input v-model="globalForm.name" class="form-input" placeholder="Your Name" />
        <label>Location</label>
        <input v-model="globalForm.location" class="form-input" placeholder="City, State" />
        <p class="hint">Uncheck a header line to comment it out in <code>global.inc</code> so it is hidden.</p>
        <div class="toggle-field">
          <label class="toggle-row">
            <input v-model="globalForm.title2_enabled" type="checkbox" />
            <span>Dashboard title (TITLE2)</span>
          </label>
          <input
            v-model="globalForm.title2"
            class="form-input"
            placeholder="ASL3+ Management Dashboard"
            :disabled="!globalForm.title2_enabled"
          />
        </div>
        <div class="toggle-field">
          <label class="toggle-row">
            <input v-model="globalForm.title3_enabled" type="checkbox" />
            <span>Subtitle (TITLE3)</span>
          </label>
          <input
            v-model="globalForm.title3"
            class="form-input"
            placeholder="AllStar Network Monitor"
            :disabled="!globalForm.title3_enabled"
          />
        </div>
        <label>Browser tab title</label>
        <input v-model="globalForm.sm_server_name" class="form-input" placeholder="Supermon-ng" />
        <details class="advanced">
          <summary>Optional settings</summary>
          <p class="hint">Uncheck a setting to comment out its line in <code>global.inc</code>.</p>
          <div class="toggle-field">
            <label class="toggle-row">
              <input v-model="globalForm.welcome_msg_enabled" type="checkbox" />
              <span>Welcome message for visitors (WELCOME_MSG)</span>
            </label>
            <input
              v-model="globalForm.welcome_msg"
              class="form-input"
              placeholder="Leave blank for default: Welcome to your callsign Supermon-ng"
              :disabled="!globalForm.welcome_msg_enabled"
            />
          </div>
          <div class="toggle-field">
            <label class="toggle-row">
              <input v-model="globalForm.welcome_msg_logged_enabled" type="checkbox" />
              <span>Welcome message when logged in (WELCOME_MSG_LOGGED)</span>
            </label>
            <input
              v-model="globalForm.welcome_msg_logged"
              class="form-input"
              placeholder="Leave blank for default: Welcome back, operator name!"
              :disabled="!globalForm.welcome_msg_logged_enabled"
            />
          </div>
          <label>Header background color</label>
          <input v-model="globalForm.background_color" class="form-input" placeholder="black" />
          <div class="toggle-field">
            <label class="toggle-row">
              <input v-model="globalForm.callsign_color_enabled" type="checkbox" />
              <span>Callsign color (hex)</span>
            </label>
            <input
              v-model="globalForm.callsign_color"
              class="form-input"
              placeholder="#00ff00"
              :disabled="!globalForm.callsign_color_enabled"
            />
          </div>
          <div class="toggle-field">
            <label class="toggle-row">
              <input v-model="globalForm.dvm_url_enabled" type="checkbox" />
              <span>DVSwitch URL (Digital Dashboard button)</span>
            </label>
            <input
              v-model="globalForm.dvm_url"
              class="form-input"
              placeholder="../dvswitch"
              :disabled="!globalForm.dvm_url_enabled"
            />
          </div>
          <div class="toggle-field">
            <label class="toggle-row">
              <input v-model="globalForm.my_url_enabled" type="checkbox" />
              <span>Your website URL</span>
            </label>
            <input
              v-model="globalForm.my_url"
              class="form-input"
              placeholder="https://example.org/"
              :disabled="!globalForm.my_url_enabled"
            />
          </div>
        </details>
        <button class="submit" :disabled="busy" @click="saveGlobalConfig">Save site info</button>
      </div>

      <div v-else-if="step === 3" class="panel">
        <p v-if="status?.can_generate_allmon">
          Generate <code>allmon.ini</code> from local Asterisk <code>rpt.conf</code> and <code>manager.conf</code>.
        </p>
        <p v-else>
          Asterisk config was not readable on this host. Skip this step and edit <code>user_files/allmon.ini</code> manually later.
        </p>
        <button v-if="status?.can_generate_allmon" class="submit" :disabled="busy" @click="generateAllmon">
          Generate allmon.ini
        </button>
        <button class="submit" :disabled="busy" @click="step = 4">Continue</button>
      </div>

      <div v-else class="panel">
        <p>Setup is ready. Log in with your new account and select a node from the menu.</p>
        <button class="submit" :disabled="busy" @click="finish">Go to Dashboard</button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="info" class="info">{{ info }}</p>

      <button v-if="!status?.needs_setup" class="dismiss" @click="visible = false">Dismiss</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/utils/api'
import { useAppStore } from '@/stores/app'
import type { AxiosErrorResponse } from '@/types/api'

type SetupStatus = {
  needs_setup: boolean
  reasons: string[]
  user_count: number
  node_count: number
  global_configured: boolean
  global_wizard_done: boolean
  wizard_step: number
  setup_complete: boolean
  can_generate_allmon: boolean
}

type GlobalForm = {
  call: string
  name: string
  location: string
  title2: string
  title3: string
  title2_enabled: boolean
  title3_enabled: boolean
  sm_server_name: string
  background_color: string
  callsign_color: string
  callsign_color_enabled: boolean
  dvm_url: string
  dvm_url_enabled: boolean
  my_url: string
  my_url_enabled: boolean
  welcome_msg: string
  welcome_msg_logged: string
  welcome_msg_enabled: boolean
  welcome_msg_logged_enabled: boolean
}

const appStore = useAppStore()
const visible = ref(false)
const step = ref(1)
const busy = ref(false)
const error = ref('')
const info = ref('')
const username = ref('')
const password = ref('')
const status = ref<SetupStatus | null>(null)
const globalForm = ref<GlobalForm>({
  call: '',
  name: '',
  location: '',
  title2: 'ASL3+ Management Dashboard',
  title3: 'AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center',
  title2_enabled: true,
  title3_enabled: true,
  sm_server_name: 'Supermon-ng',
  background_color: 'black',
  callsign_color: '',
  callsign_color_enabled: false,
  dvm_url: '',
  dvm_url_enabled: false,
  my_url: '',
  my_url_enabled: false,
  welcome_msg: '',
  welcome_msg_logged: '',
  welcome_msg_enabled: false,
  welcome_msg_logged_enabled: false,
})

const applyStepFromStatus = (allowAdvanceOnly = false) => {
  if (!status.value?.wizard_step) {
    return
  }
  const target = status.value.wizard_step
  if (target <= 0) {
    return
  }
  if (allowAdvanceOnly && target <= step.value) {
    return
  }
  step.value = target
}

const loadGlobalConfig = async () => {
  try {
    const response = await api.get('/setup/global-config')
    if (response.data.success && response.data.data) {
      const data = response.data.data
      globalForm.value = {
        call: data.call || '',
        name: data.name || '',
        location: data.location || '',
        title2: data.title2 || globalForm.value.title2,
        title3: data.title3 || globalForm.value.title3,
        title2_enabled: data.title2_enabled !== undefined ? !!data.title2_enabled : true,
        title3_enabled: data.title3_enabled !== undefined ? !!data.title3_enabled : true,
        sm_server_name: data.sm_server_name || globalForm.value.sm_server_name,
        background_color: data.background_color || 'black',
        callsign_color: data.callsign_color || '',
        callsign_color_enabled: !!data.callsign_color_enabled,
        dvm_url: data.dvm_url || '',
        dvm_url_enabled: !!data.dvm_url_enabled,
        my_url: data.my_url || '',
        my_url_enabled: !!data.my_url_enabled,
        welcome_msg: data.welcome_msg || '',
        welcome_msg_logged: data.welcome_msg_logged || '',
        welcome_msg_enabled: !!data.welcome_msg_enabled,
        welcome_msg_logged_enabled: !!data.welcome_msg_logged_enabled,
      }
    }
  } catch {
    // Keep defaults when global.inc is missing
  }
}

const loadStatus = async (options: { syncStep?: boolean } = {}) => {
  const syncStep = options.syncStep ?? false
  try {
    const response = await api.get('/setup/status')
    if (response.data.success) {
      status.value = response.data.data
      visible.value = !!status.value?.needs_setup && !status.value?.setup_complete
      await loadGlobalConfig()
      if (syncStep) {
        applyStepFromStatus(false)
      }
    }
  } catch {
    visible.value = false
  }
}

const createAdmin = async () => {
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    const response = await api.post('/setup/admin', {
      username: username.value.trim(),
      password: password.value,
    })
    info.value = response.data.message || 'Admin created'
    step.value = 2
    await loadStatus()
    visible.value = true
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Could not create admin'
  } finally {
    busy.value = false
  }
}

const saveGlobalConfig = async () => {
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    const response = await api.post('/setup/global-config', {
      call: globalForm.value.call.trim(),
      name: globalForm.value.name.trim(),
      location: globalForm.value.location.trim(),
      title2: globalForm.value.title2.trim(),
      title3: globalForm.value.title3.trim(),
      title2_enabled: globalForm.value.title2_enabled,
      title3_enabled: globalForm.value.title3_enabled,
      sm_server_name: globalForm.value.sm_server_name.trim(),
      background_color: globalForm.value.background_color.trim(),
      callsign_color: globalForm.value.callsign_color.trim(),
      callsign_color_enabled: globalForm.value.callsign_color_enabled,
      dvm_url: globalForm.value.dvm_url.trim(),
      dvm_url_enabled: globalForm.value.dvm_url_enabled,
      my_url: globalForm.value.my_url.trim(),
      my_url_enabled: globalForm.value.my_url_enabled,
      welcome_msg: globalForm.value.welcome_msg.trim(),
      welcome_msg_logged: globalForm.value.welcome_msg_logged.trim(),
      welcome_msg_enabled: globalForm.value.welcome_msg_enabled,
      welcome_msg_logged_enabled: globalForm.value.welcome_msg_logged_enabled,
    })
    info.value = response.data.message || 'Site configuration saved'
    step.value = 3
    await loadStatus()
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Could not save site configuration'
  } finally {
    busy.value = false
  }
}

const generateAllmon = async () => {
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    const response = await api.post('/setup/generate-allmon', { force: false })
    info.value = response.data.message || 'allmon.ini generated'
    step.value = 4
    await loadStatus()
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Could not generate allmon.ini'
  } finally {
    busy.value = false
  }
}

const finish = async () => {
  busy.value = true
  try {
    await api.post('/setup/complete')
    if (status.value) {
      status.value.setup_complete = true
      status.value.needs_setup = false
    }
    visible.value = false
    await appStore.initialize()
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Could not complete setup'
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  loadStatus({ syncStep: true })
})
</script>

<style scoped>
.setup-wizard-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.setup-wizard {
  width: 92%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  background: var(--background-color);
  border: 2px solid var(--primary-color);
  border-radius: 10px;
  padding: 24px;
  color: var(--text-color);
}

.intro {
  opacity: 0.9;
}

.steps {
  display: flex;
  gap: 6px;
  margin: 16px 0 20px;
}

.step {
  flex: 1;
  text-align: center;
  padding: 8px 4px;
  border-radius: 6px;
  background: var(--container-bg);
  font-size: 12px;
  opacity: 0.6;
}

.step.active {
  opacity: 1;
  border: 1px solid var(--primary-color);
}

.panel label {
  display: block;
  margin-top: 10px;
  font-weight: 600;
}

.form-input {
  width: 100%;
  box-sizing: border-box;
  margin-top: 4px;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  background: var(--input-bg);
  color: var(--input-text);
}

.advanced {
  margin-top: 14px;
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
}

.advanced summary {
  cursor: pointer;
  font-weight: 600;
}

.hint {
  margin: 8px 0 4px;
  font-size: 13px;
  opacity: 0.85;
}

.toggle-field {
  margin-top: 10px;
}

.toggle-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 0;
  font-weight: 600;
  cursor: pointer;
}

.toggle-row input[type='checkbox'] {
  width: auto;
  margin: 0;
}

.submit {
  margin-top: 14px;
  margin-right: 8px;
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border: 1px solid var(--border-color);
  padding: 8px 14px;
  border-radius: 15px;
  cursor: pointer;
  font-weight: bold;
}

.error {
  color: var(--error-color);
  margin-top: 12px;
}

.info {
  color: var(--success-color);
  margin-top: 12px;
}

.dismiss {
  margin-top: 16px;
  background: transparent;
  border: none;
  color: var(--text-color);
  opacity: 0.7;
  cursor: pointer;
}
</style>
