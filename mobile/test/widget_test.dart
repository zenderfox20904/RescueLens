import 'package:flutter_test/flutter_test.dart';
import 'package:rescuelens/core/theme.dart';

void main() {
  test('shared tokens match the web palette', () {
    expect(RL.primary.toARGB32(), 0xFF1877F2);
    expect(RL.bg.toARGB32(), 0xFFF0F2F5);
  });
}
