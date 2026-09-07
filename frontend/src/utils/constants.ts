import type { RequestStatus } from '@/types/request.types'

export const STATUS_LABELS: Record<RequestStatus, string> = {
  payment_verified:  'Payment Verified',
  completed:         'Completed',
  rejected:          'Rejected',
}

export const STATUS_COLORS: Record<RequestStatus, string> = {
  payment_verified:  'bg-green-100 text-green-700',
  completed:         'bg-green-200 text-green-800',
  rejected:          'bg-red-100 text-red-700',
}

export const CANCELLABLE_STATUSES: RequestStatus[] = []
