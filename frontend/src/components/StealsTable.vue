<template>
  <div>
    <h2>Steals</h2>

    <!-- Filters -->
    <div class="filters">
      <label>
        Region:
        <select v-model="filters.region">
          <option value="">All</option>
          <option v-for="region in metaData.regions" :key="region" :value="region">{{ region }}</option>
        </select>
      </label>

      <label>
        Product Description:
        <select v-model="filters.product_description">
          <option value="">All</option>
          <option v-for="desc in metaData.product_descriptions" :key="desc" :value="desc">{{ desc }}</option>
        </select>
      </label>

      <label>
        Steal Type:
        <select v-model="filters.steal_type">
          <option value="">All</option>
          <option v-for="type in metaData.steal_types" :key="type" :value="type">{{ type }}</option>
        </select>
      </label>
    </div>

    <!-- Steals Table -->
    <table>
      <thead>
        <tr>
          <th>Region</th>
          <th>Instance Type</th>
          <th>Product Description</th>
          <th>Spot Price</th>
          <th>Availability Zone</th>
          <th>Steal Type</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(deal, idx) in paginatedSteals" :key="idx">
          <td>{{ deal.region }}</td>
          <td>{{ deal.instance_type }}</td>
          <td>{{ deal.product_description }}</td>
          <td>{{ deal.spot_price }}</td>
          <td>{{ deal.availability_zone }}</td>
          <td>{{ deal.steal_type }}</td>
          <td>{{ deal.timestamp }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
      <button @click="prevPage" :disabled="currentPage === 1">Previous</button>
      <span>Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages">Next</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'StealsTable',
  data() {
    return {
      steals: [],
      metaData: {
        regions: [],
        product_descriptions: [],
        steal_types: [],
      },
      filters: {
        region: '',
        product_description: '',
        steal_type: ''
      },
      currentPage: 1,
      pageSize: 10
    }
  },
  watch: {
    // Watch for changes in filter selections and fetch data automatically
    filters: {
      deep: true,
      handler() {
        this.fetchSteals();
      }
    }
  },
  async mounted() {
    await this.fetchMetaData();
    await this.fetchSteals();
  },
  computed: {
    totalPages() {
      return Math.ceil(this.steals.length / this.pageSize);
    },
    paginatedSteals() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.steals.slice(start, start + this.pageSize);
    }
  },
  methods: {
    async fetchMetaData() {
      try {
        const res = await axios.get('http://localhost:8080/api/steals/meta');
        this.metaData = res.data;
      } catch (error) {
        console.error('Error fetching metadata:', error);
      }
    },
    async fetchSteals() {
      try {
        const params = {
          region: this.filters.region || undefined,
          product_description: this.filters.product_description || undefined,
          steal_type: this.filters.steal_type || undefined
        };

        const res = await axios.get('http://localhost:8080/api/steals', { params });
        this.steals = res.data;
        this.currentPage = 1;
      } catch (error) {
        console.error('Error fetching steals:', error);
      }
    },
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
      }
    },
    prevPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
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
}

th {
  background-color: #f2f2f2;
}

.filters {
  margin-bottom: 20px;
}

.filters label {
  margin-right: 15px;
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
