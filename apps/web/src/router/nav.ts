import type { NavItem } from '@/features/shared/components/AppShell.vue'

// Sidebar final 5 menu admin — FSD §6.1.
export const NAV_ITEMS: NavItem[] = [
  { label: 'Kuesioner', to: '/questionnaires' },
  { label: 'Data Mahasiswa', to: '/students' },
  { label: 'Data Pegawai', to: '/employees' },
  { label: 'Hasil Analisa Kuesioner', to: '/analytics' },
  { label: 'Pengaturan', to: '/settings/templates' },
]
