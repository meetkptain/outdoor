export interface PricingTier {
  up_to: number
  price: number
}

export interface PricingConfig {
  model?: 'per_participant' | 'tiered' | 'fixed'
  base_price?: number
  deposit_amount?: number
  tiers?: PricingTier[]
}

export interface ConstraintsConfig {
  participants?: {
    min?: number
    max?: number
  }
  required_metadata?: string[]
  enums?: Record<string, string[]>
}

export interface ActivityMetadata {
  session_strategy?: string
  workflow?: string
  [key: string]: unknown
}

export interface Activity {
  id: number
  organization_id: number
  activity_type: string
  name: string
  description?: string
  duration_minutes?: number
  pricing_config?: PricingConfig
  constraints_config?: ConstraintsConfig
  metadata?: ActivityMetadata
}

