# Sign in brand assets

| File | Source |
| --- | --- |
| `frontend/src/assets/brand/google_g.svg` | Google G cut from the official `signin-assets.zip` at https://developers.google.com/identity/branding-guidelines (button background removed, mark unchanged) |
| `mobile/assets/brand/google_g.svg` | Same official mark, embedded as a PNG inside an SVG because flutter_svg cannot draw its conic gradient. Built from the official light and dark PNG @4x files so the background is transparent |
| `*/assets/brand/facebook_f*.svg` | Facebook logo from the Facebook Brand Asset Pack at https://about.meta.com/brand/resources/facebook/logo/ (Primary Logo .ai converted to SVG; the white variant swaps the two official colors for use on the blue button) |

Button styling follows the Google Identity guidelines for light and dark themes and Meta's Facebook Login button (Facebook blue #0866FF with white text).
