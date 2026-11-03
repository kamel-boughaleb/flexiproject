import type { t, tc } from '@/lib/i18n';

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        $t: typeof t;
        $tc: typeof tc;
    }
}

export {};
