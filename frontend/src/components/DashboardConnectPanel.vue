<template>
  <div id="connect_form">
    <div v-if="displayedNodes.length > 0">
      <select
        v-if="displayedNodes.length > 1"
        :value="selectedLocalNode"
        class="submit"
        @change="emit('update:selectedLocalNode', ($event.target as HTMLSelectElement).value)"
      >
        <option
          v-for="node in displayedNodes"
          :key="String(node.id)"
          :value="String(node.id)"
          class="submit"
        >
          {{ node.id }} => {{ node.info || node.description || 'Node not in database' }}
        </option>
      </select>

      <input v-else-if="displayedNodes.length === 1" type="hidden" :value="selectedLocalNode" />

      <input
        :value="targetNode"
        type="text"
        class="submit"
        placeholder="Node to connect/DTMF"
        @input="emit('update:targetNode', ($event.target as HTMLInputElement).value)"
      />

      <label v-if="appStore.hasPermission('PERMUSER')" class="perm-label">
        Perm
        <input
          type="checkbox"
          :checked="permConnect"
          @change="emit('update:permConnect', ($event.target as HTMLInputElement).checked)"
        />
      </label>
      <br>
    </div>

    <input type="button" class="submit" value="Connect" @click="connect">
    <input type="button" class="submit" value="Disconnect" @click="disconnect">
    <input v-if="appStore.hasPermission('MONUSER')" type="button" class="submit" value="Monitor" @click="monitor">
    <input v-if="appStore.hasPermission('LMONUSER')" type="button" class="submit" value="Local Monitor" @click="localmonitor">
    <input type="button" class="submit" value="Voter" @click="emit('action', 'voter')">

    <input v-if="appStore.hasPermission('DTMFUSER')" type="button" class="submit" value="DTMF" @click="dtmf">
    <input v-if="appStore.hasPermission('ASTLKUSER')" type="button" class="submit" value="Lookup" @click="emit('action', 'astlookup')">
    <input v-if="appStore.hasPermission('RSTATUSER')" type="button" class="submit" value="Rpt Stats" @click="emit('action', 'rptstats')">
    <input v-if="appStore.hasPermission('BUBLUSER')" type="button" class="submit" value="Bubble" @click="emit('action', 'bubble')">
    <input v-if="appStore.hasPermission('CTRLUSER')" type="button" class="submit" value="Control Panel" @click="emit('action', 'control-panel')">
    <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Favorites" @click="emit('action', 'favorites')">
    <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Add Favorite" @click="emit('action', 'add-favorite')">
    <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Delete Favorite" @click="emit('action', 'delete-favorite')">

    <hr class="button-separator">
    <input v-if="appStore.hasPermission('CFGEDUSER')" type="button" class="submit" value="Configuration Editor" @click="emit('action', 'configeditor')">
    <input v-if="appStore.hasPermission('HWTOUSER')" type="button" class="submit" value="AllStar How To's" @click="emit('action', 'open-help')">
    <input v-if="appStore.hasPermission('WIKIUSER')" type="button" class="submit" value="AllStar Wiki" @click="emit('action', 'open-wiki')">
    <input v-if="appStore.hasPermission('CSTATUSER')" type="button" class="submit" value="CPU Status" @click="emit('action', 'cpustats')">
    <input v-if="appStore.hasPermission('ASTATUSER')" type="button" class="submit" value="AllStar Status" @click="emit('action', 'aststats')">
    <input v-if="appStore.hasPermission('ACTNUSER')" type="button" class="submit" value="Active Nodes" @click="emit('action', 'open-active-nodes')">
    <input v-if="appStore.hasPermission('ALLNUSER')" type="button" class="submit" value="All Nodes" @click="emit('action', 'open-all-nodes')">

    <input v-if="appStore.hasPermission('DBTUSER')" type="button" class="submit" value="Database" @click="emit('action', 'database')">
    <input v-if="appStore.hasPermission('LLOGUSER')" type="button" class="submit" value="Linux Log" @click="emit('action', 'linuxlog')">
    <input v-if="appStore.hasPermission('ASTLUSER')" type="button" class="submit" value="AST Log" @click="emit('action', 'astlog')">
    <input v-if="appStore.hasPermission('WLOGUSER')" type="button" class="submit" value="Web Access Log" @click="emit('action', 'webacclog')">
    <input v-if="appStore.hasPermission('WERRUSER')" type="button" class="submit" value="Web Error Log" @click="emit('action', 'weberrlog')">

    <input v-if="appStore.hasPermission('ASTRELUSER')" type="button" class="submit" value="IAX2/Module RELOAD" @click="emit('action', 'astreload')">
    <input v-if="appStore.hasPermission('ASTSTRUSER')" type="button" class="submit" value="AST START" @click="emit('action', 'astaron')">
    <input v-if="appStore.hasPermission('ASTSTPUSER')" type="button" class="submit" value="AST STOP" @click="emit('action', 'astaroff')">
    <input v-if="appStore.hasPermission('FSTRESUSER')" type="button" class="submit" value="RESTART" @click="emit('action', 'fastrestart')">
    <input v-if="appStore.hasPermission('RBTUSER')" type="button" class="submit" value="Server REBOOT" @click="emit('action', 'reboot')">

    <input v-if="appStore.hasPermission('GPIOUSER')" type="button" class="submit" value="GPIO" @click="emit('action', 'openpigpio')">
    <input v-if="appStore.hasPermission('BANUSER')" type="button" class="submit" value="Access List" @click="emit('action', 'openbanallow')">
    <input v-if="appStore.hasPermission('DVSWITCHUSER')" type="button" class="submit" value="DVSwitch Mode" @click="emit('action', 'dvswitch')">
  </div>
</template>

<script setup lang="ts">
import { useAppStore } from '@/stores/app'
import type { Node } from '@/types'

defineProps<{
  displayedNodes: Node[]
  targetNode: string
  selectedLocalNode: string
  permConnect: boolean
  connect: () => void | Promise<void>
  disconnect: () => void | Promise<void>
  monitor: () => void | Promise<void>
  localmonitor: () => void | Promise<void>
  dtmf: () => void | Promise<void>
}>()

const emit = defineEmits<{
  'update:targetNode': [value: string]
  'update:selectedLocalNode': [value: string]
  'update:permConnect': [value: boolean]
  action: [name: string]
}>()

const appStore = useAppStore()
</script>

<style scoped>
#connect_form {
  text-align: center;
}

.perm-label {
  font-size: 14px;
  font-weight: bold;
  color: var(--text-color);
  display: inline-block;
  margin-left: 5px;
}

.perm-label input[type="checkbox"] {
  margin: 0;
}

.button-separator {
  border: none;
  height: 1px;
  background-color: var(--border-color);
  margin: 10px 0;
}

.submit,
.submit-large,
.submit2 {
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border: 1px solid var(--border-color);
  padding: 5px 10px;
  margin: 2px;
  border-radius: 15px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s ease;
  font-size: 14px;
}

.submit:hover,
.submit-large:hover,
.submit2:hover {
  background-color: var(--primary-color);
  color: var(--background-color);
  border-color: var(--primary-color);
  transform: translateY(-1px);
}

.submit2 {
  background-color: var(--table-header-bg);
}
</style>
