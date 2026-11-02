import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:video_player/video_player.dart';

bool isVideo(Map m) => '${m['mime'] ?? ''}'.startsWith('video/');

/// Card media: first item large, a "+N" badge for more. Tapping opens the full screen viewer.
class MediaStrip extends StatelessWidget {
  final List media;
  const MediaStrip(this.media, {super.key});
  @override
  Widget build(BuildContext context) {
    final m = media.first as Map;
    final w = (m['width'] as num?)?.toDouble(), h = (m['height'] as num?)?.toDouble();
    final ratio = (w != null && h != null && h > 0) ? (w / h).clamp(0.8, 1.9) : 16 / 9;
    return GestureDetector(
      onTap: () => openViewer(context, media, 0),
      child: ClipRRect(borderRadius: BorderRadius.circular(12), child: AspectRatio(aspectRatio: ratio, child: Stack(fit: StackFit.expand, children: [
        Hero(tag: 'm${m['id']}', child: isVideo(m) ? const ColoredBox(color: Colors.black) : NetImage(m['url'])),
        if (isVideo(m)) const Center(child: CircleAvatar(radius: 28, backgroundColor: Colors.black54, child: Icon(LucideIcons.play, size: 36, color: Colors.white))),
        if (media.length > 1) Positioned(right: 8, top: 8, child: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(12)),
          child: Text('+${media.length - 1}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)))),
      ]))),
    );
  }
}

class NetImage extends StatelessWidget {
  final String url; final BoxFit fit;
  const NetImage(this.url, {super.key, this.fit = BoxFit.cover});
  @override
  Widget build(BuildContext context) => CachedNetworkImage(imageUrl: url, fit: fit,
    cacheKey: url.split('?').first,
    placeholder: (_, _) => const Shim(child: Sk(h: double.infinity, r: 0)),
    errorWidget: (_, _, _) => const ColoredBox(color: Color(0x22000000), child: Center(child: Icon(LucideIcons.imageOff, color: RL.text2))));
}

void openViewer(BuildContext c, List media, int start) => Navigator.push(c, PageRouteBuilder(opaque: false, barrierColor: Colors.black,
  pageBuilder: (_, _, _) => MediaViewer(media: media, start: start), transitionsBuilder: (_, a, _, child) => FadeTransition(opacity: a, child: child)));

/// Full screen swipeable viewer with pinch zoom for photos and playback for videos.
class MediaViewer extends StatefulWidget {
  final List media; final int start;
  const MediaViewer({super.key, required this.media, required this.start});
  @override
  State<MediaViewer> createState() => _MediaViewerState();
}

class _MediaViewerState extends State<MediaViewer> {
  late final PageController pc = PageController(initialPage: widget.start);
  late int i = widget.start;
  @override
  void dispose() { pc.dispose(); super.dispose(); }
  @override
  Widget build(BuildContext context) => Scaffold(backgroundColor: Colors.black,
    appBar: AppBar(backgroundColor: Colors.transparent, foregroundColor: Colors.white, title: Text('${i + 1} / ${widget.media.length}')),
    body: PageView.builder(controller: pc, itemCount: widget.media.length, onPageChanged: (v) => setState(() => i = v), itemBuilder: (_, k) {
      final m = widget.media[k] as Map;
      return isVideo(m) ? VideoBox(m['url']) : InteractiveViewer(maxScale: 5, child: Center(child: Hero(tag: 'm${m['id']}', child: NetImage(m['url'], fit: BoxFit.contain))));
    }));
}

class VideoBox extends StatefulWidget {
  final String url;
  const VideoBox(this.url, {super.key});
  @override
  State<VideoBox> createState() => _VideoBoxState();
}

class _VideoBoxState extends State<VideoBox> {
  late final VideoPlayerController v = VideoPlayerController.networkUrl(Uri.parse(widget.url));
  bool failed = false;
  @override
  void initState() { super.initState(); v.initialize().then((_) { if (mounted) setState(() {}); v.play(); }).catchError((_) { if (mounted) setState(() => failed = true); }); }
  @override
  void dispose() { v.dispose(); super.dispose(); }
  @override
  Widget build(BuildContext context) {
    if (failed) return const Center(child: Icon(LucideIcons.circleAlert, color: Colors.white54, size: 40));
    if (!v.value.isInitialized) return const Center(child: CircularProgressIndicator());
    return GestureDetector(onTap: () => setState(() => v.value.isPlaying ? v.pause() : v.play()), child: Center(child: AspectRatio(aspectRatio: v.value.aspectRatio, child: Stack(alignment: Alignment.center, children: [
      VideoPlayer(v),
      if (!v.value.isPlaying) const Icon(LucideIcons.circlePlay, color: Colors.white70, size: 64),
      Positioned(left: 0, right: 0, bottom: 0, child: VideoProgressIndicator(v, allowScrubbing: true)),
    ]))));
  }
}
