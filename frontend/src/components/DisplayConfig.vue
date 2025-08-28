<template>
  <div v-if="open" class="display-config-modal" @click="closeModal">
    <div class="display-config-content" @click.stop>
      <div class="display-config-header">
        <h2>Supermon Display Settings</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <form @submit.prevent="saveSettings" class="display-config-form">
        <table class="display-config-table">
          <tr>
            <td valign="top">
              Display Detailed View<br>
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-top" 
                name="show_detailed" 
                value="1" 
                v-model="settings.show_detailed"
              > YES
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-spaced" 
                name="show_detailed" 
                value="0" 
                v-model="settings.show_detailed"
              > NO<br>
            </td>
          </tr>
          <tr>
            <td valign="top">
              Show the number of connections (Displays x of y)<br>
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-top" 
                name="show_number" 
                value="1" 
                v-model="settings.show_number"
              > YES
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-spaced" 
                name="show_number" 
                value="0" 
                v-model="settings.show_number"
              > NO<br>
            </td>
          </tr>
          <tr>
            <td valign="top">
              Show ALL Connections (NO omits NEVER Keyed)<br>
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-top" 
                name="show_all" 
                value="1" 
                v-model="settings.show_all"
              > YES
              <input 
                type="radio" 
                class="display-config-radio display-config-radio-spaced" 
                name="show_all" 
                value="0" 
                v-model="settings.show_all"
              > NO<br>
            </td>
          </tr>
          <tr>
            <td valign="top">
              Maximum Number of Connections to Display in Each Node (0=ALL)<br><br>
              <input 
                type="text" 
                class="display-config-input" 
                name="number_displayed" 
                v-model="settings.number_displayed" 
                maxlength="4" 
                size="3"
              >
            </td>
          </tr>
          <tr>
            <td align="center">
              <input type="submit" class="submit-large" value="Update">
              <input type="button" class="submit-large" value="Close Window" @click="closeModal">
            </td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

interface Props {
  open: boolean
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'settings-updated', settings: any): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const settings = ref({
  show_detailed: '1',
  show_number: '0',
  show_all: '1',
  number_displayed: '0'
})

const loading = ref(false)

const closeModal = () => {
  emit('update:open', false)
}

const loadSettings = async () => {
  try {
    loading.value = true
    const response = await axios.get('/api/config/display', { withCredentials: true })
    if (response.data.success) {
      const data = response.data.data
      settings.value = {
        show_detailed: data['show-detailed'] || '1',
        show_number: data['show-number'] || '0',
        show_all: data['show-all'] || '1',
        number_displayed: data['number-displayed'] || '0'
      }
    }
  } catch (error) {
    console.error('Failed to load display settings:', error)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  try {
    loading.value = true
    const response = await axios.put('/api/config/display', {
      show_detailed: settings.value.show_detailed,
      show_number: settings.value.show_number,
      show_all: settings.value.show_all,
      number_displayed: settings.value.number_displayed
    }, { withCredentials: true })
    
    if (response.data.success) {
      // Emit the updated settings to parent
      emit('settings-updated', response.data.data)
      closeModal()
    }
  } catch (error) {
    console.error('Failed to save display settings:', error)
  } finally {
    loading.value = false
  }
}

// Load settings when modal opens
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    loadSettings()
  }
})

// Load settings on mount if modal is open
onMounted(() => {
  if (props.open) {
    loadSettings()
  }
})
</script>

<style scoped>
.display-config-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.display-config-content {
  background-color: #1a1a1a;
  border: 1px solid #333;
  border-radius: 8px;
  padding: 20px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.display-config-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid #333;
  padding-bottom: 10px;
}

.display-config-header h2 {
  margin: 0;
  color: #fff;
  font-size: 1.5em;
}

.close-button {
  background: none;
  border: none;
  color: #fff;
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  background-color: #333;
  border-radius: 4px;
}

.display-config-form {
  width: 100%;
}

.display-config-table {
  width: 100%;
  border-collapse: collapse;
}

.display-config-table td {
  padding: 10px;
  color: #fff;
  vertical-align: top;
}

.display-config-radio {
  margin-right: 5px;
}

.display-config-radio-top {
  margin-top: 5px;
}

.display-config-radio-spaced {
  margin-left: 15px;
}

.display-config-input {
  background-color: #333;
  border: 1px solid #555;
  color: #fff;
  padding: 5px;
  border-radius: 4px;
}

.display-config-input:focus {
  outline: none;
  border-color: #007bff;
}

.submit-large {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 10px 20px;
  margin: 5px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.submit-large:hover {
  background-color: #0056b3;
}

.submit-large:active {
  background-color: #004085;
}
</style>
