export type Severity = 'low' | 'moderate' | 'high' | 'critical'
export type Category = 'road_accident' | 'flood' | 'fire' | 'electrical' | 'building_collapse' | 'injury' | 'other'
export interface User { id: string; name: string; email: string; role: 'citizen' | 'responder' | 'moderator' | 'admin'; locale: 'bn' | 'en'; ai_consent: boolean; auto_post_enabled?: boolean; alert_radius_km: number; alert_area_set: boolean; alert_lat?: number | null; alert_lng?: number | null; is_demo: boolean; account_type?: string; organization?: Org | null }
export interface Org { id: string; type: string; name: string; phone: string; lat: number; lng: number; address?: string; country_code?: string; coverage_km?: number; service_hours?: string; verified: boolean; distance_km?: number; source: string; is_demo?: boolean }
export interface DispatchRow { id: number; status: string; distance_km: number; reason: string | null; updated_at: string; organization: { id: string; name: string; type: string; phone: string; verified: boolean } | null; incident?: { id: string; title: string; category: string; severity: string; area_name: string | null; lat: number; lng: number } | null }
export const ORG_TYPES = ['hospital', 'ambulance', 'fire_service', 'police', 'help_center', 'ngo', 'volunteer'] as const
export const ORG_ICON: Record<string, string> = { hospital: '🏥', ambulance: '🚑', fire_service: '🚒', police: '🚓', help_center: '🆘', ngo: '🤝', volunteer: '🙋' }
export interface Region { label: string; source: string; box: [number, number, number, number]; image_width: number; image_height: number; confidence?: number | null }
export interface Media { id: string; url: string; width: number; height: number }
export interface Incident {
  title_translated?: string | null
  description_translated?: string | null
  id: string; title: string; description: string | null; category: Category; severity: Severity; status: string; is_stale: boolean
  is_demo: boolean; ai_assisted: boolean; source?: string; ai_unverified?: boolean
  location: { lat: number; lng: number; area_name: string | null; precision: string }
  reporter: { id?: string | null; avatar?: string | null; name: string | null; anonymous: boolean; is_me: boolean }
  can_manage?: boolean; is_mine?: boolean; edited_at?: string | null
  counts: { confirm: number; dispute: number; comments: number }
  my_reaction: 'confirm' | 'dispute' | null
  media: Media[]
  analysis: { mode: string; hazards: string[]; severity: Severity; requires_human_review: boolean; evidence_regions: Region[] } | null
  occurred_at: string; created_at: string; updated_at: string; verified_at: string | null
  history?: { from: string | null; to: string; note: string | null; by_role: string | null; at: string }[]
  private?: { lat: number; lng: number; reporter_id: string }
}
export interface Analysis {
  id: string; status: string; mode: 'live' | 'demo'; mode_label: string; vision_model: string | null; segmentation_model: string | null
  primary_hazard: Category | null; severity: Severity | null; requires_human_review: boolean; error_code: string | null; latency_ms: number | null
  result: { is_incident_scene?: boolean; summary: string; suggested_title: string; observations: { text: string; kind: string }[]; possible_hazards: { type: string; evidence: string; likelihood: string }[]; uncertainties: string[]; recommended_precautions: string[] } | null
  evidence_regions: Region[]; media: Media | null
}
export interface Notification { id: number; type: string; title: string; body: string | null; incident_id: string | null; read: boolean; created_at: string }
export interface MapPoint { id: string; title: string; title_translated?: string | null; category: Category; severity: Severity; status: string; is_stale: boolean; is_demo: boolean; lat: number; lng: number; area_name: string | null; confirm: number; created_at: string; thumb: string | null }

/** Title in the selected UI language when the AI cache has it, else the original. */
export const ttl = (i: { title: string; title_translated?: string | null }) => (i.title_translated || i.title).replace('[DEMO] ', '')
