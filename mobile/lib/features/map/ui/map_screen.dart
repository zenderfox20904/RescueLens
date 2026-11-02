import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';

/// Google map of incidents (approximate public points) with a list fallback for accessibility.
class MapScreen extends StatefulWidget {
  const MapScreen({super.key});
  @override
  State<MapScreen> createState() => _MapState();
}

class _MapState extends State<MapScreen> {
  List? pts; bool? maps;
  @override
  void initState() { super.initState(); NativeConfig.hasMapsKey().then((v) { if (mounted) setState(() => maps = v); }); Api.get('/incidents/map?hours=168&lang=$curLang').then((r) { if (mounted) setState(() => pts = r['data']); }).catchError((_) {}); }
  double hue(String s) => {'low': BitmapDescriptor.hueGreen, 'moderate': BitmapDescriptor.hueYellow, 'high': BitmapDescriptor.hueOrange}[s] ?? BitmapDescriptor.hueRed;
  @override
  Widget build(BuildContext context) => Column(children: [
    SizedBox(height: 320, child: pts == null || maps == null ? const Shim(child: Sk(h: 320, r: 0)) : maps == false ? const MapUnavailable() : GoogleMap(
      initialCameraPosition: const CameraPosition(target: LatLng(23.78, 90.39), zoom: 11),
      markers: {for (final p in pts!) Marker(markerId: MarkerId(p['id']), position: LatLng((p['lat'] as num).toDouble(), (p['lng'] as num).toDouble()),
        icon: BitmapDescriptor.defaultMarkerWithHue(hue(p['severity'])), infoWindow: InfoWindow(title: title(p), snippet: p['area_name'], onTap: () => openDetail(context, p['id'])))},
    )),
    Padding(padding: const EdgeInsets.all(8), child: Text(tr('map_note'), style: const TextStyle(color: RL.text2, fontSize: 12))),
    Expanded(child: pts == null ? ListView(children: const [PostSkeleton()]) : ListView(padding: const EdgeInsets.only(bottom: 120), children: [
      for (final p in pts!) ListTile(leading: Icon(RL.cat(p['category']), size: 24, color: RL.primary), title: Text(title(p)), subtitle: Text('${p['area_name'] ?? ''} · ${ago(p['created_at'])}'), onTap: () => openDetail(context, p['id'])),
    ])),
  ]);
}

/// Shown when the map cannot be displayed on this build; the list below still works.
class MapUnavailable extends StatelessWidget {
  const MapUnavailable({super.key});
  @override
  Widget build(BuildContext context) => Container(color: Theme.of(context).cardColor, alignment: Alignment.center, padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(LucideIcons.map, size: 40, color: RL.text2), const SizedBox(height: 8),
    Text(tr('map_unavailable'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2)),
  ]));
}
