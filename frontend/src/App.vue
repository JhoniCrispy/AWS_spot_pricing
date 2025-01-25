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

    <!-- Always Render Components, Control Visibility with CSS -->
    <div :class="{ hidden: currentTab !== 'spot' }">
      <spot-table />
    </div>
    <div :class="{ hidden: currentTab !== 'steals' }">
      <steals-table />
    </div>
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

.hidden {
  display: none;
}
</style>
