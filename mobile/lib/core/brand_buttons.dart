import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Continue with Google, per the Google Identity sign in branding guidelines:
/// light #FFFFFF fill, #747775 stroke, #1F1F1F text; dark #131314 fill, #8E918F stroke, #E3E3E3 text.
/// Official G mark from Google's signin-assets.zip.
class GoogleSignInButton extends StatelessWidget {
  const GoogleSignInButton({super.key, required this.label, required this.onPressed});
  final String label;
  final VoidCallback onPressed;
  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return _BrandButton(
      label: label, onPressed: onPressed,
      bg: dark ? const Color(0xFF131314) : Colors.white,
      border: dark ? const Color(0xFF8E918F) : const Color(0xFF747775),
      fg: dark ? const Color(0xFFE3E3E3) : const Color(0xFF1F1F1F),
      logo: 'assets/brand/google_g.svg',
    );
  }
}

/// Continue with Facebook, per Meta's Facebook Login button: Facebook blue #0866FF, white text, white f mark
/// from the Meta brand resource center asset pack.
class FacebookSignInButton extends StatelessWidget {
  const FacebookSignInButton({super.key, required this.label, required this.onPressed});
  final String label;
  final VoidCallback onPressed;
  @override
  Widget build(BuildContext context) => _BrandButton(
        label: label, onPressed: onPressed,
        bg: const Color(0xFF0866FF), border: const Color(0xFF0866FF), fg: Colors.white,
        logo: 'assets/brand/facebook_f_white.svg',
      );
}

class _BrandButton extends StatelessWidget {
  const _BrandButton({required this.label, required this.onPressed, required this.bg, required this.border, required this.fg, required this.logo});
  final String label, logo;
  final VoidCallback onPressed;
  final Color bg, border, fg;
  @override
  Widget build(BuildContext context) => SizedBox(
        height: 44, width: double.infinity,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            backgroundColor: bg, foregroundColor: fg, side: BorderSide(color: border),
            shape: const StadiumBorder(), padding: const EdgeInsets.symmetric(horizontal: 12),
          ),
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            SvgPicture.asset(logo, width: 20, height: 20),
            const SizedBox(width: 10),
            Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg, fontSize: 14, fontWeight: FontWeight.w500))),
          ]),
        ),
      );
}
