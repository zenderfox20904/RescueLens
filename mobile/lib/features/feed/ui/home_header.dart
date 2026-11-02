import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/features/incident/ui/media_view.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

/// Cached signed in user for header and profile surfaces.
class Me {
  /// Listenable so the avatar and name update live everywhere the moment they change.
  static final notifier = ValueNotifier<Map?>(null);
  static Map? get user => notifier.value;
  static set user(Map? u) => notifier.value = u == null ? null : Map.of(u);
  static Future<Map?> load({bool force = false}) async {
    if (Api.token == null) return user = null;
    if (user != null && !force) return user;
    try { user = (await Api.get('/auth/me'))['user']; } catch (_) {}
    return user;
  }
}

/// Curved solid header (white in light, dark surface in dark) with soft shadow, that collapses into a compact bar on scroll.
class HomeHeader extends StatefulWidget {
  final VoidCallback? onAlerts, onProfile;
  const HomeHeader({super.key, this.onAlerts, this.onProfile});
  @override
  State<HomeHeader> createState() => _HomeHeaderState();
}

class _HomeHeaderState extends State<HomeHeader> {
  Map? me;
  @override
  void initState() { super.initState(); Me.notifier.addListener(_sync); Me.load().then((u) { if (mounted) setState(() => me = u); }); }
  void _sync() { if (mounted) setState(() => me = Me.user); }
  @override
  void dispose() { Me.notifier.removeListener(_sync); super.dispose(); }

  String greeting() {
    final h = DateTime.now().hour;
    return tr(h < 12 ? 'good_morning' : h < 17 ? 'good_afternoon' : 'good_evening');
  }

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final th = Theme.of(context);
    final bg = dark ? (th.appBarTheme.backgroundColor ?? th.colorScheme.surface) : Colors.white;
    final fg = th.colorScheme.onSurface;
    final top = MediaQuery.paddingOf(context).top;
    final name = '${me?['name'] ?? ''}'.split(' ').first;
    final radius = me?['alert_radius_km'] ?? 5;
    final unit = me?['distance_unit'] ?? 'km';
    final area = me?['alert_area_set'] == true ? tr('your_alert_area') : tr('set_alert_area');
    final white = fg;
    final actions = Row(mainAxisSize: MainAxisSize.min, children: [
      PopupMenuButton<String>(tooltip: tr('language'), icon: Icon(LucideIcons.languages, color: white),
        onSelected: (l) => L10n.i.set(l),
        itemBuilder: (_) => [for (final e in L10n.langs.entries) CheckedPopupMenuItem(value: e.key, checked: L10n.i.lang == e.key, child: Text(e.value))]),
      IconButton(tooltip: tr('alerts'), onPressed: widget.onAlerts, icon: IconTheme(data: IconThemeData(color: white), child: AlertBadge(color: white))),
      if (Api.token != null) Padding(padding: const EdgeInsets.only(left: 4), child: MyAvatar(radius: 17, onTap: widget.onProfile)),
    ]);
    return SliverAppBar(
      pinned: true, expandedHeight: 172, toolbarHeight: 64, backgroundColor: Colors.transparent, surfaceTintColor: Colors.transparent, elevation: 0,
      automaticallyImplyLeading: false, titleSpacing: 16,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(bottom: Radius.circular(28))),
      
      title: Row(mainAxisSize: MainAxisSize.min, children: [const LogoMark(size: 28), const SizedBox(width: 8), const Text('RescueLens', style: TextStyle(color: Color(0xFF1877F2), fontWeight: FontWeight.w800, fontSize: 22, letterSpacing: -0.5))]),
      actions: [actions, const SizedBox(width: 4)],
      flexibleSpace: LayoutBuilder(builder: (_, c) {
        final t = ((c.maxHeight - 64 - top) / (172 - 64)).clamp(0.0, 1.0);
        return DecoratedBox(
          decoration: BoxDecoration(color: bg, borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: dark ? .45 : .08), blurRadius: 18, offset: const Offset(0, 6))]),
          child: Stack(children: [
            Positioned(left: 16, right: 16, bottom: 16, child: Opacity(opacity: t, child: Transform.translate(offset: Offset(0, 12 * (1 - t)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              Text(name.isEmpty ? greeting() : '${greeting()}, $name', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: white, fontSize: 22, fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6), decoration: BoxDecoration(color: const Color(0xFF0866FF).withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(LucideIcons.locateFixed, color: Color(0xFF0866FF), size: 16), const SizedBox(width: 6),
                  Flexible(child: Text('$area · $radius $unit', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFF0866FF), fontWeight: FontWeight.w600, fontSize: 13))),
                ])),
            ])))),
          ]),
        );
      }),
    );
  }
}

/// Current user's avatar that rebuilds instantly when the profile photo changes.
class MyAvatar extends StatelessWidget {
  final double radius; final VoidCallback? onTap;
  const MyAvatar({super.key, this.radius = 18, this.onTap});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Map?>(valueListenable: Me.notifier, builder: (_, u, _) {
    final url = u?['avatar_url'];
    final name = '${u?['name'] ?? ''}'.trim();
    return GestureDetector(onTap: onTap, child: CircleAvatar(radius: radius, backgroundColor: RL.primary.withValues(alpha: .18),
      child: url != null ? ClipOval(child: SizedBox.expand(child: NetImage('$url', fit: BoxFit.cover)))
        : name.isNotEmpty ? Text(name[0].toUpperCase(), style: TextStyle(color: RL.primary, fontWeight: FontWeight.w800, fontSize: radius * .8))
        : Icon(LucideIcons.user, color: RL.primary, size: radius)));
  });
}
