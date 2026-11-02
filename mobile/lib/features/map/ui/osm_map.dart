import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart' as fm;
import 'package:latlong2/latlong.dart' as ll;
import 'package:rescuelens/core/theme.dart';

/// OpenStreetMap fallback used when the build has no Google Maps key, so the map still works out of the box.
/// Points are the approximate public locations from the API, never the precise reporter location.
class OsmPoint {
  final String id; final double lat, lng; final String category, severity; final VoidCallback? onTap;
  const OsmPoint(this.id, this.lat, this.lng, this.category, this.severity, {this.onTap});
}

class OsmMap extends StatelessWidget {
  final double lat, lng, zoom; final List<OsmPoint> points; final bool interactive; final fm.MapController? controller;
  final double? areaRadiusMeters; final void Function(double zoom)? onZoom;
  const OsmMap({super.key, required this.lat, required this.lng, required this.zoom, this.points = const [], this.interactive = true, this.controller, this.areaRadiusMeters, this.onZoom});

  static Color sev(String s) => {'low': const Color(0xFF31A24C), 'moderate': const Color(0xFFF7B928), 'high': const Color(0xFFF5793B)}[s] ?? const Color(0xFFE41E3F);

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    Widget tiles = fm.TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', userAgentPackageName: 'com.rescuelens.rescuelens', maxZoom: 19);
    // Dim the light OSM tiles in dark mode so the map matches the app theme.
    if (dark) tiles = ColorFiltered(colorFilter: const ColorFilter.matrix([-0.21, -0.72, -0.07, 0, 235, -0.21, -0.72, -0.07, 0, 238, -0.21, -0.72, -0.07, 0, 245, 0, 0, 0, 1, 0]), child: tiles);
    return fm.FlutterMap(
      mapController: controller,
      options: fm.MapOptions(initialCenter: ll.LatLng(lat, lng), initialZoom: zoom, minZoom: 1,
        interactionOptions: fm.InteractionOptions(flags: interactive ? fm.InteractiveFlag.all & ~fm.InteractiveFlag.rotate : fm.InteractiveFlag.none),
        onPositionChanged: (cam, _) => onZoom?.call(cam.zoom)),
      children: [
        tiles,
        if (areaRadiusMeters != null) fm.CircleLayer(circles: [fm.CircleMarker(point: ll.LatLng(lat, lng), radius: areaRadiusMeters!, useRadiusInMeter: true,
          color: sev(points.isEmpty ? '' : points.first.severity).withValues(alpha: .25), borderColor: sev(points.isEmpty ? '' : points.first.severity), borderStrokeWidth: 2)]),
        if (areaRadiusMeters == null) fm.MarkerLayer(markers: [for (final p in points) fm.Marker(point: ll.LatLng(p.lat, p.lng), width: 34, height: 34, alignment: Alignment.topCenter,
          child: GestureDetector(onTap: p.onTap, child: Icon(RL.cat(p.category), color: Colors.white, size: 18,
            shadows: const [Shadow(blurRadius: 2)]).let((i) => Container(decoration: BoxDecoration(color: sev(p.severity), shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2)), alignment: Alignment.center, child: i))))]),
        const fm.SimpleAttributionWidget(source: Text('OpenStreetMap contributors'), alignment: Alignment.bottomLeft),
      ],
    );
  }
}

extension _Let<T> on T { R let<R>(R Function(T) f) => f(this); }
