import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:rescuelens/core/theme.dart';

/// Incident type pins: the Lucide category icon on a Meta blue disc. The ring and shadow follow the theme.
class MapPins {
  static final Map<String, BitmapDescriptor> _cache = {};
  static BitmapDescriptor? get(String cat, bool dark) => _cache['$cat|$dark'];

  static Future<void> warm(bool dark) async {
    for (final c in RL.categories) {
      final k = '$c|$dark';
      if (_cache.containsKey(k)) continue;
      _cache[k] = await _draw(RL.cat(c), dark);
    }
  }

  static Future<BitmapDescriptor> _draw(IconData icon, bool dark) async {
    const s = 3.0, d = 36.0 * s, tail = 8.0 * s;
    final rec = ui.PictureRecorder();
    final cv = Canvas(rec);
    final ring = dark ? const Color(0xFF18191A) : Colors.white;
    final blue = dark ? const Color(0xFF2D88FF) : RL.primary;
    final c = Offset(d / 2, d / 2);
    cv.drawCircle(c.translate(0, 1.5 * s), d / 2 - 1 * s, Paint()..color = Colors.black.withValues(alpha: dark ? .5 : .25)..maskFilter = const MaskFilter.blur(BlurStyle.normal, 3));
    final tri = Path()..moveTo(d / 2 - 6 * s, d - 6 * s)..lineTo(d / 2 + 6 * s, d - 6 * s)..lineTo(d / 2, d + tail - 2 * s)..close();
    cv.drawPath(tri, Paint()..color = ring);
    cv.drawCircle(c, d / 2 - 1 * s, Paint()..color = ring);
    cv.drawCircle(c, d / 2 - 3.5 * s, Paint()..color = blue);
    final tp = TextPainter(textDirection: TextDirection.ltr, text: TextSpan(text: String.fromCharCode(icon.codePoint),
      style: TextStyle(fontSize: 18 * s, fontFamily: icon.fontFamily, package: icon.fontPackage, color: Colors.white)))..layout();
    tp.paint(cv, c - Offset(tp.width / 2, tp.height / 2));
    final img = await rec.endRecording().toImage(d.toInt(), (d + tail).toInt());
    final png = await img.toByteData(format: ui.ImageByteFormat.png);
    return BitmapDescriptor.bytes(png!.buffer.asUint8List(), imagePixelRatio: s);
  }
}
