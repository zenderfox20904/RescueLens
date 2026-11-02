import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'theme.dart';

/// Facebook style shimmer placeholder block.
class Sk extends StatelessWidget {
  final double? w; final double h; final double r;
  const Sk({super.key, this.w, this.h = 12, this.r = 6});
  @override
  Widget build(BuildContext context) => Container(width: w, height: h, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(r)));
}

class Shim extends StatelessWidget {
  final Widget child;
  const Shim({super.key, required this.child});
  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Shimmer.fromColors(baseColor: dark ? RL.darkHover : const Color(0xFFE4E6EB), highlightColor: dark ? const Color(0xFF4E4F50) : const Color(0xFFF5F6F7), child: child);
  }
}

class PostSkeleton extends StatelessWidget {
  const PostSkeleton({super.key});
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(12), child: Shim(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
    Row(children: [Sk(w: 40, h: 40, r: 20), SizedBox(width: 8), Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Sk(w: 140), SizedBox(height: 6), Sk(w: 90, h: 10)])]),
    SizedBox(height: 12), Sk(h: 14), SizedBox(height: 8), Sk(w: 220, h: 14), SizedBox(height: 12), Sk(h: 200, r: 8),
  ]))));
}

class StoriesSkeleton extends StatelessWidget {
  const StoriesSkeleton({super.key});
  @override
  Widget build(BuildContext context) => SizedBox(height: 180, child: Shim(child: ListView.separated(scrollDirection: Axis.horizontal, padding: const EdgeInsets.all(8), itemCount: 5, separatorBuilder: (_, _) => const SizedBox(width: 8), itemBuilder: (_, _) => const Sk(w: 110, h: 170, r: 10))));
}

class LogoMark extends StatelessWidget {
  final double size;
  const LogoMark({super.key, this.size = 36});
  @override
  Widget build(BuildContext context) => ClipRRect(borderRadius: BorderRadius.circular(size * 0.25), child: Image.asset('assets/icon.png', width: size, height: size));
}

class StatusChip extends StatelessWidget {
  final Map i;
  const StatusChip(this.i, {super.key});
  @override
  Widget build(BuildContext context) {
    final verified = i['status'] == 'verified';
    final c = verified ? const Color(0xFF31A24C) : RL.sev[i['severity']] ?? RL.text2;
    return Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: c.withValues(alpha: .15), borderRadius: BorderRadius.circular(10)),
      child: Text(verified ? '✔ যাচাইকৃত' : '${i['status']}', style: TextStyle(color: c, fontSize: 12, fontWeight: FontWeight.w600)));
  }
}
