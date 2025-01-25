<template>
  <div>
    <h2>Spot Prices</h2>

    <div class="minimal-filters">
      <label>
        Instance Type:
        <input v-model="instanceTypeSearch" placeholder="Search instance type" />
      </label>
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
        Price Range: Min
        <input type="number" v-model.number="minPrice" :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price" placeholder="Min Price" />
        Max
        <input type="number" v-model.number="maxPrice" :min="metadata.price_range.min_price"
          :max="metadata.price_range.max_price" placeholder="Max Price" />
      </label>
    </div>

    <!-- Spot Prices Table -->
    <table class="minimal-table">
      <thead>
        <tr>
          <th @click="toggleSort('region')" :class="{ sorted: sortField === 'region' }">
            Region
            <span v-if="sortField === 'region'">{{ sortOrder === 'ASC' ? '▲' : '▼' }}</span>
          </th>
          <th @click="toggleSort('instance_type')" :class="{ sorted: sortField === 'instance_type' }">
            Instance Type
            <span v-if="sortField === 'instance_type'">{{ sortOrder === 'ASC' ? '▲' : '▼' }}</span>
          </th>
          <th @click="toggleSort('product_description')" :class="{ sorted: sortField === 'product_description' }">
            Product Description
            <span v-if="sortField === 'product_description'">{{ sortOrder === 'ASC' ? '▲' : '▼' }}</span>
          </th>
          <th @click="toggleSort('spot_price')" :class="{ sorted: sortField === 'spot_price' }">
            Spot Price
            <span v-if="sortField === 'spot_price'">{{ sortOrder === 'ASC' ? '▲' : '▼' }}</span>
          </th>
          <th @click="toggleSort('timestamp')" :class="{ sorted: sortField === 'timestamp' }">
            Time Updated
            <span v-if="sortField === 'timestamp'">{{ sortOrder === 'ASC' ? '▲' : '▼' }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(price, idx) in paginatedPrices" :key="idx">
          <td>{{ price.region }}</td>
          <td>{{ price.instance_type }}</td>
          <td>{{ price.product_description }}</td>
          <td>{{ price.spot_price.toFixed(3) }}</td>
          <td>{{ price.timestamp }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="minimal-pagination">
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
      instanceTypeSearch: '', // New data property for free text filter
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
    maxPrice: 'applyFiltersAndSort',
    instanceTypeSearch: 'applyFiltersAndSort' // Watcher for the free text filter
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
    // Convert the search text to lowercase for case-insensitive comparison
    const searchText = this.instanceTypeSearch.trim().toLowerCase();

    this.filteredPrices = this.allPrices.filter(price => {
      const regionMatch = !this.selectedRegion || price.region === this.selectedRegion;
      const productDescriptionMatch = !this.selectedProductDescription || price.product_description === this.selectedProductDescription;
      const minPriceMatch = this.minPrice === null || price.spot_price >= this.minPrice;
      const maxPriceMatch = this.maxPrice === null || price.spot_price <= this.maxPrice;
      
      // New condition for instance type search
      const instanceTypeMatch = !searchText || price.instance_type.toLowerCase().includes(searchText);

      return regionMatch && productDescriptionMatch && minPriceMatch && maxPriceMatch && instanceTypeMatch;
    });

    // Sorting logic remains the same
    this.filteredPrices.sort((a, b) => {
      const field = this.sortField;
      const valA = this.parseNumeric(a[field]);
      const valB = this.parseNumeric(b[field]);

      if (typeof valA === 'number' && typeof valB === 'number') {
        return this.sortOrder === 'ASC' ? valA - valB : valB - valA;
      } else {
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
.minimal-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background-color: #fff;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.minimal-table th,
.minimal-table td {
  border: 1px solid #ddd;
  padding: 12px;
  text-align: left;
}

.minimal-table th {
  background-color: #f5f5f5;
  cursor: pointer;
  font-weight: bold;
}

.minimal-table tbody tr:hover {
  background-color: #e9ecef;
}

.minimal-table th.sorted {
  background-color: #007bff;
  color: #fff;
}

.minimal-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  justify-content: center;
  margin-bottom: 20px;
  padding: 20px;
  background-color: #f9f9f9;
  border: 1px solid #ddd;
  border-radius: 8px;
}

.minimal-filters label {
  font-weight: bold;
  color: #333;
}

.minimal-filters select,
.minimal-filters input {
  padding: 10px;
  font-size: 16px;
  border: 1px solid #ccc;
  border-radius: 4px;
  /* width: 200px; */
}

.minimal-filters input {
  text-align: center;
}

.minimal-filters input::placeholder {
  color: #999;
}


.minimal-pagination {
  margin-top: 20px;
  text-align: center;
}

.minimal-pagination button {
  padding: 8px 16px;
  margin: 0 5px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.minimal-pagination button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

button {
  margin: 0 5px;
  padding: 5px 10px;
}
</style>