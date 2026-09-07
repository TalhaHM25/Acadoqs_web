export const COLLEGE_PROGRAMS = [
  'Bachelor of Science in Information Technology',
  'Bachelor of Science in Computer Engineering',
  'Bachelor of Science in Social Work',
  'Bachelor of Secondary Education, Major in English',
  'Bachelor of Science in Tourism Management',
  'Bachelor of Science in Hospitality Management',
  'Bachelor of Science in Criminology',
  'Bachelor of Science in Business Administration',
]

export const SHS_STRANDS = [
  'STEM',
  'ABM',
  'HUMSS',
  'GAS',
  'TVL - ICT',
  'TVL - Home Economics',
  'TVL - Industrial Arts',
  'TVL - Agri-Fishery Arts',
  'Sports Track',
  'Arts and Design Track',
]

export const COLLEGE_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year']
export const SHS_GRADE_LEVELS    = ['Grade 11', 'Grade 12']

export function programOptions(level: string): string[] {
  return level === 'senior_high' ? SHS_STRANDS : COLLEGE_PROGRAMS
}

export function gradeLevelOptions(level: string): string[] {
  return level === 'senior_high' ? SHS_GRADE_LEVELS : COLLEGE_YEAR_LEVELS
}
