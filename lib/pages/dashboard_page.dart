import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:table_calendar/table_calendar.dart';

import '../controllers/appointment_controller.dart';
import '../models/availability.dart';

class DashboardPage extends GetView<AppointmentController> {
  const DashboardPage({super.key});

  static const _weekdayLabels = {
    DateTime.monday: 'Monday',
    DateTime.tuesday: 'Tuesday',
    DateTime.wednesday: 'Wednesday',
    DateTime.thursday: 'Thursday',
    DateTime.friday: 'Friday',
    DateTime.saturday: 'Saturday',
    DateTime.sunday: 'Sunday',
  };

  @override
  Widget build(BuildContext context) {
    final format = DateFormat('hh:mm a');

    return Obx(() {
      final selected = controller.selectedDate.value;
      final events = controller.appointmentsForDate(selected);
      final dayConfig = controller.workingDays[selected.weekday];
      final dayBreaks = controller.breaksByWeekday[selected.weekday] ?? [];
      final closures = controller.temporaryClosures
          .where(
            (closure) => closure.date.year == selected.year && closure.date.month == selected.month && closure.date.day == selected.day,
          )
          .toList();

      return ListView(
        padding: const EdgeInsets.all(12),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: TableCalendar(
                firstDay: DateTime.now().subtract(const Duration(days: 365)),
                lastDay: DateTime.now().add(const Duration(days: 365 * 2)),
                focusedDay: selected,
                availableGestures: AvailableGestures.horizontalSwipe,
                selectedDayPredicate: (day) => DateUtils.isSameDay(day, selected),
                onDaySelected: (picked, _) {
                  final normalized = DateTime(picked.year, picked.month, picked.day);
                  controller.selectedDate.value = normalized;
                  _showDayAppointments(context, normalized);
                },
                locale: Localizations.localeOf(context).toString(),
                eventLoader: (day) => controller.appointmentsForDate(day),
                headerStyle: const HeaderStyle(formatButtonVisible: false),
                calendarBuilders: CalendarBuilders(
                  todayBuilder: (context, day, focusedDay) {
                    return Container(
                      margin: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.teal.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${day.day}',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                      ),
                    );
                  },
                  selectedBuilder: (context, day, focusedDay) {
                    return Container(
                      margin: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.teal,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${day.day}',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                      ),
                    );
                  },
                  markerBuilder: (context, day, events) {
                    if (events.isEmpty) {
                      return const SizedBox.shrink();
                    }

                    return Align(
                      alignment: Alignment.bottomRight,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                        margin: const EdgeInsets.only(right: 2, bottom: 2),
                        decoration: BoxDecoration(
                          color: Colors.lightBlue,
                          borderRadius: BorderRadius.circular(2),
                        ),
                        constraints: const BoxConstraints(minWidth: 16, minHeight: 14),
                        child: Text(
                          '${events.length}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            height: 1,
                          ),
                        ),
                      ),
                    );
                  },
                ),
                calendarStyle: const CalendarStyle(
                  markerDecoration: BoxDecoration(
                    color: Colors.teal,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Session Configuration',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  DropdownButton<int>(
                    value: controller.sessionDurationMinutes.value,
                    items: const [30, 60]
                        .map(
                          (minutes) => DropdownMenuItem(
                            value: minutes,
                            child: Text('$minutes minutes'),
                          ),
                        )
                        .toList(),
                    onChanged: (value) {
                      if (value != null) {
                        controller.setSessionDuration(value);
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Availability Management',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  ..._weekdayLabels.entries.map((entry) {
                    final day = controller.workingDays[entry.key];
                    if (day == null) {
                      return const SizedBox.shrink();
                    }

                    return Row(
                      children: [
                        Expanded(child: Text(entry.value)),
                        Switch(
                          value: day.enabled,
                          onChanged: (value) => controller.updateDayEnabled(entry.key, value),
                        ),
                        IconButton(
                          onPressed: day.enabled
                              ? () => _pickAndUpdateHours(
                                  context,
                                  weekday: entry.key,
                                  currentStart: day.start,
                                  currentEnd: day.end,
                                )
                              : null,
                          icon: const Icon(Icons.schedule),
                        ),
                      ],
                    );
                  }),
                  if (dayConfig != null)
                    Text(
                      'Selected day hours: '
                      '${_fmt(context, dayConfig.start)} - ${_fmt(context, dayConfig.end)}',
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Break / Temporary Closure Management',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [
                      OutlinedButton.icon(
                        onPressed: () => _addBreak(context, selected.weekday),
                        icon: const Icon(Icons.free_breakfast),
                        label: const Text('Add Break'),
                      ),
                      OutlinedButton.icon(
                        onPressed: () => _addClosure(context, selected),
                        icon: const Icon(Icons.block),
                        label: const Text('Add Closure'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text('Breaks for selected weekday:'),
                  ...dayBreaks.asMap().entries.map(
                    (entry) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '${_fmt(context, entry.value.start)} - ${_fmt(context, entry.value.end)}',
                      ),
                      trailing: IconButton(
                        onPressed: () => controller.removeBreak(selected.weekday, entry.key),
                        icon: const Icon(Icons.delete_outline),
                      ),
                    ),
                  ),
                  const Divider(),
                  const Text('Closures for selected date:'),
                  ...closures.map(
                    (closure) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(_closureLabel(context, closure.range)),
                      subtitle: closure.reason.isEmpty ? null : Text(closure.reason),
                      trailing: IconButton(
                        onPressed: () => controller.removeTemporaryClosure(closure.id),
                        icon: const Icon(Icons.delete_outline),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Appointments on ${DateFormat('EEE, dd MMM yyyy').format(selected)}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  if (events.isEmpty) const Text('No appointments for the selected day.'),
                  ...events.map(
                    (event) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.event_note_outlined),
                      title: Text(event.title),
                      subtitle: Text(
                        '${format.format(event.startAt)} - ${format.format(event.endAt)}\n'
                        '${event.clientName}',
                      ),
                      isThreeLine: true,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    });
  }

  Future<void> _pickAndUpdateHours(
    BuildContext context, {
    required int weekday,
    required TimeOfDay currentStart,
    required TimeOfDay currentEnd,
  }) async {
    final start = await showTimePicker(
      context: context,
      initialTime: currentStart,
    );

    if (start == null || !context.mounted) {
      return;
    }

    final end = await showTimePicker(
      context: context,
      initialTime: currentEnd,
    );

    if (end == null) {
      return;
    }

    controller.updateDayHours(weekday, start: start, end: end);
  }

  Future<void> _addBreak(BuildContext context, int weekday) async {
    final start = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 12, minute: 0),
    );

    if (start == null || !context.mounted) {
      return;
    }

    final end = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 13, minute: 0),
    );

    if (end == null) {
      return;
    }

    final error = controller.addBreak(weekday, TimeRange(start: start, end: end));
    if (error != null && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    }
  }

  Future<void> _addClosure(BuildContext context, DateTime date) async {
    final mode = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.event_busy),
                title: const Text('Full day closure'),
                subtitle: const Text('Disable bookings for the whole day.'),
                onTap: () => Navigator.of(context).pop('full_day'),
              ),
              ListTile(
                leading: const Icon(Icons.schedule),
                title: const Text('By hours'),
                subtitle: const Text('Disable bookings for a selected time range.'),
                onTap: () => Navigator.of(context).pop('by_hours'),
              ),
            ],
          ),
        );
      },
    );

    if (mode == null || !context.mounted) {
      return;
    }

    if (mode == 'full_day') {
      final error = controller.addTemporaryClosure(
        date,
        TimeRange(
          start: const TimeOfDay(hour: 0, minute: 0),
          end: const TimeOfDay(hour: 23, minute: 59),
        ),
        reason: 'Full day closure',
      );

      if (error != null && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      }

      return;
    }

    final start = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 9, minute: 0),
    );

    if (start == null || !context.mounted) {
      return;
    }

    final end = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 10, minute: 0),
    );

    if (end == null) {
      return;
    }

    final error = controller.addTemporaryClosure(
      date,
      TimeRange(start: start, end: end),
      reason: 'Temporary closure',
    );

    if (error != null && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    }
  }

  Future<void> _showDayAppointments(BuildContext context, DateTime date) async {
    final dayAppointments = controller.appointmentsForDate(date);
    final dayLabel = DateFormat('EEE, dd MMM yyyy').format(date);
    final timeFormat = DateFormat('hh:mm a');

    if (!context.mounted) {
      return;
    }

    if (dayAppointments.isEmpty) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Appointments on $dayLabel',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 12),
                if (dayAppointments.isEmpty) const Text('No appointments for this day.'),
                if (dayAppointments.isNotEmpty)
                  ...dayAppointments.map(
                    (item) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.event_note_outlined),
                      title: Text(item.title),
                      subtitle: Text(
                        '${timeFormat.format(item.startAt)} - ${timeFormat.format(item.endAt)}\n${item.clientName}',
                      ),
                      isThreeLine: true,
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  String _fmt(BuildContext context, TimeOfDay value) {
    return MaterialLocalizations.of(context).formatTimeOfDay(value);
  }

  String _closureLabel(BuildContext context, TimeRange range) {
    if (_isFullDayRange(range)) {
      return 'Full day closure';
    }

    return '${_fmt(context, range.start)} - ${_fmt(context, range.end)}';
  }

  bool _isFullDayRange(TimeRange range) {
    final startsAtMidnight = range.start.hour == 0 && range.start.minute == 0;
    final endsNearMidnight = range.end.hour == 23 && range.end.minute == 59;
    return startsAtMidnight && endsNearMidnight;
  }
}
