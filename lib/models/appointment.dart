enum AppointmentStatus { scheduled, cancelled }

class Appointment {
  Appointment({
    required this.id,
    required this.title,
    required this.clientName,
    required this.startAt,
    required this.endAt,
    required this.reminderMinutes,
    this.notes = '',
    this.status = AppointmentStatus.scheduled,
  });

  final String id;
  final String title;
  final String clientName;
  final String notes;
  final DateTime startAt;
  final DateTime endAt;
  final int reminderMinutes;
  final AppointmentStatus status;

  Appointment copyWith({
    String? id,
    String? title,
    String? clientName,
    String? notes,
    DateTime? startAt,
    DateTime? endAt,
    int? reminderMinutes,
    AppointmentStatus? status,
  }) {
    return Appointment(
      id: id ?? this.id,
      title: title ?? this.title,
      clientName: clientName ?? this.clientName,
      notes: notes ?? this.notes,
      startAt: startAt ?? this.startAt,
      endAt: endAt ?? this.endAt,
      reminderMinutes: reminderMinutes ?? this.reminderMinutes,
      status: status ?? this.status,
    );
  }
}
