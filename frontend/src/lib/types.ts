export type Severity = 'low' | 'moderate' | 'high' | 'critical'
export type Category = 'road_accident' | 'flood' | 'fire' | 'electrical' | 'building_collapse' | 'injury' | 'other'
export interface User { id: string; name: string; email: string; role: 'citizen' | 'responder' | 'moderator' | 'admin'; locale: 'bn' | 'en'; ai_consent: boolean; auto_post_enabled?: boolean; alert_radius_km: number; alert_area_set: boolean; is_demo: boolean }
export interface Region { label: string; source: string; box: [number, number, number, number]; image_width: number; image_height: number; confidence?: number | null }
export interface Media { id: string; url: string; width: number; height: number }
export interface Incident {
  id: string; title: string; description: string | null; category: Category; severity: Severity; status: string; is_stale: boolean
  is_demo: boolean; ai_assisted: boolean; source?: string; ai_unverified?: boolean
  location: { lat: number; lng: number; area_name: string | null; precision: string }
  reporter: { name: string | null; anonymous: boolean; is_me: boolean }
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
export interface MapPoint { id: string; title: string; category: Category; severity: Severity; status: string; is_stale: boolean; is_demo: boolean; lat: number; lng: number; area_name: string | null; confirm: number; created_at: string; thumb: string | null }
