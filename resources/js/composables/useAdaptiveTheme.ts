import { ref, watch } from 'vue';

export type Theme = 'light' | 'dark';

export function useAdaptiveTheme(initial: Theme = 'light') {
  const theme = ref<Theme>(initial);

  const apply = (value: Theme) => {
    document.documentElement.setAttribute('data-theme', value);
  };

  apply(initial);

  watch(theme, (newValue) => {
    apply(newValue);
  });

  const setTheme = (value: Theme) => {
    theme.value = value;
    apply(value);
  };

  return { theme, setTheme };
}
