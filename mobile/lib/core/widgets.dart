import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'dart:async';
import 'api.dart';
import 'i18n.dart';
import 'theme.dart';

/// Facebook style shimmer placeholder block.
class Sk extends StatelessWidget {
  final double? w; final double h; final double r;
  const Sk({super.key, this.w, this.h = 12, this.r = 6});
  @override
  Widget build(BuildContext context) => Container(width: w, height: h, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(r)));
}

class Shim extends StatelessWidget {
  final Widget child;
  const Shim({super.key, required this.child});
  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Shimmer.fromColors(baseColor: dark ? RL.darkHover : const Color(0xFFE4E6EB), highlightColor: dark ? const Color(0xFF4E4F50) : const Color(0xFFF5F6F7), child: child);
  }
}

class PostSkeleton extends StatelessWidget {
  const PostSkeleton({super.key});
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(12), child: Shim(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
    Row(children: [Sk(w: 40, h: 40, r: 20), SizedBox(width: 8), Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Sk(w: 140), SizedBox(height: 6), Sk(w: 90, h: 10)])]),
    SizedBox(height: 12), Sk(h: 14), SizedBox(height: 8), Sk(w: 220, h: 14), SizedBox(height: 12), Sk(h: 200, r: 8),
  ]))));
}

class LogoMark extends StatelessWidget {
  final double size;
  const LogoMark({super.key, this.size = 36});
  @override
  Widget build(BuildContext context) => ClipRRect(borderRadius: BorderRadius.circular(size * 0.25), child: Image.asset('assets/icon.png', width: size, height: size, errorBuilder: (_, _, _) => Container(width: size, height: size, decoration: const BoxDecoration(gradient: RL.gradient), child: Icon(LucideIcons.heartPulse, color: Colors.white, size: size * .6))));
}

/// Severity pill, always localized.
class StatusChip extends StatelessWidget {
  final Map i;
  const StatusChip(this.i, {super.key});
  @override
  Widget build(BuildContext context) {
    final verified = i['status'] == 'verified';
    final sev = '${i['severity']}';
    final c = RL.sev[sev] ?? RL.text2;
    return Row(mainAxisSize: MainAxisSize.min, children: [
      if (verified) Padding(padding: const EdgeInsetsDirectional.only(end: 4), child: Icon(LucideIcons.badgeCheck, size: 16, color: RL.primary, semanticLabel: tr('verified'))),
      Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3), decoration: BoxDecoration(color: c.withValues(alpha: .16), borderRadius: BorderRadius.circular(20)),
        child: Text(tr(sev), style: TextStyle(color: c, fontSize: 12, fontWeight: FontWeight.w700))),
    ]);
  }
}

/// Fades and slides a child in once, staggered by [index].
class Entrance extends StatefulWidget {
  final Widget child; final int index;
  const Entrance({super.key, required this.child, this.index = 0});
  @override
  State<Entrance> createState() => _EntranceState();
}

class _EntranceState extends State<Entrance> with SingleTickerProviderStateMixin {
  late final c = AnimationController(vsync: this, duration: const Duration(milliseconds: 380));
  @override
  void initState() { super.initState(); Future.delayed(Duration(milliseconds: 50 * widget.index.clamp(0, 8)), () { if (mounted) c.forward(); }); }
  @override
  void dispose() { c.dispose(); super.dispose(); }
  @override
  Widget build(BuildContext context) {
    final a = CurvedAnimation(parent: c, curve: Curves.easeOutCubic);
    return FadeTransition(opacity: a, child: SlideTransition(position: Tween(begin: const Offset(0, .06), end: Offset.zero).animate(a), child: widget.child));
  }
}

/// Friendly, localized error with a retry action. Raw exceptions are never shown to users.
class ErrorCard extends StatelessWidget {
  final VoidCallback onRetry;
  const ErrorCard({super.key, required this.onRetry});
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(20), child: Column(children: [
    Icon(LucideIcons.wifiOff, size: 36, color: RL.text2), const SizedBox(height: 8),
    Text(tr('load_error'), textAlign: TextAlign.center), const SizedBox(height: 12),
    FilledButton.icon(onPressed: onRetry, icon: const Icon(LucideIcons.refreshCw), label: Text(tr('retry'))),
  ])));
}

/// Bell icon with a live unread count, polled every 15 seconds.
class AlertBadge extends StatefulWidget {
  final Color? color;
  const AlertBadge({super.key, this.color});
  @override
  State<AlertBadge> createState() => _AlertBadgeState();
}

class _AlertBadgeState extends State<AlertBadge> {
  int n = 0; Timer? t;
  @override
  void initState() { super.initState(); poll(); t = Timer.periodic(const Duration(seconds: 15), (_) => poll()); }
  @override
  void dispose() { t?.cancel(); super.dispose(); }
  Future<void> poll() async {
    if (Api.token == null) return;
    try { final r = await Api.get('/notifications'); if (mounted) setState(() => n = (r['unread'] as num?)?.toInt() ?? 0); } catch (_) {}
  }
  @override
  Widget build(BuildContext context) => Badge(isLabelVisible: n > 0, label: Text(n > 99 ? '99+' : '$n'), child: Icon(LucideIcons.bell, color: widget.color ?? RL.text2, size: 24));
}

/// Polished confirmation for destructive or important actions. Returns true only when confirmed.
Future<bool> confirmAction(BuildContext context, {required String title, required String body, required String confirm, IconData icon = LucideIcons.triangleAlert, bool danger = true}) async {
  final color = danger ? const Color(0xFFE41E3F) : RL.primary;
  final ok = await showDialog<bool>(context: context, builder: (c) => AlertDialog(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    icon: Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: color.withValues(alpha: .12), shape: BoxShape.circle), child: Icon(icon, color: color, size: 28)),
    title: Text(title, textAlign: TextAlign.center), content: Text(body, textAlign: TextAlign.center),
    actionsAlignment: MainAxisAlignment.center,
    actions: [
      OutlinedButton(onPressed: () => Navigator.pop(c, false), child: Text(tr('cancel'))),
      FilledButton(style: FilledButton.styleFrom(backgroundColor: color), onPressed: () => Navigator.pop(c, true), child: Text(confirm)),
    ]));
  return ok == true;
}
