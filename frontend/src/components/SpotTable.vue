<template>
  <div>
    <h2>Spot Prices</h2>

    <!-- Filters -->
    <div class="filters">
      <label>
        Region:
        <select v-model="selectedRegion">
          <option value="">All Regions</option>
          <option v-for="region in metadata.regions" :key="region" :value="region">
            {{ region }}
          </option>
        </select>
      </label>

      <label>
        Product Description:
        <select v-model="selectedProductDescription">
          <option value="">All Product Descriptions</option>
          <option v-for="desc in metadata.product_descriptions" :key="desc" :value="desc">
            {{ desc }}
          </option>
        </select>
      </label>

      <label>
        Price Range:
        <input
          type="number"
          v-model.number="minPrice"
          :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price"
          placeholder="Min Price"
        />
        to
        <input
          type="number"
          v-model.number="maxPrice"
          :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price"
          placeholder="Max Price"
        />
      </label>
    </div>

    <!-- Spot Prices Table -->
    <table>
      <thead>
        <tr>
          <th 
            @click="toggleSort('region')"
            :class="{ sorted: sortField === 'region' }"
          >
            Region 
            <span v-if="sortField === 'region'">
              {{ sortOrder === 'ASC' ? '▲' : '▼' }}
            </span>
          </th>
          <th 
            @click="toggleSort('instance_type')"
            :class="{ sorted: sortField === 'instance_type' }"
          >
            Instance Type
            <span v-if="sortField === 'instance_type'">
              {{ sortOrder === 'ASC' ? '▲' : '▼' }}
            </span>
          </th>
          <th 
            @click="toggleSort('product_description')"
            :class="{ sorted: sortField === 'product_description' }"
          >
            Product Description
            <span v-if="sortField === 'product_description'">
              {{ sortOrder === 'ASC' ? '▲' : '▼' }}
            </span>
          </th>
          <th 
            @click="toggleSort('spot_price')"
            :class="{ sorted: sortField === 'spot_price' }"
          >
            Spot Price
            <span v-if="sortField === 'spot_price'">
              {{ sortOrder === 'ASC' ? '▲' : '▼' }}
            </span>
          </th>
          <th 
            @click="toggleSort('timestamp')"
            :class="{ sorted: sortField === 'timestamp' }"
          >
            Timestamp
            <span v-if="sortField === 'timestamp'">
              {{ sortOrder === 'ASC' ? '▲' : '▼' }}
            </span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="price in paginatedPrices" :key="`${price.instance_type}-${price.timestamp}`">
          <td>{{ price.region }}</td>
          <td>{{ price.instance_type }}</td>
          <td>{{ price.product_description }}</td>
          <td>{{ price.spot_price.toFixed(4) }}</td>
          <td>{{ price.timestamp }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
      <button @click="changePage(-1)" :disabled="currentPage === 1">Previous</button>
      <span>Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="changePage(1)" :disabled="currentPage === totalPages">Next</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SpotTable',
  data() {
    return {
      metadata: {
        regions: [],
        product_descriptions: [],
        price_range: { min_price: 0, max_price: 0 }
      },
      allPrices: [],
      filteredPrices: [],
      selectedRegion: '',
      selectedProductDescription: '',
      minPrice: null,
      maxPrice: null,
      sortField: 'timestamp',
      sortOrder: 'DESC',
      currentPage: 1,
      pageSize: 10
    }
  },
  computed: {
    totalPages() {
      return Math.ceil(this.filteredPrices.length / this.pageSize);
    },
    paginatedPrices() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.filteredPrices.slice(start, start + this.pageSize);
    }
  },
  watch: {
    selectedRegion: 'applyFiltersAndSort',
    selectedProductDescription: 'applyFiltersAndSort',
    minPrice: 'applyFiltersAndSort',
    maxPrice: 'applyFiltersAndSort'
  },
  created() {
    this.fetchMetadata();
  },
  methods: {
    // Parse numeric values to ensure proper sorting
    parseNumeric(value) {
      // Convert to number, handling potential string representations
      const parsed = parseFloat(value);
      return isNaN(parsed) ? value : parsed;
    },
    async fetchMetadata() {
      try {
        const response = await axios.get('http://localhost:8080/api/spot-prices/metadata');
        this.metadata = response.data;
        
        // Ensure min and max prices are numbers
        this.minPrice = this.parseNumeric(this.metadata.price_range.min_price);
        this.maxPrice = this.parseNumeric(this.metadata.price_range.max_price);

        this.fetchPrices();
      } catch (error) {
        console.error('Error fetching metadata:', error);
      }
    },
    async fetchPrices() {
      try {
        const response = await axios.get('http://localhost:8080/api/spot-prices');
        // Transform data to ensure numeric parsing
        this.allPrices = response.data.data.map(price => ({
          ...price,
          spot_price: this.parseNumeric(price.spot_price),
          // Use a unique, stable key
          _id: `${price.region}-${price.instance_type}-${price.timestamp}`
        }));
        this.applyFiltersAndSort();
      } catch (error) {
        console.error('Error fetching spot prices:', error);
      }
    },
    applyFiltersAndSort() {
      // Filter with parsed numeric comparisons
      this.filteredPrices = this.allPrices.filter(price => {
        const regionMatch = !this.selectedRegion || price.region === this.selectedRegion;
        const productDescriptionMatch = !this.selectedProductDescription || price.product_description === this.selectedProductDescription;
        const minPriceMatch = this.minPrice === null || price.spot_price >= this.minPrice;
        const maxPriceMatch = this.maxPrice === null || price.spot_price <= this.maxPrice;

        return regionMatch && productDescriptionMatch && minPriceMatch && maxPriceMatch;
      });

      // Improved sorting with type-aware comparison
      this.filteredPrices.sort((a, b) => {
        const field = this.sortField;
        const valA = this.parseNumeric(a[field]);
        const valB = this.parseNumeric(b[field]);
        
        // Handle different types of sorting
        if (typeof valA === 'number' && typeof valB === 'number') {
          return this.sortOrder === 'ASC' ? valA - valB : valB - valA;
        } else {
          // Fallback to string comparison for non-numeric fields
          return this.sortOrder === 'ASC' 
            ? String(valA).localeCompare(String(valB))
            : String(valB).localeCompare(String(valA));
        }
      });

      this.currentPage = 1;
    },
    toggleSort(field) {
      if (this.sortField === field) {
        this.sortOrder = this.sortOrder === 'ASC' ? 'DESC' : 'ASC';
      } else {
        this.sortField = field;
        this.sortOrder = 'ASC';
      }
      this.applyFiltersAndSort();
    },
    changePage(delta) {
      const newPage = this.currentPage + delta;
      if (newPage >= 1 && newPage <= this.totalPages) {
        this.currentPage = newPage;
      }
    }
  }
}
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

th, td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: left;
  cursor: pointer;
}

th {
  background-color: #f2f2f2;
}

th.sorted {
  background-color: #e0e0e0;
}

.filters {
  margin-bottom: 20px;
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
}

.filters label {
  font-weight: bold;
  margin-right: 10px;
}

.filters select,
.filters input {
  padding: 5px;
  font-size: 14px;
}

.pagination {
  margin-top: 20px;
  text-align: center;
}

button {
  margin: 0 5px;
  padding: 5px 10px;
}
</style>