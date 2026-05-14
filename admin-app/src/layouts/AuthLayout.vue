<script setup lang="ts"></script>

<template>
  <div class="auth-shell">
    <div class="auth-card">
      <div class="auth-brand">
        <i class="pi pi-car" style="font-size: 2rem; color: var(--p-primary-color)" />
        <h1>Vielimousine Admin</h1>
      </div>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--p-surface-100), var(--p-surface-200));
}
.auth-card {
  background: var(--p-surface-0);
  padding: 2.5rem;
  border-radius: 1rem;
  box-shadow: 0 12px 40px rgba(0,0,0,0.1);
  width: 100%;
  max-width: 420px;
}
.auth-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--p-surface-200);
}
.auth-brand h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}
</style>
