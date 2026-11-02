import 'package:url_launcher/url_launcher.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:rescuelens/features/incident/ui/media_view.dart';
import 'package:rescuelens/features/incident/ui/post_actions.dart';
import 'package:rescuelens/features/incident/ui/comments.dart';
import 'package:rescuelens/features/profile/ui/timeline.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/core/post_tr.dart';

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
  void initState() { super.initState(); PostTr.notifier.addListener(_tr); }
  void _tr() { if (mounted) setState(() {}); }
  @override
  void dispose() { PostTr.notifier.removeListener(_tr); burst.dispose(); super.dispose(); }
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
    final trd = PostTr.data(i['id']);
    final meta = TextStyle(color: RL.text2, fontSize: 13);
    return Card(clipBehavior: Clip.antiAlias, child: InkWell(onTap: open, onDoubleTap: verify, child: Stack(children: [
      Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          CircleAvatar(backgroundColor: RL.primary, child: Icon(RL.cat(i['category']), color: Colors.white, size: 20)),
          const SizedBox(width: 8),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            GestureDetector(onTap: i['reporter']?['id'] == null ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserProfileScreen('${i['reporter']['id']}'))),
              child: Text(maxLines: 1, overflow: TextOverflow.ellipsis, i['reporter']?['name'] ?? tr('anonymous'), style: const TextStyle(fontWeight: FontWeight.w600))),
            Text('${ago(i['created_at'])}${i['edited_at'] != null ? ' · ${tr('edited')}' : ''} · ${i['location']?['area_name'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: RL.text2, fontSize: 12)),
          ])),
          StatusChip(i),
          PostMenu(i, onUpdated: widget.onUpdated, onRemoved: widget.onRemoved, onRestored: widget.onRestored),
        ]),
        const SizedBox(height: 8),
        Text('${trd?['title'] ?? i['title']}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
        if (i['description'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: widget.inDetail ? Text('${trd?['description'] ?? i['description']}') : ExpandableText('${trd?['description'] ?? i['description']}')),
        PostTranslateBar(i['id'], data: trd),
        if (media.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: MediaStrip(media)),
        const SizedBox(height: 8),
        Row(children: [
          Icon(mine ? LucideIcons.eye : LucideIcons.eye, size: 16, color: mine ? RL.primary : RL.text2), const SizedBox(width: 4),
          Flexible(child: Text(tr('seen_by', {'n': n}), style: meta, overflow: TextOverflow.ellipsis)), const SizedBox(width: 12),
          Icon(LucideIcons.messageCircle, size: 16, color: RL.text2), const SizedBox(width: 4),
          Text(tr('comments', {'n': i['counts']?['comments'] ?? 0}), style: meta),
          const Spacer(),
          if (!widget.inDetail) TextButton.icon(onPressed: open, icon: const Icon(LucideIcons.eye, size: 18), label: Text(tr('view'))),
        ]),
        if (!widget.inDetail && ((i['counts']?['comments'] ?? 0) as int) > 0) ...[const Divider(height: 12), CommentsSection(i['id'], preview: true, onOpenAll: open)],
      ])),
      Positioned.fill(child: IgnorePointer(child: AnimatedBuilder(animation: burst, builder: (_, _) {
        final t = burst.value; if (t == 0 || t == 1) return const SizedBox();
        return Center(child: Opacity(opacity: (1 - t).clamp(0, 1), child: Transform.scale(scale: 0.6 + Curves.elasticOut.transform(t.clamp(0, 1)) * 0.8,
          child: const Icon(LucideIcons.eye, size: 88, color: Colors.white, shadows: [Shadow(blurRadius: 24, color: Colors.black54)]))));
      }))),
    ])));
  }
}

class DetailScreen extends StatefulWidget {
  final String id; final bool focusComments;
  const DetailScreen({super.key, required this.id, this.focusComments = false});
  @override
  State<DetailScreen> createState() => _DetailState();
}

/// Polished details: full post and media, severity and Meta AI card, approximate map, responders, verifications and full comments.
class _DetailState extends State<DetailScreen> {
  Map? i; bool error = false; List? responders; bool? maps;
  final commentsKey = GlobalKey();
  @override
  void initState() {
    super.initState(); load();
    NativeConfig.hasMapsKey().then((v) { if (mounted) setState(() => maps = v); });
    Api.get('/incidents/${widget.id}/dispatches').then((r) { if (mounted) setState(() => responders = r['data']); if ((debugOpen ?? '').endsWith(':sheet') && mounted && (r['data'] as List).isNotEmpty) Future.delayed(const Duration(milliseconds: 900), () { if (mounted && i != null) showResponder(context, r['data'][0], i!); }); }).catchError((_) { if (mounted) setState(() => responders = []); });
  }
  Future<void> load() async { try { final r = await Api.get('/incidents/${widget.id}?lang=$curLang'); if (mounted) setState(() { i = r['data']; error = false; }); } catch (_) { if (mounted) setState(() => error = true); } }
  Future<void> react(String type) async {
    if (Api.token == null) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('login_to_verify')))); return; }
    try { await Api.post('/incidents/${widget.id}/reactions', {'type': type}); await load(); } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('action_failed')))); }
  }
  Widget section(String title, IconData icon, Widget child) => Card(margin: const EdgeInsets.only(top: 10), child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(children: [Icon(icon, size: 20, color: RL.primary), const SizedBox(width: 8), Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))]),
    const SizedBox(height: 10), child])));
  @override
  Widget build(BuildContext context) {
    final x = i;
    return Scaffold(appBar: AppBar(title: Text(tr('report'))),
      body: x == null ? (error ? Padding(padding: const EdgeInsets.all(12), child: ErrorCard(onRetry: load)) : ListView(padding: const EdgeInsets.all(8), children: const [PostSkeleton(), PostSkeleton()]))
      : RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.fromLTRB(8, 8, 8, 40), children: [
          PostTile(x, inDetail: true, onUpdated: (n) => setState(() => i = n), onRemoved: () => Navigator.pop(context)),
          SeverityCard(x),
          section(tr('verifications'), LucideIcons.shieldCheck, Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              _Stat(LucideIcons.eye, '${x['counts']?['confirm'] ?? 0}', tr('confirmed')), const SizedBox(width: 10),
              _Stat(LucideIcons.flag, '${x['counts']?['dispute'] ?? 0}', tr('disputed')),
            ]),
            if (x['reporter']?['is_me'] != true) Padding(padding: const EdgeInsets.only(top: 10), child: Row(children: [
              Expanded(child: FilledButton.icon(onPressed: () => react('confirm'), icon: Icon(x['my_reaction'] == 'confirm' ? LucideIcons.eye : LucideIcons.eye), label: Text(tr('i_see_it')))),
              const SizedBox(width: 8),
              OutlinedButton.icon(onPressed: () => react('dispute'), icon: const Icon(LucideIcons.flag), label: Text(tr('not_there'))),
            ])),
          ])),
          section(tr('location'), LucideIcons.mapPin, Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (maps == true && x['location']?['lat'] != null) ClipRRect(borderRadius: BorderRadius.circular(12), child: SizedBox(height: 180, child: IgnorePointer(child: GoogleMap(liteModeEnabled: true, zoomControlsEnabled: false, myLocationButtonEnabled: false,
              initialCameraPosition: CameraPosition(target: LatLng((x['location']['lat'] as num).toDouble(), (x['location']['lng'] as num).toDouble()), zoom: 14),
              circles: {Circle(circleId: const CircleId('a'), center: LatLng((x['location']['lat'] as num).toDouble(), (x['location']['lng'] as num).toDouble()), radius: 250, fillColor: _sevC(x['severity']).withValues(alpha: .25), strokeColor: _sevC(x['severity']), strokeWidth: 2)})))),
            Padding(padding: const EdgeInsets.only(top: 8), child: Text('${x['location']?['area_name'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600))),
            Text(tr('approx_location'), style: TextStyle(color: RL.text2, fontSize: 12)),
          ])),
          section(tr('responders'), LucideIcons.hospital, responders == null ? const Shim(child: Sk(h: 40))
            : responders!.isEmpty ? Text(tr('no_responders'), style: TextStyle(color: RL.text2))
            : Column(children: [for (final d in responders!) ListTile(contentPadding: EdgeInsets.zero, dense: true, onTap: () => showResponder(context, d, x),
                trailing: const Icon(LucideIcons.chevronRight, size: 18),
                leading: CircleAvatar(backgroundColor: RL.primary.withValues(alpha: .15), child: Icon(_orgIcon('${d['organization']?['type']}'), color: RL.primary, size: 20)),
                title: Row(children: [Flexible(child: Text('${d['organization']?['name'] ?? ''}', overflow: TextOverflow.ellipsis)), if (d['organization']?['verified'] == true) const Padding(padding: EdgeInsets.only(left: 4), child: Icon(LucideIcons.badgeCheck, size: 14, color: RL.primary))]),
                subtitle: Text('${tr('st_${d['status']}')} · ${(d['distance_km'] as num?)?.toStringAsFixed(1) ?? '?'} km'))])),
          Card(key: commentsKey, margin: const EdgeInsets.only(top: 10), child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [const Icon(LucideIcons.messageCircle, size: 20, color: RL.primary), const SizedBox(width: 8), Text(tr('comments_title'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))]),
            CommentsSection(x['id']),
          ]))),
          const SizedBox(height: 10),
          Text(tr('community_note'), style: TextStyle(color: RL.text2, fontSize: 12)),
        ])));
  }
}

IconData _orgIcon(String t) => switch (t) { 'hospital' => LucideIcons.hospital, 'ambulance' => LucideIcons.ambulance, 'fire_service' => LucideIcons.flame, 'police' => LucideIcons.siren, _ => LucideIcons.handHeart };

class _Stat extends StatelessWidget {
  final IconData icon; final String n, label;
  const _Stat(this.icon, this.n, this.label);
  @override
  Widget build(BuildContext context) => Expanded(child: Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: RL.primary.withValues(alpha: .08), borderRadius: BorderRadius.circular(12)),
    child: Row(children: [Icon(icon, color: RL.primary), const SizedBox(width: 8), Text(n, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)), const SizedBox(width: 6), Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: TextStyle(color: RL.text2)))])));
}

/// Severity banner plus the Meta AI analysis summary when present. Never states that a road is safe.
class SeverityCard extends StatelessWidget {
  final Map i;
  const SeverityCard(this.i, {super.key});
  @override
  Widget build(BuildContext context) {
    final c = _sevC(i['severity']); final a = i['analysis'] as Map?;
    return Card(margin: const EdgeInsets.only(top: 10), child: Container(padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Icon(LucideIcons.triangleAlert, color: c), const SizedBox(width: 8), Text('${tr('severity')}: ${tr('${i['severity']}')}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: c))]),
        if (i['severity_reason'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('${i['severity_reason']}', style: TextStyle(color: RL.text2))),
        const Divider(height: 22),
        Row(children: [const Icon(LucideIcons.sparkles, color: RL.primary, size: 20), const SizedBox(width: 8), Text(tr('ai_analysis'), style: const TextStyle(fontWeight: FontWeight.w800)),
          const Spacer(), if (a?['mode'] == 'demo') Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: Colors.orange.withValues(alpha: .2), borderRadius: BorderRadius.circular(8)), child: const Text('DEMO', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.orange)))]),
        const SizedBox(height: 6),
        Text(a == null ? tr('no_ai_analysis') : '${a['summary'] ?? a['description'] ?? tr('ai_done')}', style: const TextStyle(height: 1.35)),
        if (a?['hazards'] is List && (a!['hazards'] as List).isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Wrap(spacing: 6, runSpacing: 6, children: [for (final h in a['hazards']) Chip(visualDensity: VisualDensity.compact, label: Text('$h'))])),
        const SizedBox(height: 6),
        Text(tr('never_safe_note'), style: TextStyle(color: RL.text2, fontSize: 11)),
      ])));
  }
}
Color _sevC(dynamic s) => RL.sev['$s'] ?? RL.primary;

/// Responder details sheet: who was alerted, why, live status steps and tap to call. RescueLens never places calls itself.
void showResponder(BuildContext context, Map d, Map incident) {
  final o = (d['organization'] as Map?) ?? {};
  const steps = ['alerted', 'accepted', 'en_route', 'on_scene', 'resolved'];
  final st = '${d['status']}';
  final at = steps.indexOf(st);
  showModalBottomSheet(context: context, showDragHandle: true, isScrollControlled: true, builder: (c) => SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(20, 0, 20, 20), child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(children: [
      CircleAvatar(radius: 24, backgroundColor: RL.primary.withValues(alpha: .15), child: Icon(_orgIcon('${o['type']}'), color: RL.primary)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Flexible(child: Text('${o['name'] ?? ''}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
          if (o['verified'] == true) const Padding(padding: EdgeInsets.only(left: 4), child: Icon(LucideIcons.badgeCheck, size: 18, color: RL.primary))]),
        Text('${tr('org_${o['type']}')} · ${(d['distance_km'] as num?)?.toStringAsFixed(1) ?? '?'} km', style: TextStyle(color: RL.text2)),
      ])),
    ]),
    if (d['reason'] != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(tr('why_alerted', {'reason': '${d['reason']}'}))),
    const SizedBox(height: 16),
    Text(tr('live_status'), style: const TextStyle(fontWeight: FontWeight.w800)),
    const SizedBox(height: 8),
    if (st == 'declined') Text(tr('st_declined'), style: const TextStyle(color: Color(0xFFE41E3F), fontWeight: FontWeight.w700))
    else for (final (n, s) in steps.indexed) Row(children: [
      Icon(n <= at ? LucideIcons.circleCheck : LucideIcons.circle, size: 20, color: n <= at ? RL.sev['low'] : RL.text2),
      const SizedBox(width: 10),
      Padding(padding: const EdgeInsets.symmetric(vertical: 5), child: Text(tr('st_$s'), style: TextStyle(fontWeight: n == at ? FontWeight.w800 : FontWeight.w500, color: n <= at ? null : RL.text2))),
    ]),
    if (d['updated_at'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(tr('updated_at', {'date': fmtDate(d['updated_at'])}), style: TextStyle(color: RL.text2, fontSize: 12))),
    const SizedBox(height: 16),
    if ('${o['phone'] ?? ''}'.isNotEmpty) SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: () => launchUrl(Uri.parse('tel:${o['phone']}')),
      icon: const Icon(LucideIcons.phone), label: Text(tr('call_org', {'phone': '${o['phone']}'})))),
    Padding(padding: const EdgeInsets.only(top: 8), child: Text(tr('responder_source'), style: TextStyle(color: RL.text2, fontSize: 11))),
  ]))));
}

/// One "See translation" action for the whole post. When on, also shows the Meta AI analysis in the reader's language.
class PostTranslateBar extends StatelessWidget {
  final String id; final Map? data;
  const PostTranslateBar(this.id, {super.key, this.data});
  @override
  Widget build(BuildContext context) {
    final st = PostTr.of(id)?['status'];
    final a = data?['analysis'] as Map?;
    final muted = TextStyle(color: RL.text2, fontSize: 12);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      TextButton.icon(style: TextButton.styleFrom(padding: EdgeInsets.zero, visualDensity: VisualDensity.compact), onPressed: st == 'loading' ? null : () => PostTr.toggle(id),
        icon: st == 'loading' ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(LucideIcons.languages, size: 16),
        label: Text(st == 'loading' ? tr('translating') : data != null ? tr('see_original') : tr('see_translation'))),
      if (st == 'error') Text(tr('translation_failed'), style: muted),
      if (a?['summary'] != null) Container(width: double.infinity, margin: const EdgeInsets.only(top: 2), padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: RL.primary.withValues(alpha: .08), borderRadius: BorderRadius.circular(12)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Icon(LucideIcons.sparkles, size: 14, color: RL.primary), const SizedBox(width: 6), Text(tr('meta_ai_analysis'), style: const TextStyle(fontWeight: FontWeight.w600))]),
          const SizedBox(height: 4), Text('${a!['summary']}'),
          for (final r in (a['reasons'] as List? ?? [])) Text('• $r', style: const TextStyle(fontSize: 13)),
          if ((a['precautions'] as List? ?? []).isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text((a['precautions'] as List).join(' • '), style: const TextStyle(fontSize: 13))),
          const SizedBox(height: 4), Text(tr('translated_by_meta_ai'), style: muted),
        ])),
    ]);
  }
}
