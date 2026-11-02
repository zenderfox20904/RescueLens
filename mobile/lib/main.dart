import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'core/api.dart';
import 'core/i18n.dart';
import 'core/theme.dart';
import 'core/widgets.dart';
import 'features/feed/ui/feed_screen.dart';
import 'features/map/ui/map_screen.dart';
import 'features/auth/ui/login_screen.dart';
import 'features/report/ui/report_screen.dart';
import 'features/alerts/ui/alerts_screen.dart';
import 'features/profile/ui/profile_screen.dart';


Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Future.wait([Api.load(), L10n.i.load()]);
  runApp(const RescueLensApp());
}

class RescueLensApp extends StatelessWidget {
  const RescueLensApp({super.key});
  @override
  Widget build(BuildContext context) => ListenableBuilder(listenable: L10n.i, builder: (_, _) => MaterialApp(
        title: 'RescueLens', debugShowCheckedModeBanner: false,
        theme: RL.theme(Brightness.light), darkTheme: RL.theme(Brightness.dark), themeMode: ThemeMode.dark,
        locale: Locale(L10n.i.lang),
        builder: (c, child) => Directionality(textDirection: L10n.i.isRtl ? TextDirection.rtl : TextDirection.ltr, child: child!),
        home: const Home(),
      ));
}

class Home extends StatefulWidget {
  const Home({super.key});
  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> {
  // Debug aid for automated screenshots: --dart-define=RL_START_TAB=1 opens the Map tab.
  int tab = const int.fromEnvironment('RL_START_TAB');
  void go(int i) { HapticFeedback.selectionClick(); setState(() => tab = i); }
  @override
  Widget build(BuildContext context) {
    final authed = Api.token != null;
    void refresh() => setState(() {});
    final pages = [FeedScreen(onAlerts: () => go(3)), const MapScreen(), authed ? const ReportScreen() : LoginScreen(onDone: refresh),
      authed ? const AlertsScreen() : LoginScreen(onDone: refresh), ProfileScreen(onChanged: refresh)];
    final wide = MediaQuery.sizeOf(context).width >= 700;
    final body = AnimatedSwitcher(duration: const Duration(milliseconds: 250), child: KeyedSubtree(key: ValueKey('$tab$authed'), child: pages[tab]));
    final appBar = AppBar(titleSpacing: 16, title: const Row(mainAxisSize: MainAxisSize.min, children: [LogoMark(size: 30), SizedBox(width: 8), Flexible(child: Text('RescueLens', overflow: TextOverflow.ellipsis))]));
    if (wide) {
      return Scaffold(appBar: appBar, body: SafeArea(child: Row(children: [
        NavigationRail(selectedIndex: tab, onDestinationSelected: go, labelType: NavigationRailLabelType.all, destinations: [
          for (final d in _dests) NavigationRailDestination(icon: Icon(d.$1), selectedIcon: Icon(d.$2), label: Text(tr(d.$3))),
        ]),
        Expanded(child: body),
      ])));
    }
    return Scaffold(extendBody: true, appBar: tab == 0 ? null : appBar, body: body, bottomNavigationBar: FloatingNav(index: tab, onTap: go));
  }
}

const _dests = [
  (Icons.home_outlined, Icons.home_rounded, 'home'),
  (Icons.map_outlined, Icons.map_rounded, 'map'),
  (Icons.add_a_photo_outlined, Icons.add_a_photo, 'report'),
  (Icons.notifications_none_rounded, Icons.notifications_rounded, 'alerts'),
  (Icons.person_outline_rounded, Icons.person_rounded, 'profile'),
];

/// Floating pill navigation with a blurred surface, sliding active indicator and a raised center Report action.
class FloatingNav extends StatelessWidget {
  final int index; final ValueChanged<int> onTap;
  const FloatingNav({super.key, required this.index, required this.onTap});
  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return SafeArea(minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12), child: SizedBox(height: 84, child: Stack(clipBehavior: Clip.none, alignment: Alignment.bottomCenter, children: [
      Container(height: 66, decoration: BoxDecoration(borderRadius: BorderRadius.circular(33), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: dark ? .5 : .15), blurRadius: 24, offset: const Offset(0, 8))]),
        child: ClipRRect(borderRadius: BorderRadius.circular(33), child: BackdropFilter(filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18), child: Container(
          decoration: BoxDecoration(color: (dark ? RL.darkCard : Colors.white).withValues(alpha: .82), border: Border.all(color: Colors.white.withValues(alpha: dark ? .08 : .6))),
          child: Row(children: [for (var i = 0; i < 5; i++) Expanded(child: i == 2 ? const SizedBox() : _NavItem(i: i, active: i == index, onTap: () => onTap(i)))]))))),
      Positioned(top: 0, child: GestureDetector(onTap: () => onTap(2), child: AnimatedScale(scale: index == 2 ? 1.08 : 1, duration: const Duration(milliseconds: 200), child: Container(
        width: 62, height: 62, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RL.gradient, boxShadow: [BoxShadow(color: RL.primary.withValues(alpha: .45), blurRadius: 18, offset: const Offset(0, 6))], border: Border.all(color: dark ? RL.darkBg : Colors.white, width: 4)),
        child: Semantics(label: tr('report'), button: true, child: const Icon(Icons.add_rounded, color: Colors.white, size: 32)))))),
    ])));
  }
}

class _NavItem extends StatelessWidget {
  final int i; final bool active; final VoidCallback onTap;
  const _NavItem({required this.i, required this.active, required this.onTap});
  @override
  Widget build(BuildContext context) {
    final d = _dests[i];
    final c = active ? RL.primary : RL.text2;
    return InkResponse(onTap: onTap, radius: 32, child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      AnimatedContainer(duration: const Duration(milliseconds: 250), curve: Curves.easeOutCubic, padding: EdgeInsets.symmetric(horizontal: active ? 14 : 8, vertical: 4),
        decoration: BoxDecoration(color: active ? RL.primary.withValues(alpha: .14) : Colors.transparent, borderRadius: BorderRadius.circular(16)),
        child: i == 3 ? const AlertBadge() : Icon(active ? d.$2 : d.$1, color: c, size: 24)),
      const SizedBox(height: 2),
      Text(tr(d.$3), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11, color: c, fontWeight: active ? FontWeight.w700 : FontWeight.w500)),
    ]));
  }
}
