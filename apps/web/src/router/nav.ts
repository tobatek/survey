import type { NavItem } from '@/features/shared/components/AppShell.vue'

// Sidebar final 5 menu admin — FSD §6.1.
export const NAV_ITEMS: NavItem[] = [
  { label: 'Kuesioner', to: '/questionnaires', icon: 'clipboard' },
  { label: 'Data Mahasiswa', to: '/students', icon: 'academic-cap' },
  { label: 'Data Pegawai', to: '/employees', icon: 'briefcase' },
  { label: 'Hasil Analisa Kuesioner', to: '/analytics', icon: 'chart-bar' },
  { label: 'Pengaturan', to: '/settings/templates', icon: 'cog' },
  { label: 'Tampilan & Logo', to: '/settings/branding', icon: 'cog' },
]
