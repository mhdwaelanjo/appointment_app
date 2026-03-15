import 'package:add_2_calendar/add_2_calendar.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../models/appointment.dart';
import '../models/availability.dart';
import '../services/notification_service.dart';

class AppointmentController extends GetxController {
  final appointments = <Appointment>[].obs;
  final selectedDate = DateTime.now().obs;
  final sessionDurationMinutes = 30.obs;

  final workingDays = <int, DayAvailability>{}.obs;
  final breaksByWeekday = <int, List<TimeRange>>{}.obs;
  final temporaryClosures = <TemporaryClosure>[].obs;

  @override
  void onInit() {
    super.onInit();
    _seedAvailability();
    _seedSampleData();
  }

  void _seedAvailability() {
    for (var weekday = DateTime.monday; weekday <= DateTime.sunday; weekday++) {
      workingDays[weekday] = DayAvailability(
        enabled: weekday <= DateTime.friday,
        start: const TimeOfDay(hour: 9, minute: 0),
        end: const TimeOfDay(hour: 17, minute: 0),
      );

      breaksByWeekday[weekday] = weekday <= DateTime.friday
          ? [
              TimeRange(
                start: const TimeOfDay(hour: 12, minute: 0),
                end: const TimeOfDay(hour: 13, minute: 0),
              ),
            ]
          : [];
    }
  }

  void _seedSampleData() {
    final today = DateTime.now();
    final first = DateTime(today.year, today.month, today.day, 10, 0);
    final second = DateTime(today.year, today.month, today.day, 14, 0);

    appointments.assignAll([
      Appointment(
        id: 'APT-1001',
        title: 'Consultation',
        clientName: 'John Miller',
        startAt: first,
        endAt: first.add(const Duration(minutes: 30)),
        reminderMinutes: 30,
        notes: 'First-time booking',
      ),
      Appointment(
        id: 'APT-1002',
        title: 'Follow-up',
        clientName: 'Sophia Clark',
        startAt: second,
        endAt: second.add(const Duration(minutes: 60)),
        reminderMinutes: 60,
      ),
    ]);
  }

  List<Appointment> appointmentsForDate(DateTime date) {
    return appointments.where((appt) => _isSameDate(appt.startAt, date)).toList()..sort((a, b) => a.startAt.compareTo(b.startAt));
  }

  List<DateTime> generateAvailableSlots(DateTime date, {int? durationMinutes}) {
    final day = workingDays[date.weekday];
    if (day == null || !day.enabled) {
      return [];
    }

    final slots = <DateTime>[];
    final slotMinutes = durationMinutes ?? sessionDurationMinutes.value;
    final slotDuration = Duration(minutes: slotMinutes);

    var cursor = _atTime(date, day.start);
    final dayEnd = _atTime(date, day.end);

    while (cursor.add(slotDuration).isBefore(dayEnd) || cursor.add(slotDuration).isAtSameMomentAs(dayEnd)) {
      final slotEnd = cursor.add(slotDuration);

      if (!_isBlockedByBreakOrClosure(date, cursor, slotEnd) && !_hasConflict(cursor, slotEnd)) {
        slots.add(cursor);
      }

      cursor = cursor.add(slotDuration);
    }

    return slots;
  }

  String? addAppointment({
    required String title,
    required String clientName,
    required String notes,
    required DateTime startAt,
    required int durationMinutes,
    required int reminderMinutes,
  }) {
    final endAt = startAt.add(Duration(minutes: durationMinutes));

    final validation = _validateSlot(
      startAt: startAt,
      endAt: endAt,
    );

    if (validation != null) {
      return validation;
    }

    final appointment = Appointment(
      id: 'APT-${DateTime.now().millisecondsSinceEpoch}',
      title: title,
      clientName: clientName,
      notes: notes,
      startAt: startAt,
      endAt: endAt,
      reminderMinutes: reminderMinutes,
    );

    appointments.add(appointment);
    appointments.sort((a, b) => a.startAt.compareTo(b.startAt));
    NotificationService.instance.scheduleReminder(appointment);
    return null;
  }

  String? updateAppointment(
    Appointment original, {
    required String title,
    required String clientName,
    required String notes,
    required DateTime startAt,
    required int durationMinutes,
    required int reminderMinutes,
    required AppointmentStatus status,
  }) {
    final endAt = startAt.add(Duration(minutes: durationMinutes));

    final validation = _validateSlot(
      startAt: startAt,
      endAt: endAt,
      ignoreAppointmentId: original.id,
      allowCancelled: status == AppointmentStatus.cancelled,
    );

    if (validation != null) {
      return validation;
    }

    final updated = original.copyWith(
      title: title,
      clientName: clientName,
      notes: notes,
      startAt: startAt,
      endAt: endAt,
      reminderMinutes: reminderMinutes,
      status: status,
    );

    final index = appointments.indexWhere((appt) => appt.id == original.id);
    if (index == -1) {
      return 'Appointment not found.';
    }

    appointments[index] = updated;
    appointments.sort((a, b) => a.startAt.compareTo(b.startAt));

    if (status == AppointmentStatus.cancelled) {
      NotificationService.instance.cancelReminder(original.id);
    } else {
      NotificationService.instance.scheduleReminder(updated);
    }

    return null;
  }

  void deleteAppointment(String id) {
    appointments.removeWhere((appt) => appt.id == id);
    NotificationService.instance.cancelReminder(id);
  }

  void cancelAppointment(String id) {
    final index = appointments.indexWhere((appt) => appt.id == id);
    if (index == -1) {
      return;
    }

    appointments[index] = appointments[index].copyWith(status: AppointmentStatus.cancelled);
    NotificationService.instance.cancelReminder(id);
  }

  void setSessionDuration(int minutes) {
    sessionDurationMinutes.value = minutes;
  }

  void updateDayEnabled(int weekday, bool enabled) {
    final current = workingDays[weekday];
    if (current == null) {
      return;
    }

    workingDays[weekday] = current.copyWith(enabled: enabled);
  }

  void updateDayHours(int weekday, {TimeOfDay? start, TimeOfDay? end}) {
    final current = workingDays[weekday];
    if (current == null) {
      return;
    }

    final updated = current.copyWith(
      start: start,
      end: end,
    );

    if (_timeToMinutes(updated.end) <= _timeToMinutes(updated.start)) {
      return;
    }

    workingDays[weekday] = updated;
  }

  void addBreak(int weekday, TimeRange range) {
    if (!range.isValid()) {
      return;
    }

    final dayBreaks = List<TimeRange>.from(breaksByWeekday[weekday] ?? []);
    dayBreaks.add(range);
    breaksByWeekday[weekday] = dayBreaks;
  }

  void removeBreak(int weekday, int index) {
    final dayBreaks = List<TimeRange>.from(breaksByWeekday[weekday] ?? []);
    if (index < 0 || index >= dayBreaks.length) {
      return;
    }

    dayBreaks.removeAt(index);
    breaksByWeekday[weekday] = dayBreaks;
  }

  void addTemporaryClosure(DateTime date, TimeRange range, {String reason = ''}) {
    if (!range.isValid()) {
      return;
    }

    temporaryClosures.add(
      TemporaryClosure(
        id: 'CLS-${DateTime.now().millisecondsSinceEpoch}',
        date: DateTime(date.year, date.month, date.day),
        range: range,
        reason: reason,
      ),
    );
  }

  void removeTemporaryClosure(String id) {
    temporaryClosures.removeWhere((closure) => closure.id == id);
  }

  Future<void> exportToDeviceCalendar(Appointment appointment) {
    final event = Event(
      title: appointment.title,
      description: 'Client: ${appointment.clientName}\n${appointment.notes}',
      startDate: appointment.startAt,
      endDate: appointment.endAt,
      iosParams: const IOSParams(reminder: Duration(minutes: 15)),
      androidParams: const AndroidParams(emailInvites: []),
    );

    return Add2Calendar.addEvent2Cal(event);
  }

  String? _validateSlot({
    required DateTime startAt,
    required DateTime endAt,
    String? ignoreAppointmentId,
    bool allowCancelled = false,
  }) {
    final day = workingDays[startAt.weekday];
    if (day == null || !day.enabled) {
      return 'Selected day is not available for appointments.';
    }

    final dayStart = _atTime(startAt, day.start);
    final dayEnd = _atTime(startAt, day.end);

    if (startAt.isBefore(dayStart) || endAt.isAfter(dayEnd)) {
      return 'Appointment is outside configured working hours.';
    }

    if (_isBlockedByBreakOrClosure(startAt, startAt, endAt)) {
      return 'Selected time is blocked by a break or temporary closure.';
    }

    if (!allowCancelled && _hasConflict(startAt, endAt, ignoreAppointmentId: ignoreAppointmentId)) {
      return 'Selected slot conflicts with another appointment.';
    }

    return null;
  }

  bool _isBlockedByBreakOrClosure(DateTime date, DateTime start, DateTime end) {
    final dayBreaks = breaksByWeekday[date.weekday] ?? [];

    final hasBreakConflict = dayBreaks.any((breakRange) {
      final breakStart = _atTime(date, breakRange.start);
      final breakEnd = _atTime(date, breakRange.end);
      return _rangesOverlap(start, end, breakStart, breakEnd);
    });

    if (hasBreakConflict) {
      return true;
    }

    final closuresForDay = temporaryClosures.where((closure) => _isSameDate(closure.date, date)).toList();

    return closuresForDay.any((closure) {
      final closureStart = _atTime(date, closure.range.start);
      final closureEnd = _atTime(date, closure.range.end);
      return _rangesOverlap(start, end, closureStart, closureEnd);
    });
  }

  bool _hasConflict(
    DateTime start,
    DateTime end, {
    String? ignoreAppointmentId,
  }) {
    for (final appointment in appointments) {
      if (appointment.id == ignoreAppointmentId) {
        continue;
      }

      if (appointment.status == AppointmentStatus.cancelled) {
        continue;
      }

      if (_rangesOverlap(start, end, appointment.startAt, appointment.endAt)) {
        return true;
      }
    }

    return false;
  }

  bool _isSameDate(DateTime left, DateTime right) {
    return left.year == right.year && left.month == right.month && left.day == right.day;
  }

  DateTime _atTime(DateTime date, TimeOfDay time) {
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  bool _rangesOverlap(
    DateTime startA,
    DateTime endA,
    DateTime startB,
    DateTime endB,
  ) {
    return startA.isBefore(endB) && startB.isBefore(endA);
  }

  int _timeToMinutes(TimeOfDay value) => (value.hour * 60) + value.minute;
}
