import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/incident/ui/comments.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';

/// A person's reports as full feed cards with cursor based infinite loading and an empty state.
/// The server only marks posts as manageable for the owner or moderators, so other profiles show no menu.
class ProfileTimeline extends StatefulWidget {
  final String userId; final bool isMe;
  const ProfileTimeline({super.key, required this.userId, this.isMe = false});
  @override
  State<ProfileTimeline> createState() => ProfileTimelineState();
}

class ProfileTimelineState extends State<ProfileTimeline> {
  final items = <Map>[]; String? cursor; bool loading = false, done = false, error = false, first = true;
  @override
  void initState() { super.initState(); more(); }
  Future<void> reload() async { setState(() { items.clear(); cursor = null; done = false; first = true; }); await more(); }
  Future<void> more() async {
    if (loading || done) return;
    loading = true;
    try {
      final r = await Api.get('/incidents?user=${widget.userId}&per_page=10&lang=$curLang${cursor != null ? '&cursor=${Uri.encodeQueryComponent(cursor!)}' : ''}');
      if (!mounted) return;
      setState(() { items.addAll((r['data'] as List).cast<Map>()); cursor = r['meta']?['next_cursor']; done = cursor == null; error = false; first = false; });
    } catch (_) { if (mounted) setState(() { error = true; first = false; }); }
    loading = false;
  }
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    Padding(padding: const EdgeInsets.fromLTRB(4, 16, 4, 6), child: Text(widget.isMe ? tr('your_reports') : tr('reports_title'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
    if (first) ...[const PostSkeleton(), const PostSkeleton()],
    if (error && items.isEmpty) ErrorCard(onRetry: () { setState(() => error = false); more(); }),
    if (!first && !error && items.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(28), child: Column(children: [
      const Icon(Icons.article_outlined, size: 44, color: RL.text2), const SizedBox(height: 8),
      Text(tr('no_reports_profile'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)), const SizedBox(height: 4),
      Text(widget.isMe ? tr('no_reports_profile_me') : tr('no_reports_profile_other'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2)),
    ]))),
    for (final (n, i) in items.indexed) Builder(key: ValueKey(i['id']), builder: (_) {
      if (n == items.length - 3 || n == items.length - 1) WidgetsBinding.instance.addPostFrameCallback((_) => more());
      return Entrance(index: n, child: PostTile(i,
        onUpdated: (u) => setState(() => items[items.indexWhere((x) => x['id'] == u['id'])] = u),
        onRemoved: () => setState(() => items.removeWhere((x) => x['id'] == i['id'])), onRestored: reload));
    }),
    if (!done && items.isNotEmpty) const PostSkeleton(),
  ]);
}

/// Another person's public profile: header and their public, named reports. No manage menu unless moderator.
class UserProfileScreen extends StatefulWidget {
  final String userId;
  const UserProfileScreen(this.userId, {super.key});
  @override
  State<UserProfileScreen> createState() => _UserProfileState();
}

class _UserProfileState extends State<UserProfileScreen> {
  Map? p;
  @override
  void initState() { super.initState(); Api.get('/users/${widget.userId}/profile').then((r) { if (mounted) setState(() => p = r['data']); }).catchError((_) {}); }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(p?['name'] ?? '')), body: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 40), children: [
    Card(clipBehavior: Clip.antiAlias, child: Column(children: [
      Container(height: 110, decoration: const BoxDecoration(gradient: LinearGradient(colors: [RL.primary, Color(0xFF6A5CFF)]))),
      Transform.translate(offset: const Offset(0, -40), child: Column(children: [
        Container(padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: Theme.of(context).cardColor, shape: BoxShape.circle), child: p == null ? const Shim(child: Sk(w: 84, h: 84, r: 42)) : Avatar(name: '${p!['name']}', url: p!['avatar_url'], r: 42)),
        const SizedBox(height: 6),
        Text(p?['name'] ?? '', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        if (p != null) Text(tr('reports_count', {'n': p!['reports']}), style: const TextStyle(color: RL.text2)),
      ])),
    ])),
    ProfileTimeline(userId: widget.userId, isMe: p?['is_me'] == true),
  ]));
}
