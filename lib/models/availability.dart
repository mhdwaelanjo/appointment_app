import 'package:flutter/material.dart';

class DayAvailability {
  DayAvailability({
    required this.enabled,
    required this.start,
    required this.end,
  });

  final bool enabled;
  final TimeOfDay start;
  final TimeOfDay end;

  DayAvailability copyWith({
    bool? enabled,
    TimeOfDay? start,
    TimeOfDay? end,
  }) {
    return DayAvailability(
      enabled: enabled ?? this.enabled,
      start: start ?? this.start,
      end: end ?? this.end,
    );
  }
}

class TimeRange {
  TimeRange({required this.start, required this.end});

  final TimeOfDay start;
  final TimeOfDay end;

  bool isValid() => toMinutes(end) > toMinutes(start);

  static int toMinutes(TimeOfDay time) => (time.hour * 60) + time.minute;
}

class TemporaryClosure {
  TemporaryClosure({
    required this.id,
    required this.date,
    required this.range,
    this.reason = '',
  });

  final String id;
  final DateTime date;
  final TimeRange range;
  final String reason;
}
