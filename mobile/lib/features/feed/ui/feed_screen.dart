import 'package:url_launcher/url_launcher.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';
import 'package:rescuelens/features/feed/ui/home_header.dart';

/// Live feed with polling, pull to refresh, staggered entrance and shimmer skeletons.
class FeedScreen extends StatefulWidget {
  final VoidCallback? onAlerts, onProfile;
  const FeedScreen({super.key, this.onAlerts, this.onProfile});
  @override
  State<FeedScreen> createState() => _FeedState();
}

class _FeedState extends State<FeedScreen> {
  List? items; bool error = false; Timer? t; String sort = 'ranked';
  @override
  void initState() { super.initState(); load(); t = Timer.periodic(const Duration(seconds: 10), (_) => load()); }
  @override
  void dispose() { t?.cancel(); super.dispose(); }
  Future<void> load() async {
    try {
      final r = await Api.get('/incidents?per_page=20&sort=$sort&lang=$curLang');
      if (mounted) setState(() { items = r['data']; error = false; });
    } catch (_) { if (mounted) setState(() => error = true); }
  }
  @override
  Widget build(BuildContext context) => RefreshIndicator(onRefresh: load, edgeOffset: 160, child: CustomScrollView(slivers: [
    HomeHeader(onAlerts: widget.onAlerts, onProfile: widget.onProfile),
    SliverPadding(padding: const EdgeInsets.fromLTRB(12, 12, 12, 120), sliver: SliverList.list(children: [
      const Entrance(child: SosBanner()),
      Padding(padding: const EdgeInsets.fromLTRB(4, 10, 4, 4), child: Row(children: [
        Expanded(child: Text(tr('latest_near_you'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
        for (final v in const [('ranked', LucideIcons.flame, 'feed_top'), ('latest', LucideIcons.clock, 'feed_latest')])
          Padding(padding: const EdgeInsets.only(right: 6), child: ChoiceChip(avatar: Icon(v.$2, size: 16), label: Text(tr(v.$3)), selected: sort == v.$1, onSelected: (_) { setState(() { sort = v.$1; items = null; }); load(); })),
        const LiveDot(), const SizedBox(width: 6), Text(tr('live_updates'), style: TextStyle(color: RL.text2, fontSize: 12)),
      ])),
      if (error && items == null) ErrorCard(onRetry: () { setState(() => error = false); load(); }),
      if (items == null && !error) ...[const PostSkeleton(), const PostSkeleton()],
      if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(32), child: Text(tr('nothing_yet'), textAlign: TextAlign.center, style: TextStyle(color: RL.text2))),
      for (final (n, i) in (items ?? []).indexed) Entrance(key: ValueKey(i['id']), index: n, child: PostTile(i, onUpdated: (u) => setState(() => items![items!.indexWhere((x) => x['id'] == u['id'])] = u), onRemoved: () => setState(() => items!.removeWhere((x) => x['id'] == i['id'])), onRestored: load)),
    ])),
  ]));
}

class LiveDot extends StatefulWidget {
  const LiveDot({super.key});
  @override
  State<LiveDot> createState() => _LiveDotState();
}

class _LiveDotState extends State<LiveDot> with SingleTickerProviderStateMixin {
  late final c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat(reverse: true);
  @override
  void dispose() { c.dispose(); super.dispose(); }
  @override
  Widget build(BuildContext context) => FadeTransition(opacity: Tween(begin: .35, end: 1.0).animate(c), child: Container(width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFF31A24C), shape: BoxShape.circle)));
}

/// Verified emergency number for the active country (device location, profile, then IP), with its source. Nothing unverified is shown.
class SosBanner extends StatefulWidget {
  const SosBanner({super.key});
  @override
  State<SosBanner> createState() => _SosBannerState();
}

class _SosBannerState extends State<SosBanner> {
  static Map? _cache;
  Map? res = _cache;
  @override
  void initState() {
    super.initState();
    if (res == null) { () async {
      String? cc;
      try { cc = (await Me.load())?['country_code']; } catch (_) {}
      if (cc == null) { try { cc = (await Api.get('/geo/ip'))['country_code']; } catch (_) {} }
      if (cc == null) return;
      try { final r = await Api.get('/emergency-resources?country=$cc'); final l = r['data'] as List; if (l.isNotEmpty && mounted) setState(() => res = _cache = l.first); } catch (_) {}
    }(); }
  }
  @override
  Widget build(BuildContext context) {
    final r = res;
    final name = r == null ? null : (curLang == 'bn' ? (r['name_bn'] ?? r['name']) : r['name']);
    return InkWell(borderRadius: BorderRadius.circular(16), onTap: r == null ? null : () => launchUrl(Uri.parse('tel:${r['phone']}')),
      child: Container(margin: const EdgeInsets.only(bottom: 6), padding: const EdgeInsets.all(14), decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFE41E3F), Color(0xFFF5533D)]), borderRadius: BorderRadius.circular(16)),
        child: Row(children: [const Icon(LucideIcons.phoneCall, color: Colors.white), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(r == null ? tr('sos') : tr('sos_call', {'phone': r['phone']}), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
          if (r != null) Text('$name · ${tr('source_is', {'s': r['source_name']})}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12)),
        ]))])));
  }
}
