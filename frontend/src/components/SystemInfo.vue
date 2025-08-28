<template>
  <div class="system-info">
    <h3>System Information</h3>
    <p>This component will display comprehensive system information including:</p>
    <ul>
      <li>System uptime and load</li>
      <li>Memory usage</li>
      <li>Disk space</li>
      <li>Network status</li>
      <li>Asterisk status</li>
      <li>AllStar configuration</li>
    </ul>
    <p>Information will be fetched from the backend API and updated in real-time.</p>
    
    <div class="info-actions">
      <Button @click="$emit('close')" variant="secondary">
        Close
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button/Button.vue'

defineEmits<{
  close: []
}>()
</script>

<style scoped>
.system-info {
  padding: 20px;
}

.system-info h3 {
  margin-top: 0;
  color: var(--text-color);
}

.system-info ul {
  margin: 15px 0;
  padding-left: 20px;
}

.system-info li {
  margin: 5px 0;
  color: var(--text-color);
}

.system-info p {
  color: var(--text-color);
  line-height: 1.5;
}

.info-actions {
  margin-top: 20px;
  text-align: right;
}
</style>


