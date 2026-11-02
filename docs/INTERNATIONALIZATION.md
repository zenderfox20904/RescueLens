# Internationalization

RescueLens is Bangla first and ready for worldwide use.

## Web (React)
* Library: i18next, react-i18next and i18next-browser-languagedetector.
* Detection order: saved choice in localStorage (`rl_lang`), then the browser language, then Bangla.
* Translations live in `frontend/src/locales/<code>.json`. Add a language by adding a JSON file and one entry in `LANGUAGES` in `frontend/src/lib/i18n.tsx`.
* RTL ready: when the active language is Arabic, Urdu, Persian or Hebrew, `<html dir="rtl">` is set. `<html lang>` always follows the active language.
* Bangla text uses Noto Sans Bengali through a `:lang(bn)` rule.
* Numbers, dates, relative times, distances and country names use the browser `Intl` APIs. Bangla uses the bn-BD locale, which renders Bangla digits.
* Times are shown in the viewer's own time zone (or the time zone saved on the profile).
* Distance units: kilometres by default, miles for US, GB, LR and MM. Users can change both in Profile.

## Country
* Detected from the saved choice (`rl_country`), then the browser region, then BD.
* Saved on the account as `country_code`, together with `distance_unit` and `timezone` (`PATCH /api/v1/auth/me`).
* The AI prompt receives the country. Bangladesh specific road context (CNG, rickshaw, easy bike, leguna) is only added for BD. Hazard categories are global: crashes, floods, fires, wires, landslides, earthquakes, wildfires, tsunamis, cyclones, snow and ice, crowd crush.

## Emergency directory
* `GET /api/v1/emergency-resources?country=XX` returns only active entries that a system admin has verified (`verified_at` set), each with its source.
* Only one entry is seeded: Bangladesh National Emergency Service 999, source 999.gov.bd.
* For any other country the UI says no verified contacts exist yet and asks people to call their local emergency number. RescueLens never invents a phone number.

## Mobile (Flutter)
See the ARB localization section in `mobile/README.md` (Flutter gen_l10n with `app_bn.arb` and `app_en.arb`).
