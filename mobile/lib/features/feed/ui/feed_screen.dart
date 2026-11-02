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
  final VoidCallback? onAlerts;
  const FeedScreen({super.key, this.onAlerts});
  @override
  State<FeedScreen> createState() => _FeedState();
}

class _FeedState extends State<FeedScreen> {
  List? items; bool error = false; Timer? t;
  @override
  void initState() { super.initState(); load(); t = Timer.periodic(const Duration(seconds: 10), (_) => load()); }
  @override
  void dispose() { t?.cancel(); super.dispose(); }
  Future<void> load() async {
    try {
      final r = await Api.get('/incidents?per_page=20&lang=$curLang');
      if (mounted) setState(() { items = r['data']; error = false; });
    } catch (_) { if (mounted) setState(() => error = true); }
  }
  @override
  Widget build(BuildContext context) => RefreshIndicator(onRefresh: load, edgeOffset: 160, child: CustomScrollView(slivers: [
    HomeHeader(onAlerts: widget.onAlerts),
    SliverPadding(padding: const EdgeInsets.fromLTRB(12, 12, 12, 120), sliver: SliverList.list(children: [
      const Entrance(child: SosBanner()),
      Padding(padding: const EdgeInsets.fromLTRB(4, 10, 4, 4), child: Row(children: [
        Expanded(child: Text(tr('latest_near_you'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
        const LiveDot(), const SizedBox(width: 6), Text(tr('live_updates'), style: const TextStyle(color: RL.text2, fontSize: 12)),
      ])),
      if (error && items == null) ErrorCard(onRetry: () { setState(() => error = false); load(); }),
      if (items == null && !error) ...[const PostSkeleton(), const PostSkeleton()],
      if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(32), child: Text(tr('nothing_yet'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2))),
      for (final (n, i) in (items ?? []).indexed) Entrance(key: ValueKey(i['id']), index: n, child: PostTile(i)),
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

class SosBanner extends StatelessWidget {
  const SosBanner({super.key});
  @override
  Widget build(BuildContext context) => Container(margin: const EdgeInsets.only(bottom: 6), padding: const EdgeInsets.all(14), decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFE41E3F), Color(0xFFF5533D)]), borderRadius: BorderRadius.circular(16)),
    child: Row(children: [const Icon(Icons.phone_in_talk_rounded, color: Colors.white), const SizedBox(width: 10), Expanded(child: Text(tr('sos'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)))]));
}
