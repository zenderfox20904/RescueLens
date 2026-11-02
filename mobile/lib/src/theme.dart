import 'package:flutter/material.dart';

/// Shared design tokens, mirrored from design/tokens.json used by the web app.
class RL {
  static const primary = Color(0xFF1877F2);
  static const bg = Color(0xFFF0F2F5);
  static const card = Color(0xFFFFFFFF);
  static const text = Color(0xFF050505);
  static const text2 = Color(0xFF65676B);
  static const divider = Color(0xFFCED0D4);
  static const darkBg = Color(0xFF18191A);
  static const darkCard = Color(0xFF242526);
  static const darkHover = Color(0xFF3A3B3C);
  static const gradFrom = Color(0xFF0064E0);
  static const gradTo = Color(0xFF0081FB);
  static const gradient = LinearGradient(colors: [gradFrom, gradTo]);
  static const sev = {'low': Color(0xFF31A24C), 'moderate': Color(0xFFF7B928), 'high': Color(0xFFF5793B), 'critical': Color(0xFFE41E3F)};
  static const catIcon = {'road_accident': '🚗', 'flood': '🌊', 'fire': '🔥', 'electrical': '⚡', 'building_collapse': '🏚️', 'injury': '🩹', 'other': '⚠️'};

  static ThemeData theme(Brightness b) {
    final dark = b == Brightness.dark;
    final scheme = ColorScheme.fromSeed(seedColor: primary, brightness: b).copyWith(
      primary: primary, surface: dark ? darkCard : card, onSurface: dark ? const Color(0xFFE4E6EB) : text);
    return ThemeData(
      useMaterial3: true, colorScheme: scheme, brightness: b,
      scaffoldBackgroundColor: dark ? darkBg : bg,
      dividerColor: dark ? darkHover : divider,
      appBarTheme: AppBarTheme(backgroundColor: dark ? darkCard : card, foregroundColor: primary, elevation: 0.5,
        titleTextStyle: const TextStyle(color: primary, fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
      cardTheme: CardThemeData(color: dark ? darkCard : card, elevation: 0.5, margin: const EdgeInsets.symmetric(vertical: 6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: primary, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)))),
    );
  }
}
