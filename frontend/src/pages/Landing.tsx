import { Link, Navigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { useI18n } from '../lib/i18n'
import { Wordmark } from '../components/Logo'

export default function Landing() {
  const { user } = useAuth()
  const { lang, setLang } = useI18n()
  if (user) return <Navigate to="/feed" replace />
  const bn = lang === 'bn'
  const steps = bn
    ? [['📸', 'ছবি তুলুন', 'ঘটনাস্থলের ছবি তুলুন বা আপলোড করুন।'], ['✨', 'AI খসড়া', 'Muse Spark বর্ণনা ও ঝুঁকি প্রস্তাব করে, SAM 3.1 প্রমাণ চিহ্নিত করে।'], ['✅', 'আপনি নিশ্চিত করুন', 'সম্পাদনা করুন, আপনার সম্মতি ছাড়া কিছুই প্রকাশ হয় না।'], ['📍', 'কাছের সতর্কতা', 'কাছাকাছি মানুষ সতর্কতা পায়, মডারেটর যাচাই করে।']]
    : [['📸', 'Snap', 'Take or upload a photo of the scene.'], ['✨', 'AI draft', 'Muse Spark suggests a description, SAM 3.1 highlights the evidence.'], ['✅', 'You confirm', 'Edit it. Nothing is published without your consent.'], ['📍', 'Nearby alerts', 'People nearby are alerted and moderators verify.']]
  return (
    <div>
      <div style={{ background: 'linear-gradient(135deg, var(--grad-from), var(--grad-to))', color: '#fff', padding: '56px 16px' }}>
        <div style={{ maxWidth: 980, margin: '0 auto' }}>
          <div className="row"><div className="grow" style={{ filter: 'brightness(0) invert(1)' }}><Wordmark size={44} /></div>
            <button className="btn" onClick={() => setLang(bn ? 'en' : 'bn')}>{bn ? 'English' : 'বাংলা'}</button></div>
          <h1 style={{ fontSize: 44, lineHeight: 1.1, margin: '40px 0 12px' }}>{bn ? 'দুর্ঘটনা দেখলে, সবাইকে জানান।' : 'See an emergency. Let your community know.'}</h1>
          <p style={{ fontSize: 20, opacity: 0.92, maxWidth: 640 }}>{bn ? 'বাংলাদেশের জন্য AI সহায়িত কমিউনিটি রিপোর্টিং: সড়ক দুর্ঘটনা, জলাবদ্ধতা, আগুন, ঝুলন্ত বিদ্যুতের তার।' : 'AI assisted community incident reporting for Bangladesh: road accidents, waterlogging, fires and fallen wires.'}</p>
          <div className="row" style={{ marginTop: 24, flexWrap: 'wrap' }}>
            <Link to="/register" className="btn" style={{ padding: '12px 20px', fontSize: 17 }}>{bn ? 'শুরু করুন' : 'Get started'}</Link>
            <Link to="/feed" className="btn" style={{ padding: '12px 20px', fontSize: 17, background: 'transparent', color: '#fff', border: '1px solid #fff' }}>{bn ? 'লাইভ ফিড দেখুন' : 'View live feed'}</Link>
          </div>
        </div>
      </div>
      <div style={{ maxWidth: 980, margin: '24px auto', padding: 16 }}>
        <div className="sos">📞 {bn ? 'জীবন ঝুঁকিতে থাকলে আগে জাতীয় জরুরি সেবা' : 'If a life is at risk, call the National Emergency Service'} <a href="tel:999"><b>999</b></a> {bn ? '(সূত্র: 999.gov.bd)' : '(source: 999.gov.bd)'}</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12, marginTop: 16 }}>
          {steps.map(([i, h, p]) => <div key={h} className="card" style={{ padding: 16 }}><div style={{ fontSize: 32 }}>{i}</div><b>{h}</b><p className="muted small">{p}</p></div>)}
        </div>
        <p className="small muted" style={{ marginTop: 16 }}>{bn ? 'AI ফলাফল শুধু প্রস্তাবনা। RescueLens কোনো রাস্তা নিরাপদ বলে দাবি করে না এবং জরুরি সেবার বিকল্প নয়।' : 'AI output is a suggestion only. RescueLens never claims a road is safe and is not a replacement for emergency services.'}</p>
      </div>
      <footer className="small muted" style={{ textAlign: 'center', padding: 24 }}>Built with Meta Muse & SAM 3.1 · © 2026 Arifuzzaman Raihan</footer>
    </div>
  )
}
