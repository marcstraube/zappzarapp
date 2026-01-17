<script setup lang="ts">
// Fetch backend health status
const { data: health, error } = await useFetch<{
  status: string;
  service: string;
  timestamp: string;
}>('/api/backend/health');
</script>

<template>
  <main class="container">
    <header>
      <h1>Zappzarapp Frontend</h1>
      <p class="subtitle">Nuxt 3 SSR Frontend running on Node.js</p>
    </header>

    <section class="status">
      <h2>Frontend Status</h2>
      <div class="status-card">
        <span class="status-indicator online"></span>
        <span>Nuxt 3 SSR Active</span>
      </div>
    </section>

    <section class="api-status">
      <h2>Backend API Status</h2>
      <div v-if="health" class="api-card">
        <pre>{{ JSON.stringify(health, null, 2) }}</pre>
      </div>
      <div v-else-if="error" class="api-card offline">
        <span class="status-indicator offline"></span>
        <span>Backend not available</span>
      </div>
      <div v-else class="api-card loading">
        <span>Loading...</span>
      </div>
    </section>
  </main>
</template>

<style scoped>
.container {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

header {
  text-align: center;
  margin-bottom: 3rem;
}

h1 {
  color: #00dc82;
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #666;
  font-size: 1.1rem;
}

section {
  margin-bottom: 2rem;
}

h2 {
  color: #333;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  border-bottom: 2px solid #00dc82;
  padding-bottom: 0.5rem;
}

.status-card, .api-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.status-indicator.online {
  background: #00dc82;
  box-shadow: 0 0 8px #00dc82;
}

.status-indicator.offline {
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
}

.api-card pre {
  background: #1a1a2e;
  color: #00dc82;
  padding: 1rem;
  border-radius: 4px;
  overflow-x: auto;
  font-size: 0.875rem;
  width: 100%;
}

.api-card.offline {
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.api-card.loading {
  color: #666;
}
</style>
