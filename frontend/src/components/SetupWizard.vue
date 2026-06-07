<template>
  <div v-if="visible" class="setup-wizard-overlay">
    <div class="setup-wizard">
      <h2>Welcome to Supermon-ng</h2>
      <p class="intro">Complete these steps to get your node monitor online.</p>

      <div class="steps">
        <div :class="['step', step >= 1 ? 'active' : '']">1. Admin account</div>
        <div :class="['step', step >= 2 ? 'active' : '']">2. Node config</div>
        <div :class="['step', step >= 3 ? 'active' : '']">3. Finish</div>
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
        <p v-if="status?.can_generate_allmon">
          Generate <code>allmon.ini</code> from local Asterisk <code>rpt.conf</code> and <code>manager.conf</code>.
        </p>
        <p v-else>
          Asterisk config was not readable on this host. You can skip and edit <code>user_files/allmon.ini</code> manually later.
        </p>
        <button v-if="status?.can_generate_allmon" class="submit" :disabled="busy" @click="generateAllmon">
          Generate allmon.ini
        </button>
        <button class="submit" :disabled="busy" @click="step = 3">Continue</button>
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
  setup_complete: boolean
  can_generate_allmon: boolean
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

const loadStatus = async () => {
  try {
    const response = await api.get('/setup/status')
    if (response.data.success) {
      status.value = response.data.data
      visible.value = !!status.value?.needs_setup
      if ((status.value?.user_count ?? 0) > 0) {
        step.value = 2
      }
      if ((status.value?.node_count ?? 0) > 0) {
        step.value = 3
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
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Could not create admin'
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
    step.value = 3
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
  loadStatus()
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
  max-width: 520px;
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
  gap: 8px;
  margin: 16px 0 20px;
}

.step {
  flex: 1;
  text-align: center;
  padding: 8px;
  border-radius: 6px;
  background: var(--container-bg);
  font-size: 13px;
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
