export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data?: T
  errors?: Record<string, string[]>
}

export interface PaginatedResponse<T = unknown> {
  success: boolean
  message: string
  data: T[]
  meta: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
  status: number
}
