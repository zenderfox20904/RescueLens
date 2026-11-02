import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'dart:math' as math;
import 'package:geolocator/geolocator.dart';
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
  // Nearby: within 25 km of the device (location stays on the phone). World: every public report from the last 30 days.
  // Opens on the active country: device location, then the profile country, then the IP country from the server.
  String scope = 'country'; bool get world => scope != 'nearby'; LatLng? here; String? cc; final Set<String> cats = {}; GoogleMapController? ctl;
  static const centers = <String, List<double>>{'BD': [23.7, 90.35, 6.5], 'IN': [22.5, 79, 4], 'PK': [30, 70, 4.5], 'NP': [28.3, 84.1, 6], 'LK': [7.8, 80.7, 6.5], 'ID': [-2.5, 118, 3.5],
    'PH': [12.5, 122, 4.5], 'NG': [9.1, 8.7, 5], 'KE': [0.2, 37.9, 5], 'GB': [54, -2.5, 4.5], 'US': [39.5, -98.5, 3], 'CA': [56, -96, 2.5], 'AU': [-25.3, 134, 3], 'JP': [36.2, 138.2, 4.5]};
  CameraUpdate get cam => scope == 'world' ? CameraUpdate.newLatLngZoom(const LatLng(20, 40), 1.3)
    : scope == 'nearby' ? CameraUpdate.newLatLngZoom(here ?? const LatLng(23.78, 90.39), 11)
    : here != null ? CameraUpdate.newLatLngZoom(here!, 6.5)
    : centers[cc] != null ? CameraUpdate.newLatLngZoom(LatLng(centers[cc]![0], centers[cc]![1]), centers[cc]![2]) : CameraUpdate.newLatLngZoom(const LatLng(20, 40), 1.3);
  Future<void> locate() async {
    try { var perm = await Geolocator.checkPermission(); if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.always || perm == LocationPermission.whileInUse) { final g = await Geolocator.getCurrentPosition(); here = LatLng(g.latitude, g.longitude); } } catch (_) {}
  }
  @override
  void initState() {
    super.initState();
    NativeConfig.hasMapsKey().then((v) { if (mounted) setState(() => maps = v); });
    Api.get('/incidents/map?hours=720&lang=$curLang').then((r) { if (mounted) setState(() => pts = r['data']); }).catchError((_) {});
    () async {
      await locate();
      if (here == null && Api.token != null) { try { cc = (await Api.get('/auth/me'))['user']?['country_code']; } catch (_) {} }
      if (here == null && cc == null) { try { cc = (await Api.get('/geo/ip'))['country_code']; } catch (_) {} }
      if (mounted) { setState(() {}); ctl?.animateCamera(cam); }
    }();
  }
  double km(LatLng a, double lat, double lng) { const r = math.pi / 180; final x = math.pow(math.sin((lat - a.latitude) * r / 2), 2) + math.cos(a.latitude * r) * math.cos(lat * r) * math.pow(math.sin((lng - a.longitude) * r / 2), 2); return 12742 * math.asin(math.sqrt(x)); }
  List get shown => (pts ?? []).where((p) => (cats.isEmpty || cats.contains(p['category'])) && (world || here == null || km(here!, (p['lat'] as num).toDouble(), (p['lng'] as num).toDouble()) <= 25)).toList();
  Future<void> setScope(String v) async {
    setState(() => scope = v);
    if (v == 'nearby' && here == null) {
      await locate();
      if (here == null && mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('map_location_off')), duration: const Duration(seconds: 3)));
    }
    if (!mounted) return; setState(() {});
    ctl?.animateCamera(cam);
  }
  double hue(String s) => {'low': BitmapDescriptor.hueGreen, 'moderate': BitmapDescriptor.hueYellow, 'high': BitmapDescriptor.hueOrange}[s] ?? BitmapDescriptor.hueRed;
  @override
  Widget build(BuildContext context) => Column(children: [
    SizedBox(height: 44, child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 8), children: [
      for (final v in const [('nearby', LucideIcons.locateFixed), ('country', LucideIcons.flag), ('world', LucideIcons.globe)])
        Padding(padding: const EdgeInsets.only(right: 6), child: ChoiceChip(avatar: Icon(v.$2, size: 16), label: Text(tr('map_${v.$1}')), selected: scope == v.$1, onSelected: (_) => setScope(v.$1))),
      const SizedBox(width: 6),
      for (final c in RL.categories) Padding(padding: const EdgeInsets.only(right: 6), child: FilterChip(avatar: Icon(RL.cat(c), size: 16), label: Text(tr('c_$c')), selected: cats.contains(c), onSelected: (v) => setState(() => v ? cats.add(c) : cats.remove(c)))),
    ])),
    SizedBox(height: 320, child: pts == null || maps == null ? const Shim(child: Sk(h: 320, r: 0)) : maps == false ? const MapUnavailable() : GoogleMap(
      initialCameraPosition: const CameraPosition(target: LatLng(20, 40), zoom: 1.3), onMapCreated: (c) { ctl = c; c.moveCamera(cam); },
      markers: {for (final p in shown) Marker(markerId: MarkerId(p['id']), position: LatLng((p['lat'] as num).toDouble(), (p['lng'] as num).toDouble()),
        icon: BitmapDescriptor.defaultMarkerWithHue(hue(p['severity'])), infoWindow: InfoWindow(title: title(p), snippet: p['area_name'], onTap: () => openDetail(context, p['id'])))},
    )),
    Padding(padding: const EdgeInsets.all(8), child: Text(tr('map_note'), style: TextStyle(color: RL.text2, fontSize: 12))),
    Expanded(child: pts == null ? ListView(children: const [PostSkeleton()]) : ListView(padding: const EdgeInsets.only(bottom: 120), children: [
      for (final p in shown) ListTile(leading: Icon(RL.cat(p['category']), size: 24, color: RL.primary), title: Text(title(p)), subtitle: Text('${p['area_name'] ?? ''} · ${ago(p['created_at'])}'), onTap: () => openDetail(context, p['id'])),
    ])),
  ]);
}

/// Shown when the map cannot be displayed on this build; the list below still works.
class MapUnavailable extends StatelessWidget {
  const MapUnavailable({super.key});
  @override
  Widget build(BuildContext context) => Container(color: Theme.of(context).cardColor, alignment: Alignment.center, padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(LucideIcons.map, size: 40, color: RL.text2), const SizedBox(height: 8),
    Text(tr('map_unavailable'), textAlign: TextAlign.center, style: TextStyle(color: RL.text2)),
  ]));
}
