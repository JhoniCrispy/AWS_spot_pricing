<template>
  <div id="app">
    <h1>AWS Spot Pricing</h1>
    
    <!-- Tab Navigation -->
    <div class="tabs">
      <button 
        @click="currentTab = 'spot'"
        :class="{ active: currentTab === 'spot' }"
      >
        Spot Pricing
      </button>
      <button 
        @click="currentTab = 'steals'"
        :class="{ active: currentTab === 'steals' }"
      >
        Steals
      </button>
    </div>

    <!-- Conditional Rendering of Components -->
    <spot-table v-if="currentTab === 'spot'" />
    <steals-table v-else-if="currentTab === 'steals'" />
  </div>
</template>

<script>
import SpotTable from './components/SpotTable.vue'
import StealsTable from './components/StealsTable.vue'

export default {
  name: 'App',
  components: {
    SpotTable,
    StealsTable
  },
  data() {
    return {
      currentTab: 'spot' // Default to spot table
    }
  }
}
</script>

<style scoped>
.tabs {
  display: flex;
  margin-bottom: 20px;
}

.tabs button {
  padding: 10px 20px;
  margin-right: 10px;
  cursor: pointer;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.tabs button.active {
  background-color: #007bff;
  color: white;
}
</style>