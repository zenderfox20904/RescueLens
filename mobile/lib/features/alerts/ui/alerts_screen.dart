import 'dart:async';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';

/// In-app alerts list (dispatch and nearby notifications).
class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});
  @override
  State<AlertsScreen> createState() => _AlertsState();
}

class _AlertsState extends State<AlertsScreen> {
  List? items; bool error = false;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    try { final r = await Api.get('/notifications'); if (mounted) setState(() { items = r['data']; error = false; }); } catch (_) { if (mounted) setState(() => error = true); }
  }
  @override
  Widget build(BuildContext context) => RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 120), children: [
    Padding(padding: const EdgeInsets.all(4), child: Text(tr('alerts'), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
    if (error) ErrorCard(onRetry: load),
    if (items == null && !error) const PostSkeleton(),
    if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(32), child: Text(tr('no_alerts'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2))),
    for (final (n, a) in (items ?? []).indexed) Entrance(index: n, child: Card(child: ListTile(
      leading: CircleAvatar(backgroundColor: RL.primary.withValues(alpha: .15), child: Icon(a['type'] == 'dispatch' ? Icons.local_hospital_rounded : Icons.campaign_rounded, color: RL.primary)),
      title: Text('${a['title']}', style: TextStyle(fontWeight: a['read'] == true ? FontWeight.w500 : FontWeight.w800)),
      subtitle: Text([if (a['body'] != null) a['body'], ago(a['created_at'])].join(' · ')),
      onTap: a['incident_id'] == null ? null : () => openDetail(context, a['incident_id'])))),
  ]));
}
