<template>
  <div v-if="isVisible" class="donate-modal" @click="closeModal">
    <div class="donate-content" @click.stop>
      <div class="donate-header">
        <h2>Support This Project</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="donate-body">
        <p class="donate-description">
          If you find this system useful, please consider a donation to help with maintenance and development.
        </p>
        
        <!-- PayPal Donation -->
        <div class="donate-option">
          <h3>PayPal</h3>
          <p class="donate-info">Donate securely via PayPal</p>
          <form 
            action="https://www.paypal.com/donate" 
            method="post" 
            target="_blank"
            @submit="handlePayPalSubmit"
          >
            <input type="hidden" name="business" value="H2XYYRGQ9Q92E" />
            <input type="hidden" name="no_recurring" value="0" />
            <input type="hidden" name="item_name" value="Help to Support the Continued Development" />
            <input type="hidden" name="currency_code" value="USD" />
            <button type="submit" class="donate-button paypal-button">
              Donate with PayPal
            </button>
            <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
          </form>
        </div>
        
        <!-- CashApp Donation -->
        <div class="donate-option">
          <h3>CashApp</h3>
          <p class="donate-info">Send money via CashApp to $anarchpeng</p>
          <a 
            href="https://cash.app/$anarchpeng" 
            target="_blank" 
            class="donate-button cashapp-button"
            @click="handleCashAppClick"
          >
            Send via CashApp
          </a>
        </div>
        
        <!-- Zelle Donation -->
        <div class="donate-option">
          <h3>Zelle</h3>
          <p class="donate-info">Send money via Zelle using your bank's app</p>
          <button 
            class="donate-button zelle-button"
            @click="showZelleInfo"
          >
            Get Zelle Info
          </button>
        </div>
      </div>
    </div>
    
    <!-- Zelle Modal -->
    <div v-if="showZelleModal" class="zelle-modal" @click="hideZelleInfo">
      <div class="zelle-modal-content" @click.stop>
        <h3>Zelle Information</h3>
        <div class="zelle-info">
          <p><strong>Email:</strong> geekypenguin@gmail.com</p>
          <p><strong>Name:</strong> Jory Pratt</p>
        </div>
        <button class="close-zelle-button" @click="hideZelleInfo">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  isVisible: {
    type: Boolean,
    default: false
  }
}

const emit = defineEmits(['update:isVisible'])

const showZelleModal = ref(false)

const closeModal = () => {
  emit('update:isVisible', false)
}

const showZelleInfo = () => {
  showZelleModal.value = true
}

const hideZelleInfo = () => {
  showZelleModal.value = false
}

const handlePayPalSubmit = () => {
  // PayPal form will submit automatically
  console.log('PayPal donation initiated')
}

const handleCashAppClick = () => {
  console.log('CashApp donation link opened')
}
</script>

<style scoped>
.donate-modal {
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

.donate-content {
  background-color: var(--background-color);
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-color);
}

.donate-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--container-bg);
}

.donate-header h2 {
  margin: 0;
  color: var(--text-color);
  font-size: 1.5em;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--text-color);
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: var(--link-color);
}

.donate-body {
  padding: 1.5rem;
}

.donate-description {
  text-align: center;
  color: var(--text-color);
  margin-bottom: 1.5rem;
  font-size: 0.9em;
  line-height: 1.4;
}

.donate-option {
  margin: 1rem 0;
  padding: 1.25rem;
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  text-align: center;
}

.donate-option h3 {
  color: var(--text-color);
  margin: 0 0 0.5rem 0;
  font-size: 1.1em;
}

.donate-info {
  margin: 0.5rem 0 1rem 0;
  color: var(--text-color);
  font-size: 0.9em;
}

.donate-button {
  display: inline-block;
  padding: 12px 24px;
  border-radius: 6px;
  font-weight: bold;
  text-decoration: none;
  transition: background-color 0.3s ease;
  border: none;
  cursor: pointer;
  font-size: 1em;
  color: white;
}

.paypal-button {
  background-color: #0070ba;
}

.paypal-button:hover {
  background-color: #005ea6;
}

.cashapp-button {
  background-color: #00d632;
}

.cashapp-button:hover {
  background-color: #00b329;
}

.zelle-button {
  background-color: #6b4ce6;
}

.zelle-button:hover {
  background-color: #5a3fd4;
}

.zelle-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1100;
}

.zelle-modal-content {
  background-color: var(--background-color);
  padding: 1.5rem;
  border-radius: 8px;
  width: 90%;
  max-width: 400px;
  border: 1px solid var(--border-color);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.zelle-modal-content h3 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
  text-align: center;
}

.zelle-info {
  margin-bottom: 1.5rem;
}

.zelle-info p {
  margin: 0.5rem 0;
  color: var(--text-color);
  font-size: 0.9em;
}

.zelle-info strong {
  color: var(--link-color);
}

.close-zelle-button {
  width: 100%;
  padding: 10px 16px;
  background-color: var(--table-header-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9em;
}

.close-zelle-button:hover {
  background-color: var(--border-color);
}

@media (max-width: 768px) {
  .donate-content {
    width: 95%;
    max-height: 90vh;
  }
  
  .donate-body {
    padding: 1rem;
  }
  
  .donate-option {
    padding: 1rem;
  }
  
  .donate-button {
    padding: 10px 20px;
    font-size: 0.9em;
  }
}
</style>
