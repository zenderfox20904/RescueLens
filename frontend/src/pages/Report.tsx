import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { useAuth } from '../lib/auth'
import { CATS, SEVS, useI18n } from '../lib/i18n'
import type { Analysis, Incident } from '../lib/types'
import { drafts, newKey } from '../lib/offline'
import { RegionOverlay, StatusBadge } from '../components/PostCard'
import { Sk } from '../components/Skeleton'
import { ICamera, IPin, ISpark, IX } from '../components/Icons'
import { useToast } from '../components/Toast'

type Step = 'photo' | 'analyzing' | 'review' | 'duplicates' | 'posting'
const DHAKA = { lat: 23.8103, lng: 90.4125 }

export default function Report() {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const nav = useNavigate()
  const qc = useQueryClient()
  const toast = useToast()
  const fileRef = useRef<HTMLInputElement>(null)
  const [step, setStep] = useState<Step>('photo')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [mediaId, setMediaId] = useState<string | null>(null)
  const [analysis, setAnalysis] = useState<Analysis | null>(null)
  const [consentAi, setConsentAi] = useState(true)
  const [scenario, setScenario] = useState('')
  const [form, setForm] = useState({ title: '', description: '', category: 'road_accident', severity: 'moderate', area_name: '', lat: DHAKA.lat, lng: DHAKA.lng, is_anonymous: false })
  const [locSet, setLocSet] = useState(false)
  const [confirmPublic, setConfirmPublic] = useState(false)
  const [dups, setDups] = useState<Incident[]>([])
  const [err, setErr] = useState<string | null>(null)
  const [idemKey] = useState(newKey)
  const health = useQuery({ queryKey: ['health'], queryFn: () => api<any>('/health'), staleTime: 60_000 })
  const demo = health.data?.ai?.mode !== 'live'

  useEffect(() => { if (!user) nav('/login') }, [user])
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview) }, [preview])

  const locate = () => navigator.geolocation?.getCurrentPosition(
    (p) => { setForm((f) => ({ ...f, lat: +p.coords.latitude.toFixed(6), lng: +p.coords.longitude.toFixed(6) })); setLocSet(true) },
    () => toast(lang === 'bn' ? 'অবস্থান পাওয়া যায়নি, ম্যানুয়ালি দিন' : 'Location unavailable, enter it manually'),
    { enableHighAccuracy: true, timeout: 8000 })

  const pick = (f: File | undefined) => {
    if (!f) return
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return setErr('JPEG, PNG or WebP only')
    if (f.size > 8 * 1024 * 1024) return setErr('Max 8 MB')
    setErr(null); setFile(f); setPreview(URL.createObjectURL(f)); setMediaId(null); setAnalysis(null)
  }

  const analyze = async () => {
    if (!file) return
    if (!navigator.onLine) { setStep('review'); return }
    setStep('analyzing'); setErr(null)
    try {
      let id = mediaId
      if (!id) { const fd = new FormData(); fd.append('image', file); id = (await api<{ id: string }>('/media', { body: fd })).id; setMediaId(id) }
      if (!consentAi) { setStep('review'); return }
      const r = await api<{ analysis: Analysis }>('/analyses', { body: { media_id: id, language: lang, ai_consent: true, ...(demo && scenario ? { demo_scenario: scenario } : {}) } })
      const a = r.analysis; setAnalysis(a)
      if (a.result) setForm((f) => ({ ...f, title: a.result!.suggested_title.slice(0, 160), description: a.result!.summary, category: a.primary_hazard || f.category, severity: a.severity || f.severity }))
      setStep('review')
    } catch (e) { setErr(errMsg(e)); setStep(mediaId ? 'review' : 'photo') }
  }

  const payload = () => ({ ...form, consent_public: true, ...(analysis ? { analysis_id: analysis.id } : {}), media_ids: mediaId ? [mediaId] : [] })

  const checkDuplicates = async () => {
    if (!confirmPublic || !form.title.trim()) return
    if (!navigator.onLine) {
      const reader = new FileReader()
      reader.onload = () => { drafts.save({ key: idemKey, created: Date.now(), payload: { ...payload(), media_ids: undefined }, image: reader.result as string }); toast(t('offlineSaved')); nav('/feed') }
      if (file) reader.readAsDataURL(file); else { drafts.save({ key: idemKey, created: Date.now(), payload: payload() }); toast(t('offlineSaved')); nav('/feed') }
      return
    }
    try {
      const r = await api<{ data: Incident[] }>(`/incident-duplicates?category=${form.category}&lat=${form.lat}&lng=${form.lng}`)
      if (r.data.length) { setDups(r.data); setStep('duplicates') } else await submit()
    } catch (e) { setErr(errMsg(e)) }
  }

  const submit = async () => {
    setStep('posting'); setErr(null)
    try {
      const r = await api<{ data: Incident; nearby_notified: number }>('/incidents', { body: payload(), headers: { 'Idempotency-Key': idemKey } })
      qc.invalidateQueries({ queryKey: ['feed'] }); qc.invalidateQueries({ queryKey: ['map'] })
      toast(lang === 'bn' ? `পোস্ট হয়েছে। কাছের ${r.nearby_notified} জনকে জানানো হয়েছে।` : `Posted. ${r.nearby_notified} people nearby notified.`)
      nav(`/incidents/${r.data.id}`)
    } catch (e) { setErr(errMsg(e)); setStep('review') }
  }

  const join = async (d: Incident) => {
    try {
      await api(`/incidents/${d.id}/reactions`, { body: { type: 'confirm', ...(mediaId ? { media_id: mediaId } : {}) } })
      qc.invalidateQueries({ queryKey: ['feed'] }); toast(lang === 'bn' ? 'আপনার নিশ্চিতকরণ যোগ হয়েছে' : 'Your confirmation was added'); nav(`/incidents/${d.id}`)
    } catch (e) { setErr(errMsg(e)) }
  }

  return (
    <div className="modal-bg" style={{ position: 'static', background: 'transparent', minHeight: 'calc(100vh - 56px)' }}>
      <div className="card modal fade-in">
        <div className="modal-head">{lang === 'bn' ? 'রিপোর্ট তৈরি করুন' : 'Create report'}<button className="iconbtn" style={{ position: 'absolute', right: 12, top: 10 }} onClick={() => nav(-1)} aria-label={t('cancel')}><IX size={20} /></button></div>
        <div style={{ padding: 16, display: 'grid', gap: 12 }}>
          <div className="sos small">📞 {lang === 'bn' ? 'জীবন ঝুঁকিতে থাকলে আগে' : 'If life is at risk, first call'} <a href="tel:999">999</a></div>

          {preview ? (
            <div style={{ position: 'relative', borderRadius: 8, overflow: 'hidden', background: '#000' }}>
              <div style={{ position: 'relative', width: 'fit-content', margin: '0 auto' }}>
                <img src={preview} alt="" style={{ maxWidth: '100%', maxHeight: 380, display: 'block' }} />
                {analysis && <RegionOverlay regions={analysis.evidence_regions} />}
                {step === 'analyzing' && <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(90deg,transparent,rgba(24,119,242,.35),transparent)', backgroundSize: '200% 100%', animation: 'shimmer 1.2s infinite' }} />}
              </div>
              {step === 'photo' && <button className="iconbtn" style={{ position: 'absolute', top: 8, right: 8 }} onClick={() => { setFile(null); setPreview(null) }}><IX size={18} /></button>}
            </div>
          ) : (
            <button className="card" style={{ border: '2px dashed var(--divider)', padding: 40, background: 'var(--input)', color: 'var(--text)', borderRadius: 8 }} onClick={() => fileRef.current?.click()}>
              <ICamera size={36} /><div style={{ fontWeight: 600, marginTop: 8 }}>{t('photo')}</div><div className="small muted">JPEG, PNG, WebP · 8 MB · {lang === 'bn' ? 'লোকেশন মেটাডেটা মুছে ফেলা হয়' : 'location metadata is stripped'}</div>
            </button>
          )}
          <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" capture="environment" hidden onChange={(e) => pick(e.target.files?.[0])} />

          {step === 'photo' && file && <>
            <label className="row small"><input type="checkbox" checked={consentAi} onChange={(e) => setConsentAi(e.target.checked)} /> {t('aiConsent')}</label>
            {demo && <div className="small"><span className="badge badge-demo">DEMO</span> {lang === 'bn' ? 'লাইভ AI কনফিগার নেই। ডেমো দৃশ্য বেছে নিন:' : 'Live AI is not configured. Pick a demo scenario:'}
              <select className="input" style={{ marginTop: 6 }} value={scenario} onChange={(e) => setScenario(e.target.value)}>
                <option value="">{lang === 'bn' ? 'স্বয়ংক্রিয়' : 'Automatic'}</option>
                {['road_accident', 'flood', 'fire', 'electrical', 'building_collapse'].map((s) => <option key={s} value={s}>{CATS[s][lang]}</option>)}
              </select></div>}
            <button className="btn btn-grad" style={{ padding: 12 }} onClick={analyze}><ISpark size={18} /> {consentAi ? t('analyze') : t('next')}</button>
          </>}

          {step === 'analyzing' && <div><b>✨ Muse Spark {lang === 'bn' ? 'ছবি বিশ্লেষণ করছে, SAM 3.1 ঝুঁকি চিহ্নিত করছে…' : 'is analyzing, SAM 3.1 is highlighting hazards…'}</b><Sk h={14} style={{ marginTop: 10 }} /><Sk w="80%" h={14} style={{ marginTop: 8 }} /><Sk w="60%" h={14} style={{ marginTop: 8 }} /></div>}

          {analysis && step !== 'analyzing' && (
            <div className="card" style={{ padding: 12, background: 'var(--primary-soft)', boxShadow: 'none' }}>
              <div className="row"><ISpark size={16} /><b className="grow">{t('aiSuggestion')}</b>{analysis.mode === 'demo' && <span className="badge badge-demo">DEMO</span>}</div>
              <div className="small muted" style={{ marginTop: 4 }}>{analysis.mode_label}</div>
              {analysis.result && <>
                <ul className="small" style={{ margin: '8px 0', paddingLeft: 18 }}>{analysis.result.possible_hazards.map((h, k) => <li key={k}><b>{CATS[h.type]?.[lang] ?? h.type}</b> ({h.likelihood.replace('_', ' ')}): {h.evidence}</li>)}</ul>
                {!!analysis.result.recommended_precautions.length && <div className="small"><b>{lang === 'bn' ? 'সতর্কতা' : 'Precautions'}:</b> {analysis.result.recommended_precautions.join(' • ')}</div>}
                {!!analysis.result.uncertainties.length && <div className="small muted"><b>{lang === 'bn' ? 'অনিশ্চয়তা' : 'Uncertain'}:</b> {analysis.result.uncertainties.join(' • ')}</div>}
              </>}
              <div className="small muted" style={{ marginTop: 6 }}>{analysis.vision_model}{analysis.segmentation_model ? ' + ' + analysis.segmentation_model : ''}{analysis.latency_ms ? ` · ${analysis.latency_ms} ms` : ''}</div>
            </div>
          )}

          {(step === 'review' || step === 'posting') && <>
            <label className="small muted">{t('title')}<input className="input" maxLength={160} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></label>
            <label className="small muted">{t('description')}<textarea className="input" rows={3} maxLength={2000} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
            <div className="small muted">{t('category')}</div>
            <div className="chips">{Object.entries(CATS).map(([k, v]) => <button key={k} type="button" className={'chip' + (form.category === k ? ' on' : '')} onClick={() => setForm({ ...form, category: k })}>{v.icon} {v[lang]}</button>)}</div>
            <div className="small muted">{t('severity')}</div>
            <div className="chips">{Object.entries(SEVS).map(([k, v]) => <button key={k} type="button" className={'chip' + (form.severity === k ? ' on' : '')} onClick={() => setForm({ ...form, severity: k })}>{v[lang]}</button>)}</div>
            <div className="small muted">{t('location')}</div>
            <div className="row"><button type="button" className="btn" onClick={locate}><IPin size={18} /> {t('useMyLocation')}</button><span className="small muted">{locSet ? `✓ ${form.lat.toFixed(4)}, ${form.lng.toFixed(4)}` : (lang === 'bn' ? 'ডিফল্ট: ঢাকা' : 'Default: Dhaka')}</span></div>
            <div className="row"><input className="input" type="number" step="0.000001" value={form.lat} onChange={(e) => { setForm({ ...form, lat: +e.target.value }); setLocSet(true) }} aria-label="lat" /><input className="input" type="number" step="0.000001" value={form.lng} onChange={(e) => { setForm({ ...form, lng: +e.target.value }); setLocSet(true) }} aria-label="lng" /></div>
            <input className="input" maxLength={120} placeholder={lang === 'bn' ? 'এলাকার নাম (যেমন মিরপুর ১০)' : 'Area name (e.g. Mirpur 10)'} value={form.area_name} onChange={(e) => setForm({ ...form, area_name: e.target.value })} />
            <div className="small muted">{lang === 'bn' ? 'প্রকাশ্যে শুধু আনুমানিক অবস্থান (প্রায় ১০০ মিটার) দেখানো হয়।' : 'Only an approximate location (about 100 m) is shown publicly.'}</div>
            <label className="row small"><input type="checkbox" checked={form.is_anonymous} onChange={(e) => setForm({ ...form, is_anonymous: e.target.checked })} /> {t('anonymous')}</label>
            <label className="row small" style={{ fontWeight: 600 }}><input type="checkbox" checked={confirmPublic} onChange={(e) => setConfirmPublic(e.target.checked)} /> {t('confirmPost')}</label>
            <button className="btn btn-primary" style={{ padding: 12 }} disabled={!confirmPublic || !form.title.trim() || step === 'posting'} onClick={checkDuplicates}>{step === 'posting' ? '…' : t('post')}</button>
          </>}

          {step === 'duplicates' && <>
            <b>⚠️ {t('duplicateFound')}</b>
            {dups.map((d) => (
              <div key={d.id} className="card" style={{ padding: 12 }}>
                <div className="row"><b className="grow">{d.title}</b><StatusBadge i={d} /></div>
                <div className="small muted">{d.location.area_name} · 👁️ {d.counts.confirm}</div>
                <button className="btn btn-primary" style={{ marginTop: 8 }} onClick={() => join(d)}>{t('joinExisting')}</button>
              </div>))}
            <button className="btn" onClick={submit}>{t('postAnyway')}</button>
          </>}
          {err && <div role="alert" className="small" style={{ color: 'var(--danger)' }}>{err}</div>}
        </div>
      </div>
    </div>
  )
}
