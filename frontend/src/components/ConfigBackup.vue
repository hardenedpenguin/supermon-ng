<template>
  <div v-if="open" class="config-backup-modal" @click="closeModal">
    <div class="config-backup-content" @click.stop>
      <div class="config-backup-header">
        <h3>Config Backup &amp; Import</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="config-backup-body">
        <section>
          <h4>Backup</h4>
          <p>Export <code>user_files</code>, preferences, node status scripts, and <code>.env</code> as a zip archive.</p>
          <button class="submit" :disabled="exporting" @click="exportConfig">
            {{ exporting ? 'Exporting...' : 'Download Backup' }}
          </button>
        </section>

        <section>
          <h4>Restore</h4>
          <p>Upload a backup zip created by Supermon-ng. Current config is backed up on the server before restore.</p>
          <input ref="fileInput" type="file" accept=".zip,application/zip" @change="onFileSelected" />
          <button class="submit" :disabled="!selectedFile || importing" @click="importConfig">
            {{ importing ? 'Restoring...' : 'Restore Backup' }}
          </button>
        </section>

        <section>
          <h4>Importers</h4>
          <label>AllScan / favorites.ini (label/cmd pairs)</label>
          <textarea v-model="favoritesImport" rows="5" placeholder="label[] = &quot;Hub&quot;&#10;cmd[] = &quot;rpt cmd %node% ilink 13 27225&quot;" />
          <button class="submit" :disabled="!favoritesImport.trim() || importingFavorites" @click="importFavorites">
            Import Favorites
          </button>

          <label class="spaced">Allmon3 nodes (ini stanzas)</label>
          <textarea v-model="allmonImport" rows="6" placeholder="[546050]&#10;host=127.0.0.1:5038&#10;user=admin&#10;passwd=secret" />
          <button class="submit" :disabled="!allmonImport.trim() || importingNodes" @click="importNodes">
            Import Nodes
          </button>
        </section>

        <p v-if="message" :class="['message', messageType]">{{ message }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { api } from '@/utils/api'
import { appUrl } from '@/utils/basePath'
import { getCsrfService } from '@/services/CsrfTokenService'
import type { AxiosErrorResponse } from '@/types/api'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const exporting = ref(false)
const importing = ref(false)
const importingFavorites = ref(false)
const importingNodes = ref(false)
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const favoritesImport = ref('')
const allmonImport = ref('')
const message = ref('')
const messageType = ref<'success' | 'error'>('success')

const closeModal = () => emit('update:open', false)

const setMessage = (text: string, type: 'success' | 'error' = 'success') => {
  message.value = text
  messageType.value = type
}

const exportConfig = async () => {
  exporting.value = true
  message.value = ''
  try {
    const response = await axios.get(appUrl('api/v1/admin/config/export'), {
      responseType: 'blob',
      withCredentials: true,
    })
    const blob = new Blob([response.data], { type: 'application/zip' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition = response.headers['content-disposition'] as string | undefined
    const match = disposition?.match(/filename="([^"]+)"/)
    link.download = match?.[1] || `supermon-ng-config-${Date.now()}.zip`
    link.href = url
    link.click()
    URL.revokeObjectURL(url)
    setMessage('Backup downloaded')
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    setMessage(axiosError.response?.data?.message || 'Export failed', 'error')
  } finally {
    exporting.value = false
  }
}

const onFileSelected = (event: Event) => {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

const importConfig = async () => {
  if (!selectedFile.value) {
    return
  }
  importing.value = true
  message.value = ''
  try {
    const csrf = await getCsrfService().getToken()
    const form = new FormData()
    form.append('archive', selectedFile.value)
    const response = await axios.post(appUrl('api/v1/admin/config/import'), form, {
      withCredentials: true,
      headers: {
        'X-CSRF-Token': csrf,
      },
    })
    setMessage(response.data.message || 'Restore complete')
    selectedFile.value = null
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    setMessage(axiosError.response?.data?.message || 'Restore failed', 'error')
  } finally {
    importing.value = false
  }
}

const importFavorites = async () => {
  importingFavorites.value = true
  try {
    const response = await api.post('/admin/import/allscan-favorites', {
      content: favoritesImport.value,
    })
    setMessage(response.data.message || 'Favorites imported')
    favoritesImport.value = ''
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    setMessage(axiosError.response?.data?.message || 'Import failed', 'error')
  } finally {
    importingFavorites.value = false
  }
}

const importNodes = async () => {
  importingNodes.value = true
  try {
    const response = await api.post('/admin/import/allmon3-nodes', {
      content: allmonImport.value,
    })
    setMessage(response.data.message || 'Nodes imported')
    allmonImport.value = ''
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    setMessage(axiosError.response?.data?.message || 'Import failed', 'error')
  } finally {
    importingNodes.value = false
  }
}
</script>

<style scoped>
.config-backup-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.config-backup-content {
  background: var(--background-color);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: 92%;
  max-width: 640px;
  max-height: 90vh;
  overflow: auto;
}

.config-backup-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}

.config-backup-header h3 {
  margin: 0;
  color: var(--text-color);
}

.config-backup-body {
  padding: 20px;
  color: var(--text-color);
}

section {
  margin-bottom: 20px;
}

section h4 {
  margin: 0 0 8px;
}

label {
  display: block;
  margin: 10px 0 6px;
  font-weight: 600;
}

label.spaced {
  margin-top: 16px;
}

textarea {
  width: 100%;
  box-sizing: border-box;
  background: var(--input-bg);
  color: var(--input-text);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 8px;
  font-family: monospace;
  font-size: 13px;
}

.submit {
  margin-top: 8px;
  margin-right: 6px;
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border: 1px solid var(--border-color);
  padding: 5px 10px;
  border-radius: 15px;
  cursor: pointer;
  font-weight: bold;
}

.submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--text-color);
  cursor: pointer;
}

.message {
  margin-top: 12px;
  padding: 10px;
  border-radius: 6px;
}

.message.success {
  background: rgba(40, 167, 69, 0.15);
  color: #1f6b34;
}

.message.error {
  background: rgba(220, 53, 69, 0.15);
  color: #842029;
}
</style>
