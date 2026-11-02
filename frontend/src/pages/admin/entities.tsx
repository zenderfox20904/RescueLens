import { CATS, SEVS } from '../../lib/i18n'

export type FieldType = 'text' | 'textarea' | 'email' | 'password' | 'number' | 'url' | 'select' | 'bool' | 'location'
export type Field = { key: string; label: string; type?: FieldType; options?: () => [string, string][]; required?: boolean; maxLength?: number }
export type Col = { key: string; label: string; sort?: string; kind?: 'date' | 'number' | 'bool' | 'badge' | 'severity' | 'category' | 'code' }
export type Entity = {
  key: string; label: string; icon: string; adminOnly?: boolean
  title: (r: any) => string
  cols: Col[]
  filters?: Record<string, () => [string, string][]>
  create?: Field[]; edit?: Field[]
  /** Camera feeds are created and edited through their dedicated endpoints (allowlist and SSRF checks). */
  createPath?: string; editPath?: (id: string) => string
  defaultSort?: string
}

const cats = (): [string, string][] => Object.keys(CATS).map((k) => [k, `${CATS[k].icon} ${CATS[k].label}`])
const sevs = (): [string, string][] => Object.keys(SEVS).map((k) => [k, SEVS[k].label])
const list = (...v: string[]) => (): [string, string][] => v.map((x) => [x, x.replace(/_/g, ' ')])

export const ENTITIES: Entity[] = [
  {
    key: 'incidents', label: 'ui.incidents', icon: '🚨', title: (r) => r.title_translated || r.title, defaultSort: 'created_at',
    cols: [{ key: 'title', label: 'title', sort: 'title' }, { key: 'category', label: 'category', kind: 'category' }, { key: 'severity', label: 'severity', sort: 'severity', kind: 'severity' },
      { key: 'status', label: 'ui.status', sort: 'status', kind: 'badge' }, { key: 'area_name', label: 'ui.area' }, { key: 'confirmations', label: 'ui.confirmations', sort: 'confirmations_count', kind: 'number' }, { key: 'created_at', label: 'ui.created', sort: 'created_at', kind: 'date' }],
    filters: { status: list('reported', 'corroborated', 'verified', 'disputed', 'resolved', 'withdrawn', 'merged'), severity: sevs, category: cats, source: list('user', 'auto_post', 'camera') },
    create: [{ key: 'title', label: 'title', required: true, maxLength: 160 }, { key: 'description', label: 'ui.description', type: 'textarea', maxLength: 5000 }, { key: 'category', label: 'category', type: 'select', options: cats, required: true },
      { key: 'severity', label: 'severity', type: 'select', options: sevs, required: true }, { key: 'location', label: 'location', type: 'location', required: true }, { key: 'area_name', label: 'ui.public_area_name', maxLength: 120 }],
    edit: [{ key: 'title', label: 'title', maxLength: 160 }, { key: 'description', label: 'ui.description', type: 'textarea', maxLength: 5000 }, { key: 'category', label: 'category', type: 'select', options: cats },
      { key: 'severity', label: 'severity', type: 'select', options: sevs }, { key: 'location', label: 'location', type: 'location' }, { key: 'area_name', label: 'ui.public_area_name', maxLength: 120 }],
  },
  {
    key: 'users', label: 'ui.users', icon: '👤', title: (r) => r.name, defaultSort: 'created_at',
    cols: [{ key: 'name', label: 'ui.name', sort: 'name' }, { key: 'email', label: 'ui.email', sort: 'email' }, { key: 'role', label: 'ui.role', sort: 'role', kind: 'badge' }, { key: 'locale', label: 'ui.language', kind: 'code' },
      { key: 'incidents', label: 'ui.reports', kind: 'number' }, { key: 'created_at', label: 'ui.created', sort: 'created_at', kind: 'date' }],
    filters: { role: list('citizen', 'responder', 'moderator', 'admin') },
    create: [{ key: 'name', label: 'ui.name', required: true, maxLength: 120 }, { key: 'email', label: 'ui.email', type: 'email', required: true }, { key: 'password', label: 'ui.password', type: 'password', required: true },
      { key: 'locale', label: 'ui.language', maxLength: 12 }, { key: 'country_code', label: 'ui.country', maxLength: 2 }],
    edit: [{ key: 'name', label: 'ui.name', maxLength: 120 }, { key: 'email', label: 'ui.email', type: 'email' }, { key: 'locale', label: 'ui.language', maxLength: 12 }, { key: 'country_code', label: 'ui.country', maxLength: 2 }],
  },
  {
    key: 'comments', label: 'ui.comments', icon: '💬', title: (r) => r.body?.slice(0, 60), defaultSort: 'created_at',
    cols: [{ key: 'body', label: 'ui.comment' }, { key: 'author', label: 'ui.author' }, { key: 'incident_title', label: 'ui.incident' }, { key: 'created_at', label: 'ui.created', sort: 'created_at', kind: 'date' }],
    create: [{ key: 'incident_id', label: 'ui.incident_id', required: true }, { key: 'body', label: 'ui.comment', type: 'textarea', required: true, maxLength: 2000 }],
    edit: [{ key: 'body', label: 'ui.comment', type: 'textarea', maxLength: 2000 }],
  },
  {
    key: 'emergency-resources', label: 'ui.emergency_directory', icon: '☎️', adminOnly: true, title: (r) => `${r.name} · ${r.phone}`, defaultSort: 'name',
    cols: [{ key: 'name', label: 'ui.name', sort: 'name' }, { key: 'phone', label: 'ui.phone', kind: 'code' }, { key: 'category', label: 'category', sort: 'category', kind: 'badge' }, { key: 'country_code', label: 'ui.country', sort: 'country_code', kind: 'code' },
      { key: 'source_name', label: 'ui.source' }, { key: 'verified', label: 'verified', kind: 'bool' }, { key: 'is_active', label: 'ui.active', kind: 'bool' }],
    create: [{ key: 'country_code', label: 'ui.country', required: true, maxLength: 2 }, { key: 'name', label: 'ui.name', required: true }, { key: 'name_bn', label: 'ui.name_bn' }, { key: 'phone', label: 'ui.phone', required: true },
      { key: 'category', label: 'category', type: 'select', options: list('national_emergency', 'police', 'fire', 'ambulance', 'disaster', 'women_children', 'health', 'other'), required: true },
      { key: 'jurisdiction', label: 'ui.jurisdiction' }, { key: 'source_name', label: 'ui.source', required: true }, { key: 'source_url', label: 'ui.source_url', type: 'url', required: true }],
    edit: [{ key: 'name', label: 'ui.name' }, { key: 'name_bn', label: 'ui.name_bn' }, { key: 'phone', label: 'ui.phone' }, { key: 'jurisdiction', label: 'ui.jurisdiction' }, { key: 'source_name', label: 'ui.source' },
      { key: 'source_url', label: 'ui.source_url', type: 'url' }, { key: 'is_active', label: 'ui.active', type: 'bool' }],
  },
  {
    key: 'camera-feeds', label: 'ui.camera_feeds', icon: '📹', adminOnly: true, title: (r) => r.name, defaultSort: 'created_at',
    cols: [{ key: 'name', label: 'ui.name', sort: 'name' }, { key: 'kind', label: 'ui.kind', kind: 'badge' }, { key: 'snapshot_host', label: 'ui.host', kind: 'code' }, { key: 'area_name', label: 'ui.area' },
      { key: 'is_active', label: 'ui.active', kind: 'bool' }, { key: 'frames', label: 'ui.frames', kind: 'number' }, { key: 'last_polled_at', label: 'ui.last_polled', sort: 'last_polled_at', kind: 'date' }],
    filters: { kind: list('snapshot_url', 'upload') },
    createPath: '/mod/admin/camera-feeds', editPath: (id) => `/mod/admin/camera-feeds/${id}`,
    create: [{ key: 'name', label: 'ui.name', required: true, maxLength: 120 }, { key: 'kind', label: 'ui.kind', type: 'select', options: list('snapshot_url', 'upload'), required: true },
      { key: 'snapshot_url', label: 'ui.snapshot_url', type: 'url' }, { key: 'location', label: 'location', type: 'location', required: true }, { key: 'area_name', label: 'ui.area', maxLength: 120 },
      { key: 'interval_seconds', label: 'ui.interval_seconds', type: 'number' }],
    edit: [{ key: 'name', label: 'ui.name', maxLength: 120 }, { key: 'interval_seconds', label: 'ui.interval_seconds', type: 'number' }, { key: 'is_active', label: 'ui.active', type: 'bool' }],
  },
  {
    key: 'organizations', label: 'ui.organizations', icon: '🏥', adminOnly: true, title: (r) => r.name, defaultSort: 'created_at',
    cols: [{ key: 'name', label: 'ui.name', sort: 'name' }, { key: 'type', label: 'ui.kind', sort: 'type', kind: 'badge' }, { key: 'phone', label: 'ui.phone', kind: 'code' }, { key: 'country_code', label: 'ui.country', kind: 'code' },
      { key: 'coverage_km', label: 'ui.coverage_radius', kind: 'number' }, { key: 'verified', label: 'verified', sort: 'verified_at', kind: 'bool' }, { key: 'created_at', label: 'ui.created', sort: 'created_at', kind: 'date' }],
    filters: { type: list('hospital', 'ambulance', 'fire_service', 'police', 'help_center', 'ngo', 'volunteer') },
    create: [{ key: 'type', label: 'ui.kind', type: 'select', options: list('hospital', 'ambulance', 'fire_service', 'police', 'help_center', 'ngo', 'volunteer'), required: true }, { key: 'name', label: 'ui.name', required: true, maxLength: 160 },
      { key: 'phone', label: 'ui.phone', required: true }, { key: 'location', label: 'location', type: 'location', required: true }, { key: 'country_code', label: 'ui.country', maxLength: 2 },
      { key: 'coverage_km', label: 'ui.coverage_radius', type: 'number' }, { key: 'service_hours', label: 'ui.service_hours' }, { key: 'verified', label: 'verified', type: 'bool' }],
    edit: [{ key: 'name', label: 'ui.name', maxLength: 160 }, { key: 'phone', label: 'ui.phone' }, { key: 'location', label: 'location', type: 'location' }, { key: 'coverage_km', label: 'ui.coverage_radius', type: 'number' },
      { key: 'service_hours', label: 'ui.service_hours' }, { key: 'verified', label: 'verified', type: 'bool' }],
  },
  {
    key: 'languages', label: 'ui.languages', icon: '🌐', adminOnly: true, title: (r) => `${r.native_name} · ${r.english_name}`, defaultSort: 'sort',
    cols: [{ key: 'code', label: 'ui.code', sort: 'code', kind: 'code' }, { key: 'native_name', label: 'ui.native_name' }, { key: 'english_name', label: 'ui.english_name', sort: 'english_name' },
      { key: 'rtl', label: 'RTL', kind: 'bool' }, { key: 'hand_translated', label: 'ui.hand_translated', kind: 'bool' }, { key: 'enabled', label: 'ui.enabled', kind: 'bool' }],
    create: [{ key: 'code', label: 'ui.code', required: true, maxLength: 10 }, { key: 'native_name', label: 'ui.native_name', required: true }, { key: 'english_name', label: 'ui.english_name', required: true },
      { key: 'rtl', label: 'RTL', type: 'bool' }, { key: 'enabled', label: 'ui.enabled', type: 'bool' }],
    edit: [{ key: 'native_name', label: 'ui.native_name' }, { key: 'english_name', label: 'ui.english_name' }, { key: 'rtl', label: 'RTL', type: 'bool' }, { key: 'enabled', label: 'ui.enabled', type: 'bool' }],
  },
]
export const entityByKey = (k?: string) => ENTITIES.find((e) => e.key === k)
