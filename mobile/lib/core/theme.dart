import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';

/// Shared design tokens, mirrored from design/tokens.json used by the web app.
class RL {
  static const primary = Color(0xFF1877F2);
  static const bg = Color(0xFFF0F2F5);
  static const card = Color(0xFFFFFFFF);
  static const text = Color(0xFF050505);
  /// Secondary text. Updated from the active brightness so muted text keeps WCAG AA contrast in dark mode.
  static bool isDark = false;
  static Color get text2 => isDark ? const Color(0xFFB0B3B8) : const Color(0xFF65676B);
  static const divider = Color(0xFFCED0D4);
  static const darkBg = Color(0xFF18191A);
  static const darkCard = Color(0xFF242526);
  static const darkHover = Color(0xFF3A3B3C);
  static const gradFrom = Color(0xFF0064E0);
  static const gradTo = Color(0xFF0081FB);
  static const gradient = LinearGradient(colors: [gradFrom, gradTo]);
  static const sev = {'low': Color(0xFF31A24C), 'moderate': Color(0xFFF7B928), 'high': Color(0xFFF5793B), 'critical': Color(0xFFE41E3F)};
  static const catIcon = <String, IconData>{'road_accident': LucideIcons.car, 'flood': LucideIcons.waves, 'fire': LucideIcons.flame, 'electrical': LucideIcons.zap, 'building_collapse': LucideIcons.building, 'injury': LucideIcons.bandage, 'crowd_stampede': LucideIcons.users, 'landslide': LucideIcons.mountain, 'earthquake': LucideIcons.activity, 'wildfire': LucideIcons.treePine, 'tsunami': LucideIcons.waves, 'cyclone_storm': LucideIcons.wind, 'snow_ice': LucideIcons.snowflake, 'other': LucideIcons.triangleAlert};
  static const categories = ['road_accident', 'flood', 'fire', 'electrical', 'building_collapse', 'injury', 'crowd_stampede', 'landslide', 'earthquake', 'wildfire', 'tsunami', 'cyclone_storm', 'snow_ice', 'other'];
  static IconData cat(dynamic c) => catIcon[c] ?? LucideIcons.triangleAlert;

  static ThemeData theme(Brightness b) {
    final dark = b == Brightness.dark;
    final scheme = ColorScheme.fromSeed(seedColor: primary, brightness: b).copyWith(
      primary: primary, surface: dark ? darkCard : card, onSurface: dark ? const Color(0xFFE4E6EB) : text);
    return ThemeData(
      useMaterial3: true, colorScheme: scheme, brightness: b,
      scaffoldBackgroundColor: dark ? darkBg : bg,
      dividerColor: dark ? darkHover : divider,
      appBarTheme: AppBarTheme(backgroundColor: dark ? darkCard : card, foregroundColor: primary, elevation: 3, scrolledUnderElevation: 3, shadowColor: Colors.black.withValues(alpha: dark ? .5 : .12), surfaceTintColor: Colors.transparent, toolbarHeight: 64, centerTitle: false,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(bottom: Radius.circular(28))),
        titleTextStyle: const TextStyle(color: primary, fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
      cardTheme: CardThemeData(color: dark ? darkCard : card, elevation: 0, margin: const EdgeInsets.symmetric(vertical: 6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: primary, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)))),
    );
  }
}
