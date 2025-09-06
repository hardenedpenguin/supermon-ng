<template>
  <div v-if="isVisible" class="modal-overlay" @click.self="closeModal">
    <div class="modal-content hamclock-modal">
      <div class="modal-header">
        <h3>HamClock</h3>
        <button @click="closeModal" class="close-button">&times;</button>
      </div>
      <div class="modal-body">
        <iframe 
          :src="url" 
          frameborder="0" 
          width="100%" 
          height="100%"
          style="border: none;">
        </iframe>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HamClock',
  props: {
    isVisible: {
      type: Boolean,
      default: false
    },
    url: {
      type: String,
      default: ''
    }
  },
  emits: ['close'],
  methods: {
    closeModal() {
      this.$emit('close');
    }
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.hamclock-modal {
  background: #1a1a1a;
  border-radius: 8px;
  width: 95vw;
  height: 90vh;
  max-width: 1400px;
  max-height: 900px;
  display: flex;
  flex-direction: column;
  border: 1px solid #333;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #333;
  background: #2a2a2a;
  border-radius: 8px 8px 0 0;
}

.modal-header h3 {
  margin: 0;
  color: #fff;
  font-size: 1.2em;
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
  border-radius: 4px;
  transition: background-color 0.2s;
}

.close-button:hover {
  background-color: #444;
}

.modal-body {
  flex: 1;
  padding: 0;
  overflow: hidden;
}

.modal-body iframe {
  width: 100%;
  height: 100%;
}
</style>
