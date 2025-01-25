<template>
  <div id="app" class="minimal-app-container">
    <h1 class="minimal-title">AWS Spot Pricing</h1>

    <!-- Tab Navigation -->
    <div class="minimal-tabs">
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
.minimal-app-container {
  font-family: Arial, sans-serif;
  background-color: #fff;
  color: #333;
  padding: 20px;
  border-radius: 4px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.minimal-title {
  color: #333;
  text-align: center;
  font-size: 24px;
  margin-bottom: 20px;
}

.minimal-tabs {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.minimal-tabs button {
  padding: 10px 20px;
  margin: 0 5px;
  cursor: pointer;
  background-color: #f5f5f5;
  border: 1px solid #ccc;
  border-radius: 4px;
  color: #333;
  font-size: 14px;
  transition: all 0.3s ease;
}

.minimal-tabs button:hover {
  background-color: #007bff;
  color: #fff;
}

.minimal-tabs button.active {
  background-color: #007bff;
  color: #fff;
  font-weight: bold;
}

.hidden {
  display: none;
}
</style>
