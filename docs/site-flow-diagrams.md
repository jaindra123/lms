# IIIDEM Moodle — site flow diagrams

High-level flows for the IIIDEM LMS (`theme_iiidem2`). Staging host example: `staginglms.eci.gov.in`.

View these diagrams in any Markdown preview that supports [Mermaid](https://mermaid.js.org/) (GitHub, GitLab, VS Code/Cursor Mermaid extension).

---

## 1. Site overview

```mermaid
flowchart TD
    A[Visitor opens site] --> B{Logged in?}

    B -->|No| C[Public homepage<br/>About / Contact / Courses list]
    C --> D{Action}
    D -->|Sign in| E[Login /login/index.php]
    D -->|Register| F[Self-registration /register]
    D -->|Contact| G[Contact form<br/>session success only]

    F --> F1[Form + OTP email verify]
    F1 --> F2[Create student account]
    F2 --> F3{Fee required?}
    F3 -->|Yes| F4[Payment gateway<br/>Razorpay / bank]
    F3 -->|No / waived by admin| E
    F4 -->|Paid| E

    E --> H{Credentials OK?}
    H -->|No| E
    H -->|Yes| I{MFA required?<br/>privileged / policy}
    I -->|Yes| J[MFA /admin/tool/mfa/auth.php]
    I -->|No| K[Session created]
    J --> K

    B -->|Yes| K
    K --> L[Theme dashboard<br/>/theme/iiidem2/dashboard/]

    L --> M{Role}
    M -->|Admin / manager| N[Admin dashboard]
    M -->|Teacher| O[Teacher dashboard]
    M -->|Student| P[Student dashboard]

    N --> Q[Course / reports / users]
    O --> Q
    P --> Q

    Q --> R[Course view]
    R --> S[Activities]
    R --> T[Participants / Grades / Reports<br/>capability-gated]

    S --> U{Access OK?}
    U -->|No| V[Denied / enrol page]
    U -->|Yes| W[Complete activity]

    W --> X[Progress / competency]
    X --> Y{Certificate eligible?}
    Y -->|Yes| Z[Certificate download]
    Y -->|No| P

    K --> AA[Idle session timeout]
    AA --> E
```

---

## 2. Student: registration → payment → course

```mermaid
flowchart TD
    A[Open /register/] --> B[Fill registration form]
    B --> C[Email OTP verify]
    C --> D{OTP valid?}
    D -->|No| C
    D -->|Yes| E[Create Moodle user<br/>student role only]
    E --> F[Fee enrolment pending]

    F --> G{Fee waived by admin?}
    G -->|Yes| H[Login]
    G -->|No| I[Login then pay]

    H --> J[Student dashboard]
    I --> K[Gateway: Razorpay / PNB / ICICI]
    K --> L[Complete payment]
    L --> M{Payment verified?}
    M -->|Fail| N[Stay unpaid / course locked]
    M -->|Success| O[Enrol unlocked]

    J --> P{Enrolled + paid?}
    P -->|No| I
    P -->|Yes| Q[Course view]
    O --> Q

    Q --> R[Activities]
    R --> S[Progress]
    S --> T{Certificate rules met?}
    T -->|Yes| U[Download certificate]
    T -->|No| J
```

### Student path (one line)

```mermaid
flowchart LR
    A[Register] --> B[OTP]
    B --> C[Account]
    C --> D[Pay<br/>unless waived]
    D --> E[Enrol unlock]
    E --> F[Course]
    F --> G[Certificate]
```

**Text:** Register → OTP → account → pay (unless waived) → enrol unlock → course → certificate.

---

## 3. Teacher: live class → attendance

```mermaid
flowchart TD
    A[Teacher login + MFA if required] --> B[Teacher dashboard]
    B --> C[Open course]
    C --> D[Schedule live class<br/>local_iiidem_coursecalendar]

    D --> E[Save session<br/>Webex / URL + time]
    E --> F[Students notified]
    E --> G[Optional Google Calendar sync]

    F --> H[At session time]
    H --> I[Student clicks Join]
    I --> J[Open Webex meeting]

    J --> K[Meeting ends]
    K --> L[Attendance sync<br/>local_iiidem_webexattendance]
    L --> M{Webex OAuth OK?}
    M -->|No| N[Admin connects Webex OAuth]
    N --> L
    M -->|Yes| O[Pull attendees from Webex API]
    O --> P[Match enrolled students]
    P --> Q[Store present / absent]

    Q --> R[Teacher attendance reports]
    Q --> S[Student own attendance on dashboard]
```

### Teacher path (one line)

```mermaid
flowchart LR
    A[Schedule live class] --> B[Students join Webex]
    B --> C[Sync attendance]
    C --> D[Teacher / student reports]
```

**Text:** Schedule live class → students join Webex → sync attendance → teacher/student reports.

---

## 4. Ops: daily backup (staging)

Not a user journey — host cron.

```mermaid
flowchart LR
    Cron[Cron 02:15 daily] --> Bak[scripts/daily-backup.sh]
    Bak --> DB[(db-lms_stage.sql.gz)]
    Bak --> Code[project-code.zip]
    Bak --> Data[moodledata.zip]
```

Paths (staging example):

| Item | Path |
|------|------|
| Code | `/var/www/html/lms_stage` |
| Dataroot | `/var/www/html/moodledata_stage` |
| Backup output | `/var/backups/iiidem/` |
| Cron env | `/etc/iiidem/backup.env` |

See [daily-backup-cron.md](daily-backup-cron.md).

---

## Related code areas

| Flow | Main areas |
|------|------------|
| Register / OTP | `register/`, `theme/iiidem2` registration helpers |
| Payment | `enrol/fee`, `payment/gateway/razorpay`, `pnb`, `icici` |
| Dashboards | `theme/iiidem2/dashboard/` |
| Live class | `local/iiidem_coursecalendar` |
| Webex attendance | `local/iiidem_webexattendance` |
