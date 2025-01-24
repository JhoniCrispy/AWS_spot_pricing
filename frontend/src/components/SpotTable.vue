<template>
  <div>
    <h2>All Spot Prices</h2>
    <div class="controls">
      <input
        type="text"
        placeholder="Search by instance type"
        v-model="searchQuery"
      />
      <select v-model="selectedRegion">
        <option value="">All Regions</option>
        <option
          v-for="region in uniqueRegions"
          :key="region"
          :value="region"
        >
          {{ region }}
        </option>
      </select>
      <button @click="toggleSort('spot_price')">
        Sort by Price ({{ sortOrder }})
      </button>
    </div>
    
    <table>
      <thead>
        <tr>
          <th>Region</th>
          <th>Instance Type</th>
          <th>Product Description</th>
          <th>Spot Price</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(price, idx) in filteredPrices" :key="idx">
          <td>{{ price.region }}</td>
          <td>{{ price.instance_type }}</td>
          <td>{{ price.product_description }}</td>
          <td>{{ price.spot_price }}</td>
          <td>{{ price.timestamp }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'SpotTable',
  data() {
    return {
      prices: [],
      searchQuery: '',
      selectedRegion: '',
      sortField: '',
      sortOrder: 'asc'
    }
  },
  computed: {
    uniqueRegions() {
      return [...new Set(this.prices.map(p => p.region))].sort()
    },
    filteredPrices() {
      let filtered = this.prices

      // Filter by search
      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase()
        filtered = filtered.filter(
          p => p.instance_type.toLowerCase().includes(query)
        )
      }

      // Filter by region
      if (this.selectedRegion) {
        filtered = filtered.filter(
          p => p.region === this.selectedRegion
        )
      }

      // Sort
      if (this.sortField) {
        filtered = filtered.sort((a, b) => {
          let aVal = a[this.sortField]
          let bVal = b[this.sortField]

          // Convert numeric if sorting by price
          if (this.sortField === 'spot_price') {
            aVal = parseFloat(aVal)
            bVal = parseFloat(bVal)
          }

          if (aVal < bVal) return this.sortOrder === 'asc' ? -1 : 1
          if (aVal > bVal) return this.sortOrder === 'asc' ? 1 : -1
          return 0
        })
      }

      return filtered
    }
  },
  methods: {
    toggleSort(field) {
      if (this.sortField === field) {
        this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortOrder = 'asc'
      }
    }
  },
  async mounted() {
    const res = await axios.get('http://localhost:8080/api/spot-prices')
    this.prices = res.data
  }
}
</script>
