# RescueLens Demo Script (2:55)

Promo style voiceover for an Indian male voice: warm, confident, medium pace (about 150 words per minute). Narration always says "Meta AI". Record with the local setup (AI_PROVIDER=ollama) so no DEMO labels appear. Seeded posts carry real Meta AI analyses (run `php artisan rescuelens:analyze-seeded` once after seeding). The sidebar shows only "Meta AI", the emergency banner follows the viewer's location (999 with its source in Bangladesh), and app map pins are Meta blue incident type icons that follow the theme.

Screens: web at http://localhost:5173 (dark mode, 1280 by 860), app on the iPhone simulator (dark theme). Subtitles are burned in, white on a 60 percent black bar, bottom center, max two lines.

## Demo accounts

All portals use the seeded demo password. Emails and the password are listed only in DEMO_ACCOUNTS.local.txt at the repo root (never committed).

* Citizen: rina@rescuelens.test
* NGO: ngo@rescuelens.test (Demo Relief NGO Dhaka)
* Help center: helpcenter@rescuelens.test (Demo Flood Relief Center Mohammadpur)
* Responders: fire@, ambulance@, hospital@rescuelens.test
* Moderator: moderator@rescuelens.test
* Admin: admin@rescuelens.test

## Feature checklist (every feature mapped to a shot)

1. Meta AI photo analysis: type, risk, severity, confidence, reasoning, evidence boxes. Shot 2 (0:10)
2. Meta AI on a normal post (no emergency, low severity). Shot 2 (0:22)
3. Video upload with Meta AI detection, metadata stripped, evidence boxes hidden while playing. Shot 3 (0:30)
4. All 14 incident types (road accident, waterlogging or flood, fire, electrical hazard, building collapse, injury, crowd crush, landslide, earthquake, wildfire, tsunami, cyclone or storm, snow or ice, other). Shot 4 (0:40)
5. Confirm before publishing, duplicate check, join existing report. Shot 5 (0:48)
6. Comment and reaction analysis updating severity with the reason shown. Shot 6 (0:56)
7. Automatic responder alerts by category and distance, with "why alerted". Shot 7 (1:06)
8. Live dispatch status (Alerted, Accepted, On the way, On scene, Resolved) and responder sheet with verified call button. Shot 7 (1:14)
9. Nearby user alerts and how nearby people help (saw this, confirm, comment). Shot 8 (1:22)
10. Portal logins with live alerts: citizen, NGO, help center, responder, moderator, admin. Shot 9 (1:30 to 1:58)
11. Map: opens on your country, Near me, My country, World, type and severity filters, light and dark styles. Shot 10 (1:58)
12. Ranked feed (Top and Latest), live updates, shimmer loading. Shot 11 (2:12)
13. Verified emergency numbers with source, SOS banner, "never claims a road is safe". Shot 11 (2:18)
14. Notifications live, mark all read. Shot 9 (1:30)
15. Profile, settings, language (English, Bangla, Hindi, Spanish, Arabic), theme, alert radius, location privacy. Shot 12 (2:24)
16. Trash and restore, edit and delete own posts. Shot 12 (2:30)
17. Admin panel: users, organizations, emergency resources, moderation queue, verify or merge reports. Shot 9 (1:50)
18. Offline drafts and installable web app. Shot 12 (2:34)
19. Privacy: approximate public location, GPS stripped from photos and videos. Shot 3 and Shot 10

## Scene by scene

**Shot 1. Motion graphic intro (0:00 to 0:08)**
Screen: animated logo. A lens ring pulses over a dark city map, markers light up.
Music: low cinematic pulse, rising. SFX: soft whoosh on the logo.
Title card: "RescueLens. Report fast. Help arrives faster."
VO: "Every minute after an accident matters. RescueLens turns one photo into help on the way, with Meta AI."

**Shot 2. Meta AI analysis, web Report page (0:08 to 0:30)**
Screen: logged in as Rina. Report page, drop a road accident photo. Shimmer skeleton, then the analysis card with evidence boxes.
VO: "Snap a photo. Meta AI reads it from every angle. Incident type: road accident. Risk: blocked lane and injured people. Severity: critical, with its confidence and the reasoning in plain words. Tap a box to see the evidence it used."
At 0:22 upload a normal street photo.
VO: "And when nothing is wrong, Meta AI says so honestly. Low severity, no alarm."
SFX: soft click per tap. Subtitle highlights: "Type", "Risk", "Severity", "Confidence", "Reasoning".

**Shot 3. Video upload (0:30 to 0:40)**
Screen: app Report screen, tap Video, pick a clip. Poster appears with a play icon, then the Meta AI card. Cut to web post playing the video; evidence boxes disappear while it plays.
VO: "Videos work too. Location metadata is stripped before anything is stored, and Meta AI analyzes the key frame."

**Shot 4. All 14 incident types (0:40 to 0:48)**
Screen: fast montage, 0.5 seconds each, of the category chips on the web map filter, each with its icon.
VO: "Fourteen incident types, from road accidents and floods to earthquakes, cyclones and snow."
Music: beat drops on each chip. SFX: tick per chip.

**Shot 5. Confirm and post (0:48 to 0:56)**
Screen: Rina edits the Meta AI draft, ticks the public consent box, presses Post. Duplicate check shows a nearby report with "Join existing report".
VO: "You stay in control. Nothing is published until you confirm, and duplicates are merged instead of spamming the map."

**Shot 6. Comments and reactions update severity (0:56 to 1:06)**
Screen: incident detail. Two users confirm and comment "two people trapped". The severity badge moves from High to Critical with the reason line.
VO: "As people confirm and comment, Meta AI rereads the thread and updates severity, and always shows why."

**Shot 7. Rescue flow and responder sheet (1:06 to 1:22)**
Screen: app incident detail, Responders row, tap Demo Fire Station. Responder sheet: why alerted, live status steps, verified call button.
VO: "The moment a report goes live, RescueLens automatically alerts matched responders: ambulances and hospitals for crashes, fire service and relief centers for floods. Each one shows why they were alerted and live status, from alerted to resolved. Calls are always placed by you, to a verified number."

**Shot 8. Nearby people help (1:22 to 1:30)**
Screen: second simulator or web as another citizen. Alert banner "CRITICAL: Bus and rickshaw collision near Shahbag, about 2 km from you". Tap "I saw this", Confirm.
VO: "People nearby get an alert too, and can confirm, add details, or guide responders."

**Shot 9. Every portal, live alerts (1:30 to 1:58)**
Fast cuts, about 4 seconds each, login screen then inbox. Screenshots for reference in docs/screenshots (alerts-*.png, portal-*.png).
* Citizen Rina: Notifications with nearby alerts, Mark all read.
* NGO: Notifications "Response request: HIGH Knee deep flooding at Kalabagan".
* Help center: the same request for the relief center.
* Responder (ambulance): Responder inbox with Accept, Decline, Directions. Tap Accept; status turns Accepted live.
* Moderator: review alerts, Moderation queue, Verify or Merge.
* Admin: Admin panel, Users, Organizations, Emergency resources.
VO: "Every account gets its own portal. NGOs and help centers receive response requests. Responders accept and get directions. Moderators verify. Admins manage users, organizations and verified emergency numbers."
SFX: notification chime on each inbox.

**Shot 10. Map (1:58 to 2:12)**
Screen: web Map opens centered on Bangladesh (My country). Click Near me (25 km), then World, then filter Fire, then toggle the theme for light and dark map styles. Cut to the app map doing the same.
VO: "The map opens on your country. Zoom to what is near you, or see the whole world. Filter by type and severity. Locations are approximate, and an empty area never means a road is safe."

**Shot 11. Feed and safety (2:12 to 2:24)**
Screen: web Feed, Top tab then Latest tab, shimmer while loading, live dot. Then the SOS banner with the verified number and its source.
VO: "The feed ranks what matters most first: unresolved, severe, and confirmed. And emergency numbers are only shown when verified, with the source right there."

**Shot 12. Your account (2:24 to 2:40)**
Screen: app Profile, Settings (language Bangla flip, theme, alert radius, location privacy), My trash with Restore. Web: install prompt and Drafts card.
VO: "Five languages, dark and light themes, your own alert radius, a trash you can restore from, and drafts that wait when you are offline."

**Shot 13. Outro (2:40 to 2:55)**
Screen: motion graphic. Split screen of web and app, then logo.
Title card: "RescueLens. Powered by Meta AI."
VO: "RescueLens. Built so help finds you faster. Powered by Meta AI."
Music: resolve on a warm chord, fade out by 2:55. SFX: final whoosh.
Footer card: "© 2026 RescueLens"

## Recording notes

* Before Shot 9, post the two live reports (Shahbag collision, Kalabagan flooding) as Rina so every inbox has a fresh alert.
* Keep cursor movement slow; speed up cuts in editing.
* Do not show terminal windows, .env files, or the local accounts file on screen.
