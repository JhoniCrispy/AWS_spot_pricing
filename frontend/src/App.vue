<template>
  <div id="app">
    <h1>AWS Spot Pricing</h1>
    <spot-table />
    <steals-table />
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
  }
}
</script>
