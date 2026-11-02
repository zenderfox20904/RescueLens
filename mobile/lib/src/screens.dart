import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:image_picker/image_picker.dart';
import 'api.dart';
import 'theme.dart';
import 'widgets.dart';

String ago(String iso) {
  final d = DateTime.now().difference(DateTime.parse(iso));
  if (d.inMinutes < 60) return '${d.inMinutes} মিনিট আগে';
  if (d.inHours < 24) return '${d.inHours} ঘণ্টা আগে';
  return '${d.inDays} দিন আগে';
}

/// Live feed with polling, a stories strip and shimmer skeletons while loading.
class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});
  @override
  State<FeedScreen> createState() => _FeedState();
}

class _FeedState extends State<FeedScreen> {
  List? items; List? stories; String? error; Timer? t;
  @override
  void initState() { super.initState(); load(); t = Timer.periodic(const Duration(seconds: 10), (_) => load()); }
  @override
  void dispose() { t?.cancel(); super.dispose(); }
  Future<void> load() async {
    try {
      final r = await Api.get('/incidents?per_page=20');
      final s = await Api.get('/incidents/map?hours=48');
      if (mounted) setState(() { items = r['data']; stories = s['data']; error = null; });
    } catch (e) { if (mounted) setState(() => error = '$e'); }
  }
  @override
  Widget build(BuildContext context) => RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.all(8), children: [
    const SosBanner(),
    if (stories == null) const StoriesSkeleton() else SizedBox(height: 180, child: ListView(scrollDirection: Axis.horizontal, children: [
      for (final s in stories!) GestureDetector(onTap: () => openDetail(context, s['id']), child: Container(width: 110, margin: const EdgeInsets.all(4), padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [RL.sev[s['severity']]!, Colors.black87]), borderRadius: BorderRadius.circular(10)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(RL.catIcon[s['category']] ?? '⚠️', style: const TextStyle(fontSize: 26)), const Spacer(), Text(s['title'], maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12))]))),
    ])),
    if (error != null && items == null) Card(child: Padding(padding: const EdgeInsets.all(16), child: Text('ফিড লোড করা যায়নি: $error'))),
    if (items == null && error == null) ...[const PostSkeleton(), const PostSkeleton()],
    for (final i in items ?? []) PostTile(i),
  ]));
}

class SosBanner extends StatelessWidget {
  const SosBanner({super.key});
  @override
  Widget build(BuildContext context) => Container(margin: const EdgeInsets.only(bottom: 6), padding: const EdgeInsets.all(12), decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFE41E3F), Color(0xFFF5533D)]), borderRadius: BorderRadius.circular(8)),
    child: const Text('📞 জীবন ঝুঁকিতে থাকলে আগে 999 এ কল করুন (সূত্র: 999.gov.bd)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)));
}

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
          Text(i['reporter']?['name'] ?? 'বেনামী', style: const TextStyle(fontWeight: FontWeight.w600)),
          Text('${ago(i['created_at'])} · ${i['location']?['area_name'] ?? ''}', style: const TextStyle(color: RL.text2, fontSize: 12)),
        ])),
        StatusChip(i),
      ]),
      const SizedBox(height: 8),
      Text(i['title'], style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
      if (i['description'] != null) Text(i['description'], maxLines: 3, overflow: TextOverflow.ellipsis),
      if (media.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(media[0]['url'], height: 200, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox()))),
      const Divider(),
      Text('👁️ ${i['counts']['confirm']} জন দেখেছেন · 💬 ${i['counts']['comments']}', style: const TextStyle(color: RL.text2)),
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
  Map? i; String? error;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async { try { final r = await Api.get('/incidents/${widget.id}'); setState(() => i = r['data']); } catch (e) { setState(() => error = '$e'); } }
  Future<void> react() async {
    if (Api.token == null) return;
    try { await Api.post('/incidents/${widget.id}/reactions', {'type': 'confirm'}); await load(); } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'))); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('রিপোর্ট')),
    body: i == null ? (error != null ? Center(child: Text(error!)) : const Padding(padding: EdgeInsets.all(8), child: PostSkeleton()))
      : ListView(padding: const EdgeInsets.all(8), children: [
          PostTile(i!),
          FilledButton.icon(onPressed: Api.token == null ? null : react, icon: const Icon(Icons.visibility), label: const Text('আমিও দেখছি')),
          const SizedBox(height: 8),
          const Text('কমিউনিটি সমর্থিত মানে সরকারি যাচাই নয়। প্রকাশ্যে শুধু আনুমানিক অবস্থান দেখানো হয়।', style: TextStyle(color: RL.text2, fontSize: 12)),
        ]));
}

/// Google map of incidents (approximate public points) with a list fallback for accessibility.
class MapScreen extends StatefulWidget {
  const MapScreen({super.key});
  @override
  State<MapScreen> createState() => _MapState();
}

class _MapState extends State<MapScreen> {
  List? pts;
  @override
  void initState() { super.initState(); Api.get('/incidents/map?hours=168').then((r) { if (mounted) setState(() => pts = r['data']); }).catchError((_) {}); }
  double hue(String s) => {'low': BitmapDescriptor.hueGreen, 'moderate': BitmapDescriptor.hueYellow, 'high': BitmapDescriptor.hueOrange}[s] ?? BitmapDescriptor.hueRed;
  @override
  Widget build(BuildContext context) => Column(children: [
    SizedBox(height: 320, child: pts == null ? const Shim(child: Sk(h: 320, r: 0)) : GoogleMap(
      initialCameraPosition: const CameraPosition(target: LatLng(23.78, 90.39), zoom: 11),
      markers: {for (final p in pts!) Marker(markerId: MarkerId(p['id']), position: LatLng((p['lat'] as num).toDouble(), (p['lng'] as num).toDouble()),
        icon: BitmapDescriptor.defaultMarkerWithHue(hue(p['severity'])), infoWindow: InfoWindow(title: '${RL.catIcon[p['category']]} ${p['title']}', snippet: p['area_name'], onTap: () => openDetail(context, p['id'])))},
    )),
    const Padding(padding: EdgeInsets.all(8), child: Text('মার্কারগুলো আনুমানিক অবস্থান দেখায়। খালি এলাকা মানে রাস্তা নিরাপদ নয়।', style: TextStyle(color: RL.text2, fontSize: 12))),
    Expanded(child: pts == null ? ListView(children: const [PostSkeleton()]) : ListView(children: [
      for (final p in pts!) ListTile(leading: Text(RL.catIcon[p['category']] ?? '⚠️', style: const TextStyle(fontSize: 24)), title: Text(p['title']), subtitle: Text('${p['area_name'] ?? ''} · ${ago(p['created_at'])}'), onTap: () => openDetail(context, p['id'])),
    ])),
  ]);
}

class LoginScreen extends StatefulWidget {
  final VoidCallback onDone;
  const LoginScreen({super.key, required this.onDone});
  @override
  State<LoginScreen> createState() => _LoginState();
}

class _LoginState extends State<LoginScreen> {
  final email = TextEditingController(), pass = TextEditingController();
  String? err; bool busy = false;
  Future<void> submit() async {
    setState(() { busy = true; err = null; });
    try { final r = await Api.post('/auth/login', {'email': email.text.trim(), 'password': pass.text, 'device_name': 'flutter'}); await Api.setToken(r['token']); widget.onDone(); }
    catch (e) { setState(() => err = '$e'); } finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(24), children: [
    const Center(child: LogoMark(size: 72)), const SizedBox(height: 12),
    const Center(child: Text('RescueLens', style: TextStyle(color: RL.primary, fontSize: 34, fontWeight: FontWeight.w800))),
    const SizedBox(height: 24),
    TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'ইমেইল', border: OutlineInputBorder())),
    const SizedBox(height: 12),
    TextField(controller: pass, obscureText: true, decoration: const InputDecoration(labelText: 'পাসওয়ার্ড', border: OutlineInputBorder())),
    if (err != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(err!, style: const TextStyle(color: Color(0xFFE41E3F)))),
    const SizedBox(height: 12),
    FilledButton(onPressed: busy ? null : submit, child: const Padding(padding: EdgeInsets.all(12), child: Text('লগ ইন', style: TextStyle(fontSize: 17)))),
    const SizedBox(height: 8),
    const Text('ডেমো: rina@rescuelens.test / password', textAlign: TextAlign.center, style: TextStyle(color: RL.text2)),
  ]);
}

/// Report flow: pick photo, upload, AI suggestion (labeled), edit, explicit confirmation, post.
class ReportScreen extends StatefulWidget {
  const ReportScreen({super.key});
  @override
  State<ReportScreen> createState() => _ReportState();
}

class _ReportState extends State<ReportScreen> {
  XFile? file; Map? analysis; String? mediaId; String? err; bool busy = false, confirm = false;
  final title = TextEditingController(), desc = TextEditingController();
  String category = 'road_accident', severity = 'moderate';

  Future<void> pick(ImageSource src) async {
    final f = await ImagePicker().pickImage(source: src, maxWidth: 2048, imageQuality: 85);
    if (f == null) return;
    setState(() { file = f; busy = true; err = null; });
    try {
      mediaId = (await Api.upload('/media', f.path))['id'];
      final a = (await Api.post('/analyses', {'media_id': mediaId, 'ai_consent': true, 'language': 'bn'}))['analysis'];
      setState(() {
        analysis = a;
        final r = a['result'];
        if (r != null) { title.text = r['suggested_title']; desc.text = r['summary']; }
        category = a['primary_hazard'] ?? category; severity = a['severity'] ?? severity;
      });
    } catch (e) { setState(() => err = '$e'); } finally { setState(() => busy = false); }
  }

  Future<void> post() async {
    setState(() => busy = true);
    try {
      final r = await Api.post('/incidents', {'title': title.text, 'description': desc.text, 'category': category, 'severity': severity, 'lat': 23.8103, 'lng': 90.4125,
        'consent_public': true, 'media_ids': [?mediaId], if (analysis != null) 'analysis_id': analysis!['id']}, headers: {'Idempotency-Key': 'rl_m_${DateTime.now().microsecondsSinceEpoch}'});
      if (mounted) openDetail(context, r['data']['id']);
    } catch (e) { setState(() => err = '$e'); } finally { if (mounted) setState(() => busy = false); }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(12), children: [
    const SosBanner(),
    Row(children: [
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.camera), icon: const Icon(Icons.photo_camera), label: const Text('ক্যামেরা'))),
      const SizedBox(width: 8),
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.gallery), icon: const Icon(Icons.photo), label: const Text('গ্যালারি'))),
    ]),
    if (busy) const Padding(padding: EdgeInsets.all(8), child: Shim(child: Column(children: [Sk(h: 14), SizedBox(height: 8), Sk(w: 200, h: 14)]))),
    if (analysis != null) Card(color: RL.primary.withValues(alpha: .08), child: Padding(padding: const EdgeInsets.all(12), child: Text('✨ AI প্রস্তাবনা (${analysis!['mode'] == 'demo' ? 'DEMO' : 'লাইভ'}): ${analysis!['mode_label'] ?? ''}'))),
    if (file != null) ...[
      TextField(controller: title, maxLength: 160, decoration: const InputDecoration(labelText: 'শিরোনাম')),
      TextField(controller: desc, maxLines: 3, decoration: const InputDecoration(labelText: 'বিবরণ')),
      Wrap(spacing: 6, children: [for (final c in RL.catIcon.keys) ChoiceChip(label: Text(RL.catIcon[c]!), selected: category == c, onSelected: (_) => setState(() => category = c))]),
      Wrap(spacing: 6, children: [for (final s in RL.sev.keys) ChoiceChip(label: Text(s), selected: severity == s, onSelected: (_) => setState(() => severity = s))]),
      CheckboxListTile(value: confirm, onChanged: (v) => setState(() => confirm = v ?? false), title: const Text('আমি তথ্য যাচাই করেছি এবং প্রকাশে সম্মতি দিচ্ছি')),
      FilledButton(onPressed: confirm && !busy && title.text.isNotEmpty ? post : null, child: const Text('পোস্ট করুন')),
    ],
    if (err != null) Text(err!, style: const TextStyle(color: Color(0xFFE41E3F))),
  ]);
}
