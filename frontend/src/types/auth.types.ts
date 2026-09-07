export type UserRole =
  | 'admin'
  | 'college_registrar'
  | 'senior_high_registrar'
  | 'cashier'
  | 'college_student'
  | 'senior_high_student'

export type UserLevel = 'college' | 'senior_high' | null

export interface User {
  id:     number
  email:  string
  role:   UserRole
  level:  UserLevel
  permissions?: string[]
}

export interface AuthState {
  user:  User | null
  token: string | null
}

export interface LoginPayload {
  email:    string
  password: string
}

export interface RegisterPayload {
  email:                 string
  password:              string
  password_confirmation: string
  first_name:            string
  last_name:             string
  middle_name?:          string
  gender?:               string
  birthday?:             string
  birthplace?:           string
  student_id?:           string
  program_id?:           number
  program?:              string
  year_level?:           string
  phone_number?:         string
  address?:              string
  level:                 'college' | 'senior_high'
}

export interface AuthResponse {
  token: string
  user:  User
}
