import { ref } from 'vue';

export function useBulkSave<T>(saveFn: (changes: T[]) => Promise<void>) {
  const dirty = ref<Map<string, T>>(new Map() as Map<string, T>);
  const saving = ref(false);

  function markDirty(key: string, value: T): void {
    (dirty.value as Map<string, T>).set(key, value);
    dirty.value = new Map(dirty.value as Map<string, T>);
  }

  function isDirty(key: string): boolean {
    return dirty.value.has(key);
  }

  function reset(): void {
    (dirty.value as Map<string, T>).clear();
    dirty.value = new Map();
  }

  async function saveAll(): Promise<void> {
    if (dirty.value.size === 0) return;
    saving.value = true;
    try {
      await saveFn(Array.from((dirty.value as Map<string, T>).values()));
      reset();
    } finally {
      saving.value = false;
    }
  }

  return { dirty, saving, markDirty, isDirty, reset, saveAll };
}
