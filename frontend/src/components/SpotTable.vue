<template>
  <div>
    <h2>Spot Prices</h2>
    <div class="controls">
      <select v-model="selectedRegion" @change="debouncedFetch">
        <option value="">All Regions</option>
        <option v-for="region in metadata.regions" :key="region" :value="region">
          {{ region }}
        </option>
      </select>

      <select v-model="selectedProductDescription" @change="debouncedFetch">
        <option value="">All Product Descriptions</option>
        <option v-for="desc in metadata.product_descriptions" :key="desc" :value="desc">
          {{ desc }}
        </option>
      </select>

      <div>
        Price Range:
        <input type="number" v-model.number="minPrice" :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price" @change="debouncedFetch" />
        to
        <input type="number" v-model.number="maxPrice" :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price" @change="debouncedFetch" />
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th @click="toggleSort('region')">Region</th>
          <th @click="toggleSort('instance_type')">Instance Type</th>
          <th @click="toggleSort('product_description')">Product Description</th>
          <th @click="toggleSort('spot_price')">Spot Price</th>
          <th @click="toggleSort('timestamp')">Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="price in prices" :key="`${price.instance_type}-${price.timestamp}`">
          <td>{{ price.region }}</td>
          <td>{{ price.instance_type }}</td>
          <td>{{ price.product_description }}</td>
          <td>{{ price.spot_price }}</td>
          <td>{{ price.timestamp }}</td>
        </tr>
      </tbody>
    </table>

    <div class="pagination">
      <button @click="changePage(-1)" :disabled="currentPage === 1">Previous</button>
      <span>Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="changePage(1)" :disabled="currentPage === totalPages">Next</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import debounce from 'lodash/debounce'
axios.defaults.baseURL = 'http://localhost:8080';
export default {
  name: 'SpotTable',
  data() {
    return {
      metadata: {
        regions: [],
        product_descriptions: [],
        price_range: { min_price: 0, max_price: 0 }
      },
      prices: [],
      selectedRegion: '',
      selectedProductDescription: '',
      minPrice: null,
      maxPrice: null,
      sortField: 'timestamp',
      sortOrder: 'DESC',
      currentPage: 1,
      totalPages: 1,
      limit: 25
    }
  },
  created() {
    this.debouncedFetch = debounce(this.fetchPrices, 300);
    this.fetchMetadata().then(() => {
      if (this.minPrice !== null && this.maxPrice !== null) {
        this.fetchPrices();
      }
    });
  },
  methods: {
    async fetchMetadata() {
      try {///fsf
        const response = await axios.get('/api/spot-prices/metadata')
        this.metadata = response.data
        this.minPrice = this.metadata.price_range.min_price
        this.maxPrice = this.metadata.price_range.max_price

        // Trigger price fetch after setting metadata
        this.fetchPrices()
      } catch (error) {
        console.error('Error fetching metadata:', error)
      }
    },
    async fetchPrices() {
      try {
        const response = await axios.get('/api/spot-prices', {
          params: {
            page: this.currentPage,
            limit: this.limit,
            sortBy: this.sortField,
            sortOrder: this.sortOrder,
            ...(this.selectedRegion && { region: this.selectedRegion }),
            ...(this.selectedProductDescription && { product_description: this.selectedProductDescription }),
            min_price: this.minPrice,
            max_price: this.maxPrice
          }
        })
        console.log("responseedddd" ,response)
        this.prices = response.data.data
        this.totalPages = response.data.pagination.total_pages
      } catch (error) {
        console.error('Error fetching spot prices:', error)
      }
    },
    toggleSort(field) {
      if (this.sortField === field) {
        this.sortOrder = this.sortOrder === 'ASC' ? 'DESC' : 'ASC'
      } else {
        this.sortField = field
        this.sortOrder = 'DESC'
      }
      this.currentPage = 1
      this.fetchPrices()
    },
    changePage(delta) {
      const newPage = this.currentPage + delta
      if (newPage >= 1 && newPage <= this.totalPages) {
        this.currentPage = newPage
        this.fetchPrices()
      }
    }
  },
  mounted() {
    this.fetchPrices()
  }
}
</script>