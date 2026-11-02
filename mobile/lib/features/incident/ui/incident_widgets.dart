import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:rescuelens/features/incident/ui/media_view.dart';
import 'package:rescuelens/features/incident/ui/post_actions.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

void openDetail(BuildContext c, String id) => Navigator.push(c, MaterialPageRoute(builder: (_) => DetailScreen(id: id)));

/// Feed card. Tap opens details, double tap marks "I see it too" with a burst animation.
class PostTile extends StatefulWidget {
  final Map i; final bool inDetail; final ValueChanged<Map>? onUpdated; final VoidCallback? onRemoved, onRestored;
  const PostTile(this.i, {super.key, this.inDetail = false, this.onUpdated, this.onRemoved, this.onRestored});
  @override
  State<PostTile> createState() => _PostTileState();
}

class _PostTileState extends State<PostTile> with SingleTickerProviderStateMixin {
  late final AnimationController burst = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
  int? seen; bool mine = false;
  @override
  void dispose() { burst.dispose(); super.dispose(); }
  Map get i => widget.i;
  void open() { if (!widget.inDetail) openDetail(context, i['id']); }
  Future<void> verify() async {
    HapticFeedback.mediumImpact();
    burst.forward(from: 0);
    if (Api.token == null) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('login_to_verify')))); return; }
    if (mine) return;
    setState(() { mine = true; seen = (seen ?? (i['counts']?['confirm'] ?? 0) as int) + 1; });
    try { await Api.post('/incidents/${i['id']}/reactions', {'type': 'confirm'}); }
    catch (_) { if (mounted) { setState(() { mine = false; seen = seen! - 1; }); ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('action_failed')))); } }
  }
  @override
  Widget build(BuildContext context) {
    final media = (i['media'] as List?) ?? [];
    final n = seen ?? i['counts']?['confirm'] ?? 0;
    const meta = TextStyle(color: RL.text2, fontSize: 13);
    return Card(clipBehavior: Clip.antiAlias, child: InkWell(onTap: open, onDoubleTap: verify, child: Stack(children: [
      Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          CircleAvatar(backgroundColor: RL.primary, child: Text(RL.catIcon[i['category']] ?? '⚠️')),
          const SizedBox(width: 8),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(maxLines: 1, overflow: TextOverflow.ellipsis, i['reporter']?['name'] ?? tr('anonymous'), style: const TextStyle(fontWeight: FontWeight.w600)),
            Text('${ago(i['created_at'])}${i['edited_at'] != null ? ' · ${tr('edited')}' : ''} · ${i['location']?['area_name'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: RL.text2, fontSize: 12)),
          ])),
          StatusChip(i),
          PostMenu(i, onUpdated: widget.onUpdated, onRemoved: widget.onRemoved, onRestored: widget.onRestored),
        ]),
        const SizedBox(height: 8),
        Text(title(i), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
        if (i['description'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: widget.inDetail ? Text('${i['description_translated'] ?? i['description']}') : ExpandableText('${i['description_translated'] ?? i['description']}')),
        if (media.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: MediaStrip(media)),
        const SizedBox(height: 8),
        Row(children: [
          Icon(mine ? Icons.visibility : Icons.visibility_outlined, size: 16, color: mine ? RL.primary : RL.text2), const SizedBox(width: 4),
          Flexible(child: Text(tr('seen_by', {'n': n}), style: meta, overflow: TextOverflow.ellipsis)), const SizedBox(width: 12),
          const Icon(Icons.chat_bubble_outline_rounded, size: 16, color: RL.text2), const SizedBox(width: 4),
          Text(tr('comments', {'n': i['counts']?['comments'] ?? 0}), style: meta),
          const Spacer(),
          if (!widget.inDetail) TextButton(onPressed: open, child: Text(tr('view'))),
        ]),
      ])),
      Positioned.fill(child: IgnorePointer(child: AnimatedBuilder(animation: burst, builder: (_, _) {
        final t = burst.value; if (t == 0 || t == 1) return const SizedBox();
        return Center(child: Opacity(opacity: (1 - t).clamp(0, 1), child: Transform.scale(scale: 0.6 + Curves.elasticOut.transform(t.clamp(0, 1)) * 0.8,
          child: const Icon(Icons.visibility_rounded, size: 88, color: Colors.white, shadows: [Shadow(blurRadius: 24, color: Colors.black54)]))));
      }))),
    ])));
  }
}

class DetailScreen extends StatefulWidget {
  final String id;
  const DetailScreen({super.key, required this.id});
  @override
  State<DetailScreen> createState() => _DetailState();
}

class _DetailState extends State<DetailScreen> {
  Map? i; bool error = false;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async { try { final r = await Api.get('/incidents/${widget.id}?lang=$curLang'); if (mounted) setState(() { i = r['data']; error = false; }); } catch (_) { if (mounted) setState(() => error = true); } }
  Future<void> react() async {
    if (Api.token == null) return;
    try { await Api.post('/incidents/${widget.id}/reactions', {'type': 'confirm'}); await load(); } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('action_failed')))); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('report'))),
    body: i == null ? (error ? Padding(padding: const EdgeInsets.all(12), child: ErrorCard(onRetry: load)) : const Padding(padding: EdgeInsets.all(8), child: PostSkeleton()))
      : ListView(padding: const EdgeInsets.all(8), children: [
          PostTile(i!, inDetail: true, onUpdated: (n) => setState(() => i = n), onRemoved: () => Navigator.pop(context)),
          FilledButton.icon(onPressed: Api.token == null ? null : react, icon: const Icon(Icons.visibility), label: Text(tr('i_see_it'))),
          const SizedBox(height: 8),
          Text(tr('community_note'), style: const TextStyle(color: RL.text2, fontSize: 12)),
        ]));
}
