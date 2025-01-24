<template>
  <div>
    <h2>Steals</h2>
    <table>
      <thead>
        <tr>
          <th>Region</th>
          <th>Instance Type</th>
          <th>Spot Price</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(deal, idx) in steals" :key="idx">
          <td>{{ deal.region }}</td>
          <td>{{ deal.instance_type }}</td>
          <td>{{ deal.spot_price }}</td>
          <td>{{ deal.timestamp }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'StealsTable',
  data() {
    return {
      steals: []
    }
  },
  async mounted() {
    const res = await axios.get('http://localhost:8080/api/steals')
    this.steals = res.data
  }
}
</script>
