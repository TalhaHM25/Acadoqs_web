export type RequestStatus =
  | 'payment_verified'
  | 'completed'
  | 'rejected'

export interface DocumentCategory {
  id:             number
  name:           string
  slug:           string
  level:          'college' | 'senior_high' | 'all'
  document_types: DocumentType[]
}

export interface DocumentType {
  id:                 number
  category_id:        number
  category_name:      string
  name:               string
  slug:               string
  description:        string | null
  base_fee:           number
  certified_copy_fee: number
  required_fields:    string[]
  processing_days:    number
  level:              'college' | 'senior_high' | 'all'
  is_active:          number | boolean
}

export interface DocumentRequestItem {
  id:                number
  request_id:        number
  document_type_id:  number
  document_type_name: string
  copies:            number
  is_certified_copy: boolean
  item_fee:          number
  special_data:      Record<string, string> | null
}

export interface DocumentRequest {
  id:               number
  request_number:   string
  document_type_id: number | null
  document_type_name: string
  category_name:    string
  status:           RequestStatus
  level:            'college' | 'senior_high' | null
  request_source:   'online' | 'kiosk' | 'mobile'
  purpose:          string
  copies:           number
  is_certified_copy: boolean
  total_fee:        number

  is_walkin:        boolean
  walkin_name:      string | null
  walkin_phone:     string | null
  walkin_email:     string | null

  req_student_id:   string | null
  req_last_name:    string
  req_first_name:   string
  req_middle_name:  string | null
  req_program:      string | null
  grade_level:      string | null
  request_semester: '1st' | '2nd' | null
  req_email:        string
  req_phone:        string | null

  is_representative: boolean
  rep_name:          string | null
  rep_relationship:  string | null

  rejection_reason:  string | null
  rejected_by_email?: string | null
  rejected_at?:       string | null
  expected_release_at?: string | null
  admin_notes:       string | null
  created_at:        string
  updated_at:        string

  status_logs?: StatusLog[]
  payment?:     PaymentRecord | null
  items?:       DocumentRequestItem[]
  attachments?: RequestAttachment[]
}

export interface RequestAttachment {
  id:          number
  request_id:  number
  label:       string
  file_path:   string
  file_name:   string
  file_size:   number
  mime_type:   string
  uploaded_by: number
  created_at:  string
}

export interface StatusLog {
  id:               number
  from_status:      RequestStatus | null
  to_status:        RequestStatus
  notes:            string | null
  changed_by_email: string
  created_at:       string
}

export interface PaymentRecord {
  id:               number
  request_id:       number
  amount:           number
  payment_method:   'paymongo' | 'gcash' | string
  payment_method_id: number | null
  reference_number: string | null
  proof_path:       string | null
  proof_filename:   string | null
  paymongo_checkout_session_id?: string | null
  paymongo_checkout_url?:        string | null
  paymongo_payment_intent_id?:   string | null
  status:           'pending' | 'verified' | 'rejected'
  rejection_reason: string | null
  submitted_at:     string | null
  verified_at:      string | null
}

// ── New request payload (multi-doc) ──────────────────────────

export interface RequestItemPayload {
  document_type_id:   number
  copies:             number
  is_certified_copy:  boolean
  special_data?:      Record<string, string>
}

export interface CreateRequestPayload {
  items:             RequestItemPayload[]
  purpose:           string
  payment_method_id?: number

  req_last_name:     string
  req_first_name:    string
  req_middle_name?:  string
  req_student_id?:   string
  req_program?:      string
  grade_level:       string
  request_semester:  '1st' | '2nd'
  req_email:         string
  req_phone:         string

  is_representative?: boolean
  rep_name?:          string
  rep_relationship?:  string
}

export interface RequestCheckout {
  checkout_token: string
  checkout_session_id: string
  checkout_url: string
  amount: number
}
