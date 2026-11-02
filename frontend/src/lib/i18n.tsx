import { createContext, useContext, useState, type ReactNode } from 'react'

const dict = {
  bn: {
    feed: 'ফিড', map: 'ম্যাপ', report: 'রিপোর্ট করুন', alerts: 'সতর্কতা', mod: 'মডারেশন', profile: 'প্রোফাইল', login: 'লগ ইন', register: 'নতুন অ্যাকাউন্ট',
    logout: 'লগ আউট', whatsHappening: 'আশেপাশে কী ঘটছে? ছবি দিয়ে রিপোর্ট করুন', photo: 'ছবি / ক্যামেরা', iSee: 'আমিও দেখছি', notThere: 'ভুল তথ্য',
    comment: 'মন্তব্য', share: 'শেয়ার', recent: 'সাম্প্রতিক সতর্কতা', nearby: 'আপনার কাছাকাছি', emergency: 'জরুরি প্রয়োজনে কল করুন', notifications: 'নোটিফিকেশন',
    markRead: 'সব পড়া হয়েছে', noNotif: 'কোনো নোটিফিকেশন নেই', writeComment: 'মন্তব্য লিখুন...', verified: 'মডারেটর যাচাইকৃত', corroborated: 'কমিউনিটি সমর্থিত',
    reported: 'রিপোর্ট করা', disputed: 'বিতর্কিত', resolved: 'সমাধান হয়েছে', expired: 'পুরনো', stale: 'তথ্য পুরনো হতে পারে', demo: 'ডেমো',
    aiSuggestion: 'AI পরামর্শ, নিশ্চিত তথ্য নয়', analyze: 'AI দিয়ে বিশ্লেষণ', post: 'পোস্ট করুন', cancel: 'বাতিল', next: 'পরবর্তী', back: 'পেছনে',
    confirmPost: 'আমি নিশ্চিত করছি তথ্যটি সত্য এবং প্রকাশ্যে দেখানো যাবে (আনুমানিক অবস্থান)', aiConsent: 'ছবিটি AI বিশ্লেষণের জন্য Meta Model API তে পাঠানোর অনুমতি দিচ্ছি',
    title: 'শিরোনাম', description: 'বিবরণ', category: 'ধরন', severity: 'তীব্রতা', location: 'অবস্থান', useMyLocation: 'আমার অবস্থান ব্যবহার করুন',
    anonymous: 'নাম গোপন রাখুন', duplicateFound: 'কাছাকাছি একই ধরনের রিপোর্ট আছে', joinExisting: 'এটিতে যোগ দিন', postAnyway: 'তবুও নতুন পোস্ট',
    offlineSaved: 'অফলাইন: খসড়া সংরক্ষিত, অনলাইন হলে পাঠানো হবে', drafts: 'অফলাইন খসড়া', sendNow: 'এখন পাঠান', loadMore: 'আরও দেখুন',
    neverSafe: 'RescueLens কোনো রাস্তা নিরাপদ বলে নিশ্চয়তা দেয় না।', all: 'সব', stats: 'পরিসংখ্যান', active: 'সক্রিয়', last24: 'শেষ ২৪ ঘণ্টা',
  },
  en: {
    feed: 'Feed', map: 'Map', report: 'Report', alerts: 'Alerts', mod: 'Moderation', profile: 'Profile', login: 'Log in', register: 'Create account',
    logout: 'Log out', whatsHappening: "What's happening nearby? Report with a photo", photo: 'Photo / Camera', iSee: 'I see this too', notThere: 'Not accurate',
    comment: 'Comment', share: 'Share', recent: 'Recent alerts', nearby: 'Near you', emergency: 'In an emergency call', notifications: 'Notifications',
    markRead: 'Mark all read', noNotif: 'No notifications yet', writeComment: 'Write a comment...', verified: 'Moderator verified', corroborated: 'Community corroborated',
    reported: 'Reported', disputed: 'Disputed', resolved: 'Resolved', expired: 'Expired', stale: 'May be outdated', demo: 'DEMO',
    aiSuggestion: 'AI suggestion, not confirmed', analyze: 'Analyze with AI', post: 'Post', cancel: 'Cancel', next: 'Next', back: 'Back',
    confirmPost: 'I confirm this is accurate and can be shown publicly (approximate location)', aiConsent: 'I allow this photo to be sent to the Meta Model API for AI analysis',
    title: 'Title', description: 'Description', category: 'Category', severity: 'Severity', location: 'Location', useMyLocation: 'Use my location',
    anonymous: 'Post anonymously', duplicateFound: 'A similar report already exists nearby', joinExisting: 'Join that report', postAnyway: 'Post new anyway',
    offlineSaved: 'Offline: draft saved and will be sent when you are back online', drafts: 'Offline drafts', sendNow: 'Send now', loadMore: 'Load more',
    neverSafe: 'RescueLens never guarantees that any road is safe.', all: 'All', stats: 'Statistics', active: 'Active', last24: 'Last 24 hours',
  },
} as const
export type Key = keyof typeof dict.bn
export const CATS: Record<string, { bn: string; en: string; icon: string }> = {
  road_accident: { bn: 'সড়ক দুর্ঘটনা', en: 'Road accident', icon: '🚗' }, flood: { bn: 'জলাবদ্ধতা / বন্যা', en: 'Waterlogging / flood', icon: '🌊' },
  fire: { bn: 'আগুন', en: 'Fire', icon: '🔥' }, electrical: { bn: 'বিদ্যুৎ ঝুঁকি', en: 'Electrical hazard', icon: '⚡' },
  building_collapse: { bn: 'ভবন ধস', en: 'Building collapse', icon: '🏚️' }, injury: { bn: 'আহত ব্যক্তি', en: 'Injury', icon: '🩹' }, other: { bn: 'অন্যান্য', en: 'Other', icon: '⚠️' },
}
export const SEVS: Record<string, { bn: string; en: string }> = { low: { bn: 'কম', en: 'Low' }, moderate: { bn: 'মাঝারি', en: 'Moderate' }, high: { bn: 'উচ্চ', en: 'High' }, critical: { bn: 'গুরুতর', en: 'Critical' } }

type Ctx = { lang: 'bn' | 'en'; setLang: (l: 'bn' | 'en') => void; t: (k: Key) => string }
const I18n = createContext<Ctx>(null!)
export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setL] = useState<'bn' | 'en'>((localStorage.getItem('rl_lang') as any) || 'bn')
  const setLang = (l: 'bn' | 'en') => { localStorage.setItem('rl_lang', l); document.documentElement.lang = l; setL(l) }
  return <I18n.Provider value={{ lang, setLang, t: (k) => dict[lang][k] }}>{children}</I18n.Provider>
}
export const useI18n = () => useContext(I18n)

export function timeAgo(iso: string, lang: 'bn' | 'en') {
  const s = Math.max(1, Math.round((Date.now() - new Date(iso).getTime()) / 1000))
  const [v, u] = s < 60 ? [s, 's'] : s < 3600 ? [Math.round(s / 60), 'm'] : s < 86400 ? [Math.round(s / 3600), 'h'] : [Math.round(s / 86400), 'd']
  if (lang === 'en') return `${v}${u}`
  const bnU = { s: ' সেকেন্ড', m: ' মিনিট', h: ' ঘণ্টা', d: ' দিন' }[u]
  return String(v).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[+d]) + bnU + ' আগে'
}
