import type { QuestionType } from './store/builder'

export const QUESTION_TYPE_LABELS: Record<QuestionType, string> = {
  short_text: 'Teks Singkat',
  long_text: 'Teks Panjang',
  single_choice: 'Pilihan Tunggal',
  multiple_choice: 'Pilihan Ganda',
  dropdown: 'Dropdown',
  likert: 'Skala Likert',
  matrix: 'Matrix',
  number: 'Angka',
  date: 'Tanggal',
}
