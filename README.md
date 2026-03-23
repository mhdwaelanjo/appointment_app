# Smart Appointment Management System

A Flutter application for managing appointments, availability, and client bookings. The app supports scheduling, rescheduling, and cancelling appointments, configuring weekly working hours, recurring breaks, and temporary closures, as well as local push notification reminders and device calendar export.

---

## Features

- **Appointment Management** — Create, edit, reschedule, cancel, and delete appointments.
- **Availability Configuration** — Enable/disable weekdays and set working hours per day.
- **Breaks** — Define recurring weekly break windows during which no bookings can be made.
- **Temporary Closures** — Block specific date-and-time ranges (partial or full-day) on an ad-hoc basis.
- **Smart Slot Generation** — Available time slots are computed automatically by factoring in working hours, breaks, closures, and existing appointments.
- **Push Notifications** — Local reminders are scheduled when an appointment is created or updated and cancelled automatically on deletion or cancellation.
- **Device Calendar Export** — Export any appointment to the device's native calendar.
- **Interactive Calendar View** — Monthly calendar with event markers and day-level appointment detail.

---

## Project Structure

```
lib/
├── main.dart                          # App entry point
├── controllers/
│   └── appointment_controller.dart    # Business logic (GetX controller)
├── models/
│   ├── appointment.dart               # Appointment & AppointmentStatus
│   └── availability.dart             # DayAvailability, TimeRange, TemporaryClosure
├── pages/
│   ├── home_shell.dart                # Bottom navigation shell
│   ├── dashboard_page.dart            # Calendar view + day detail
│   ├── bookings_page.dart             # Full appointment list
│   └── add_edit_appointment_page.dart # Create / edit form
└── services/
    └── notification_service.dart      # flutter_local_notifications wrapper
```

---

## Tech Stack

| Package | Version | Purpose |
|---|---|---|
| [get](https://pub.dev/packages/get) | ^4.7.2 | State management & navigation |
| [table_calendar](https://pub.dev/packages/table_calendar) | ^3.2.0 | Interactive monthly calendar |
| [flutter_local_notifications](https://pub.dev/packages/flutter_local_notifications) | ^19.4.2 | Scheduled local push notifications |
| [timezone](https://pub.dev/packages/timezone) | ^0.10.1 | Timezone-aware notification scheduling |
| [add_2_calendar](https://pub.dev/packages/add_2_calendar) | ^3.0.1 | Device calendar export |
| [intl](https://pub.dev/packages/intl) | ^0.20.2 | Date & time formatting |

---

## Getting Started

### Prerequisites

- [Flutter SDK](https://docs.flutter.dev/get-started/install) — Dart SDK `^3.10.8`
- Android Studio / Xcode for device/emulator targets

### Run the App

```bash
flutter pub get
flutter run
```

### Run Tests

```bash
flutter test
```

---

## API Integration

This app is designed to connect to a REST back-end. A full API integration guide — including all endpoints, request/response JSON examples, business rules, and error codes — is available in [API_INTEGRATION.md](API_INTEGRATION.md).

---

## Appointment Status Values

| Value | Description |
|---|---|
| `scheduled` | Active appointment |
| `cancelled` | Cancelled; excluded from conflict checks |

## Reminder Options

Reminders can be set to **10**, **15**, **30**, or **60** minutes before the appointment start time.

## Session Duration Options

The default session duration (used for slot generation) can be configured to **30** or **60** minutes.
