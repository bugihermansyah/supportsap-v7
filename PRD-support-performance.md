# Product Requirements Document (PRD)

# Support Performance Center (Admin / Head Dashboard)

**Project** : Support Management System (SAP Support)
**Module** : Support Performance Center
**Version** : 1.0
**Target** : FilamentPHP v5
**Author** : Bugi Hermansyah

---

# 1. Background

Saat ini setiap engineer memiliki halaman profile yang hanya dapat melihat performanya sendiri.

Admin, Project Manager, Head Support, maupun Supervisor membutuhkan halaman khusus untuk:

* Memantau seluruh engineer dalam satu halaman.
* Membandingkan performa masing-masing engineer.
* Melihat KPI harian.
* Melihat progress outstanding.
* Memantau aktivitas.
* Mengetahui engineer yang overload maupun idle.
* Masuk ke halaman detail engineer hanya dengan satu klik.

Halaman ini menjadi **Control Center** seluruh tim support.

---

# 2. Objectives

Menyediakan dashboard monitoring engineer secara real-time sehingga Head Support dapat:

* Monitoring produktivitas engineer
* Monitoring SLA
* Monitoring KPI
* Monitoring aktivitas
* Monitoring lokasi kerja
* Monitoring outstanding
* Mengambil keputusan distribusi pekerjaan

---

# 3. User Role

| Role             | Permission      |
| ---------------- | --------------- |
| Admin            | Full Access     |
| Head Support     | Full Monitoring |
| Project Manager  | Monitoring Team |
| Supervisor       | Monitoring Team |
| Support Engineer | No Access       |

---

# 4. Navigation

```
Dashboard

Support Performance
    ├── Overview
    ├── Engineers
    ├── Ranking
    ├── Map
    └── Detail Engineer
```

---

# 5. Page Layout

```
+---------------------------------------------------------+
| Filter Bar                                              |
+---------------------------------------------------------+

+---------------------------------------------------------+
| Team Summary                                            |
+---------------------------------------------------------+

+---------------------------------------------------------+
| Engineer Cards                                          |
+---------------------------------------------------------+

+---------------------------------------------------------+
| Performance Table                                       |
+---------------------------------------------------------+

+---------------------------------------------------------+
| Charts                                                  |
+---------------------------------------------------------+

+---------------------------------------------------------+
| Map                                                     |
+---------------------------------------------------------+
```

---

# 6. Filter

## Component

* Date Range
* Team
* Engineer
* Customer
* Area
* Status
* KPI Level
* Search

### Filter Example

```
Today

This Week

This Month

Last Month

Custom Date
```

---

# 7. Widget 1 — Team Summary

Menampilkan ringkasan seluruh engineer.

## Cards

```
Total Engineer

Online

Offline

Reporting Today

Outstanding

Borrow Request

Average KPI

Total Distance

Outside City

SLA Achievement
```

Contoh

```
+-------------+
Engineer
18
+-------------+

+-------------+
Reporting
425
+-------------+

+-------------+
Outstanding
96
+-------------+

+-------------+
Average KPI
101.5
+-------------+
```

---

# 8. Widget 2 — Engineer Cards

Grid seluruh engineer.

```
+--------------------------------+

Avatar

Bugi

Head Support

KPI
102.5

Reporting
22

Outstanding
3

Borrow
1

Distance
123 KM

Status

🟢 Online

Last Reporting

10 Minutes Ago

Progress KPI

██████████

View Detail

+--------------------------------+
```

Jumlah card otomatis responsive

Desktop

```
4 Column
```

Tablet

```
2 Column
```

Mobile

```
1 Column
```

---

# 9. Widget 3 — Performance Ranking

Ranking engineer berdasarkan KPI.

Columns

| Column        |
| ------------- |
| Rank          |
| Engineer      |
| KPI           |
| Reporting     |
| Outstanding   |
| Borrow        |
| Distance      |
| SLA           |
| Status        |
| Last Activity |
| Action        |

Action

```
View Profile

View Reporting

View Outstanding

Assign Job
```

Sortable

```
KPI

Reporting

Outstanding

Distance

SLA
```

---

# 10. Widget 4 — KPI Chart

Chart

```
Reporting per Engineer

Outstanding per Engineer

Distance per Engineer

Borrow Request

Average KPI
```

Jenis chart

* Bar Chart
* Line Chart
* Doughnut

---

# 11. Widget 5 — Activity Timeline

Menampilkan aktivitas terbaru seluruh engineer.

Contoh

```
09:30

Bugi

Created Reporting

----------------

09:28

Andi

Completed Outstanding

----------------

09:20

Dedi

Borrow Unit

----------------

09:05

Rizky

Check In
```

---

# 12. Widget 6 — Engineer Map

Leaflet Map

Menampilkan posisi terakhir engineer.

Marker

```
Green

Online

Yellow

Working

Red

Offline

Blue

Outside City
```

Popup

```
Avatar

Engineer

Customer

Location

Distance Today

Last Reporting

View Detail
```

---

# 13. Widget 7 — Performance Distribution

Pie Chart

```
Excellent

Good

Normal

Need Improvement

Critical
```

Berdasarkan KPI.

---

# 14. Widget 8 — Outstanding Monitoring

Tabel

```
Engineer

Outstanding

Overdue

Due Today

Closed Today
```

Indicator

Hijau

```
0-5
```

Orange

```
6-10
```

Merah

```
>10
```

---

# 15. Widget 9 — Borrow Monitoring

```
Engineer

Borrow Unit

Borrow Laptop

Borrow Camera

Borrow Tool
```

---

# 16. Detail Engineer

Klik card engineer akan membuka halaman profile yang sudah ada.

```
Engineer Dashboard
```

Tidak perlu membuat ulang.

Reuse halaman existing.

---

# 17. KPI Formula

Contoh

```
KPI =
(
Reporting × 35%

+
Outstanding Closed × 30%

+
SLA × 20%

+
Attendance × 10%

+
Distance × 5%

)
```

Formula dapat berubah melalui Setting.

---

# 18. Color Rules

## KPI

| KPI     | Color  |
| ------- | ------ |
| >=110   | Green  |
| 100-109 | Blue   |
| 90-99   | Orange |
| <90     | Red    |

---

Outstanding

| Total | Color  |
| ----- | ------ |
| 0-5   | Green  |
| 6-10  | Orange |
| >10   | Red    |

---

Status

```
Online

Green

Offline

Gray

Working

Blue

Outside City

Orange
```

---

# 19. Database Requirement

Menggunakan tabel yang sudah ada:

```
users

teams

reportings

outstandings

borrow_requests

locations

customers

contracts

activities
```

Tidak ada tabel baru.

---

# 20. Performance

Semua widget wajib menggunakan cache.

```
Cache

30 detik
```

Heavy query menggunakan:

* Eager Loading
* Aggregate Query
* Subquery
* Index pada `user_id`, `team_id`, `created_at`, `status`

---

# 21. FilamentPHP v5 Structure

```
SupportPerformancePage
│
├── HeaderWidget
│
├── FilterWidget
│
├── TeamSummaryWidget
│
├── EngineerGridWidget
│
├── PerformanceRankingWidget
│
├── ActivityTimelineWidget
│
├── KPIChartWidget
│
├── OutstandingWidget
│
├── BorrowWidget
│
└── EngineerMapWidget
```

## 22. Future Enhancement (Roadmap)

Untuk versi berikutnya, beberapa fitur dapat ditambahkan agar halaman ini menjadi pusat operasional tim support:

* **Live Presence Monitoring**: status online/offline, check-in/check-out, dan durasi aktif secara real-time menggunakan WebSocket.
* **Workload Balancing**: indikator beban kerja engineer berdasarkan jumlah outstanding, jadwal, dan estimasi waktu pengerjaan untuk membantu distribusi tugas.
* **Achievement & Gamification**: badge seperti *Top Performer*, *Fast Responder*, *Most Reporting*, atau *Zero Outstanding* untuk meningkatkan motivasi tim.
* **SLA Heatmap**: visualisasi lokasi atau customer dengan tingkat pelanggaran SLA tertinggi.
* **AI Performance Insight**: ringkasan otomatis mengenai tren performa, engineer yang memerlukan perhatian, dan rekomendasi tindakan.
* **Export & Reporting**: ekspor ke Excel/PDF serta penjadwalan laporan mingguan/bulanan melalui email.
* **Notification Center**: peringatan jika KPI turun di bawah ambang batas, outstanding melewati SLA, atau engineer tidak mengirim reporting dalam periode tertentu.
* **Drill-down Analytics**: kemampuan membuka detail KPI hingga level reporting, outstanding, lokasi, atau customer hanya dengan satu klik.

Dokumen ini dirancang agar implementasinya selaras dengan arsitektur **FilamentPHP v5**, memanfaatkan halaman (`Page`) sebagai kontainer utama dan widget-widget yang modular sehingga mudah dipelihara, diuji, dan dikembangkan di masa mendatang.
