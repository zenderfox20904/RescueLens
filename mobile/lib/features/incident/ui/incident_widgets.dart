import 'dart:async';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

void openDetail(BuildContext c, String id) => Navigator.push(c, MaterialPageRoute(builder: (_) => DetailScreen(id: id)));

class PostTile extends StatelessWidget {
  final Map i;
  const PostTile(this.i, {super.key});
  @override
  Widget build(BuildContext context) {
    final media = (i['media'] as List?) ?? [];
    return Card(child: InkWell(onTap: () => openDetail(context, i['id']), child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        CircleAvatar(backgroundColor: RL.primary, child: Text(RL.catIcon[i['category']] ?? '⚠️')),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(maxLines: 1, overflow: TextOverflow.ellipsis, i['reporter']?['name'] ?? tr('anonymous'), style: const TextStyle(fontWeight: FontWeight.w600)),
          Text('${ago(i['created_at'])} · ${i['location']?['area_name'] ?? ''}', style: const TextStyle(color: RL.text2, fontSize: 12)),
        ])),
        StatusChip(i),
      ]),
      const SizedBox(height: 8),
      Text(title(i), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
      if (i['description'] != null) Text('${i['description_translated'] ?? i['description']}', maxLines: 3, overflow: TextOverflow.ellipsis),
      if (media.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(media[0]['url'], height: 200, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox()))),
      const Divider(),
      Row(children: [const Icon(Icons.visibility_outlined, size: 16, color: RL.text2), const SizedBox(width: 4), Text(tr('seen_by', {'n': i['counts']['confirm']}), style: const TextStyle(color: RL.text2)), const SizedBox(width: 12), const Icon(Icons.chat_bubble_outline_rounded, size: 16, color: RL.text2), const SizedBox(width: 4), Text(tr('comments', {'n': i['counts']['comments']}), style: const TextStyle(color: RL.text2))]),
    ]))));
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
          PostTile(i!),
          FilledButton.icon(onPressed: Api.token == null ? null : react, icon: const Icon(Icons.visibility), label: Text(tr('i_see_it'))),
          const SizedBox(height: 8),
          Text(tr('community_note'), style: const TextStyle(color: RL.text2, fontSize: 12)),
        ]));
}
