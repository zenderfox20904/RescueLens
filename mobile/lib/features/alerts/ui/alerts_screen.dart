import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/feed/ui/home_header.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';

const kCategories = ['fire', 'flood', 'road_accident', 'building_collapse', 'electrical', 'injury', 'other'];
const kSeverities = ['low', 'moderate', 'high', 'critical'];

/// Realtime notifications: swipe to delete with Undo, read toggle, bulk actions, preferences.
class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});
  @override
  State<AlertsScreen> createState() => _AlertsState();
}

class _AlertsState extends State<AlertsScreen> {
  List? items; bool error = false; Timer? t; int unread = 0;
  final pending = <int>{};
  @override
  void initState() { super.initState(); load(); t = Timer.periodic(const Duration(seconds: 10), (_) => load()); }
  @override
  void dispose() { t?.cancel(); super.dispose(); }
  Future<void> load() async {
    try {
      final r = await Api.get('/notifications');
      if (mounted) setState(() { items = (r['data'] as List).where((a) => !pending.contains(a['id'])).toList(); unread = (r['unread'] as num).toInt(); error = false; });
    } catch (_) { if (mounted && items == null) setState(() => error = true); }
  }

  void snack(String m, {SnackBarAction? action}) => ScaffoldMessenger.of(context)..hideCurrentSnackBar()..showSnackBar(SnackBar(persist: false, duration: const Duration(seconds: 5), content: Text(m), action: action, behavior: SnackBarBehavior.floating, margin: const EdgeInsets.fromLTRB(12, 0, 12, 100)));

  Future<void> setRead(Map a, bool read) async {
    setState(() { a['read'] = read; unread += read ? -1 : 1; });
    try { await Api.patch('/notifications/${a['id']}', {'read': read}); } catch (_) { if (mounted) setState(() => a['read'] = !read); }
  }

  void remove(Map a, int index) {
    HapticFeedback.mediumImpact();
    setState(() { items!.remove(a); pending.add(a['id']); if (a['read'] != true) unread--; });
    var undone = false;
    final c = ScaffoldMessenger.of(context)..hideCurrentSnackBar();
    c.showSnackBar(SnackBar(persist: false, duration: const Duration(seconds: 5), content: Text(tr('n_deleted')), behavior: SnackBarBehavior.floating, margin: const EdgeInsets.fromLTRB(12, 0, 12, 100),
      action: SnackBarAction(label: tr('undo'), onPressed: () { undone = true; setState(() { items!.insert(index.clamp(0, items!.length), a); pending.remove(a['id']); if (a['read'] != true) unread++; }); }))).closed.then((_) async {
      if (undone) return;
      try { await Api.delete('/notifications/${a['id']}'); } catch (_) {}
      pending.remove(a['id']);
    });
  }

  Future<void> markAll() async {
    setState(() { for (final a in items ?? []) { a['read'] = true; } unread = 0; });
    try { await Api.post('/notifications/read-all', {}); snack(tr('n_all_read')); } catch (_) { load(); }
  }

  Future<void> clearAll() async {
    if (!await confirmAction(context, title: tr('n_clear_q'), body: tr('n_clear_body'), confirm: tr('n_clear'), icon: LucideIcons.trash)) return;
    setState(() { items = []; unread = 0; });
    try { await Api.delete('/notifications'); } catch (_) { load(); }
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 120), children: [
    Padding(padding: const EdgeInsets.fromLTRB(4, 0, 0, 4), child: Row(children: [
      Expanded(child: Text(tr('alerts'), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
      if (unread > 0) Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: const Color(0xFFE41E3F), borderRadius: BorderRadius.circular(12)), child: Text('$unread', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12))),
      IconButton(tooltip: tr('n_prefs'), icon: const Icon(LucideIcons.slidersHorizontal), onPressed: () => showPrefs(context)),
      PopupMenuButton<String>(onSelected: (v) => v == 'read' ? markAll() : clearAll(), itemBuilder: (_) => [
        PopupMenuItem(value: 'read', enabled: unread > 0, child: ListTile(leading: const Icon(LucideIcons.checkCheck), title: Text(tr('n_mark_all')))),
        PopupMenuItem(value: 'clear', enabled: (items ?? []).isNotEmpty, child: ListTile(leading: const Icon(LucideIcons.trash), title: Text(tr('n_clear')))),
      ]),
    ])),
    if (error) ErrorCard(onRetry: load),
    if (items == null && !error) ...[const PostSkeleton(), const PostSkeleton()],
    if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(40), child: Column(children: [
      Icon(LucideIcons.bellOff, size: 48, color: RL.text2.withValues(alpha: .6)), const SizedBox(height: 12),
      Text(tr('no_alerts'), textAlign: TextAlign.center, style: TextStyle(color: RL.text2))])),
    if ((items ?? []).isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(4, 0, 4, 6), child: Text(tr('n_swipe_hint'), style: TextStyle(color: RL.text2, fontSize: 12))),
    for (final (n, a) in (items ?? []).indexed) Dismissible(
      key: ValueKey('n${a['id']}'), direction: DismissDirection.endToStart, onDismissed: (_) => remove(a, n),
      background: Container(margin: const EdgeInsets.symmetric(vertical: 6), alignment: AlignmentDirectional.centerEnd, padding: const EdgeInsets.symmetric(horizontal: 20),
        decoration: BoxDecoration(color: const Color(0xFFE41E3F), borderRadius: BorderRadius.circular(16)), child: const Icon(LucideIcons.trash2, color: Colors.white)),
      child: Card(child: ListTile(
        leading: Stack(clipBehavior: Clip.none, children: [
          CircleAvatar(backgroundColor: RL.primary.withValues(alpha: .15), child: Icon(a['type'] == 'dispatch' ? LucideIcons.hospital : LucideIcons.megaphone, color: RL.primary)),
          if (a['read'] != true) Positioned(right: -1, top: -1, child: Container(width: 10, height: 10, decoration: const BoxDecoration(color: RL.primary, shape: BoxShape.circle))),
        ]),
        title: Text('${a['title']}', style: TextStyle(fontWeight: a['read'] == true ? FontWeight.w500 : FontWeight.w800)),
        subtitle: Text([if (a['body'] != null) a['body'], ago(a['created_at'])].join(' · ')),
        trailing: PopupMenuButton<String>(icon: const Icon(LucideIcons.ellipsis),
          onSelected: (v) => v == 'toggle' ? setRead(a, a['read'] != true) : remove(a, n),
          itemBuilder: (_) => [
            PopupMenuItem(value: 'toggle', child: Text(tr(a['read'] == true ? 'n_mark_unread' : 'n_mark_read'))),
            PopupMenuItem(value: 'delete', child: Text(tr('delete'))),
          ]),
        onTap: () { if (a['read'] != true) setRead(a, true); if (a['incident_id'] != null) openDetail(context, a['incident_id']); }))),
  ]));
}

/// Notification preferences sheet: push, categories, minimum severity, quiet hours.
Future<void> showPrefs(BuildContext context) async {
  final me = await Me.load(force: true);
  if (!context.mounted) return;
  final p = Map<String, dynamic>.from((me?['notify_prefs'] as Map?) ?? {});
  bool push = p['push'] ?? true;
  final cats = <String>{...((p['categories'] as List?) ?? []).cast<String>()};
  String sev = p['min_severity'] ?? 'low';
  String? qs = p['quiet_start'], qe = p['quiet_end'];
  var saving = false;
  TimeOfDay? parse(String? s) => s == null ? null : TimeOfDay(hour: int.parse(s.split(':')[0]), minute: int.parse(s.split(':')[1]));
  String fmt(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  await showModalBottomSheet(context: context, isScrollControlled: true, showDragHandle: true, builder: (c) => StatefulBuilder(builder: (c, set) => SafeArea(child: SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(tr('n_prefs'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
      SwitchListTile(contentPadding: EdgeInsets.zero, value: push, onChanged: (v) => set(() => push = v), title: Text(tr('n_push')), subtitle: Text(tr('n_push_sub'))),
      const SizedBox(height: 6), Text(tr('n_categories'), style: const TextStyle(fontWeight: FontWeight.w700)),
      Text(tr('n_categories_sub'), style: TextStyle(color: RL.text2, fontSize: 12)), const SizedBox(height: 6),
      Wrap(spacing: 6, runSpacing: 6, children: [for (final k in kCategories) FilterChip(label: Text(tr('c_$k')), selected: cats.contains(k), onSelected: (v) => set(() => v ? cats.add(k) : cats.remove(k)))]),
      const SizedBox(height: 14), Text(tr('n_min_sev'), style: const TextStyle(fontWeight: FontWeight.w700)), const SizedBox(height: 6),
      SegmentedButton<String>(segments: [for (final s in kSeverities) ButtonSegment(value: s, label: Text(tr(s), style: const TextStyle(fontSize: 12)))], selected: {sev}, onSelectionChanged: (v) => set(() => sev = v.first), showSelectedIcon: false),
      const SizedBox(height: 14), Text(tr('n_quiet'), style: const TextStyle(fontWeight: FontWeight.w700)),
      Text(tr('n_quiet_sub'), style: TextStyle(color: RL.text2, fontSize: 12)), const SizedBox(height: 6),
      Row(children: [
        for (final (i, v) in [qs, qe].indexed) ...[
          Expanded(child: OutlinedButton.icon(icon: const Icon(LucideIcons.clock, size: 18), label: Text(v ?? tr(i == 0 ? 'n_from' : 'n_to')), onPressed: () async {
            final t = await showTimePicker(context: c, initialTime: parse(v) ?? TimeOfDay(hour: i == 0 ? 22 : 7, minute: 0));
            if (t != null) set(() => i == 0 ? qs = fmt(t) : qe = fmt(t));
          })),
          if (i == 0) const SizedBox(width: 8),
        ],
        if (qs != null || qe != null) IconButton(onPressed: () => set(() { qs = null; qe = null; }), icon: const Icon(LucideIcons.x)),
      ]),
      const SizedBox(height: 18),
      SizedBox(width: double.infinity, child: FilledButton(onPressed: saving ? null : () async {
        set(() => saving = true);
        try {
          final both = qs != null && qe != null;
          final r = await Api.patch('/auth/me', {'notify_prefs': {'push': push, 'categories': cats.toList(), 'min_severity': sev, 'quiet_start': both ? qs : null, 'quiet_end': both ? qe : null}});
          Me.user = r['user'];
          if (c.mounted) Navigator.pop(c);
          if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('saved')), behavior: SnackBarBehavior.floating));
        } catch (e) { set(() => saving = false); }
      }, child: saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('save')))),
    ])))));
}
