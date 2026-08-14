# infinity_metrics
Goal: Build a lightweight, self-hosted multi-site product analytics platform with WordPress as the first dashboard/collector implementation.  Where are visitors coming from, what are they doing, where are they dropping off, and what actions convert?  It should collect events from multiple websites/apps into one installation.
infinity_metrics/
│
├── plugin/
│   └── infinity_metrics/
│       ├── infinity_metrics.php
│       │
│       ├── includes/
│       │   ├── class-database.php
│       │   ├── class-event-api.php
│       │   ├── class-events.php
│       │   ├── class-sources.php
│       │   ├── class-metrics.php
│       │   └── class-privacy.php
│       │
│       ├── admin/
│       │   ├── class-dashboard.php
│       │   └── views/
│       │
│       └── assets/
│           ├── tracker.js
│           ├── admin.js
│           └── admin.css
│
├── sdk/
│   └── javascript/
│       └── infinity_metrics.js
│
├── tests/
│
├── docs/
│   ├── architecture.md
│   ├── event-schema.md
│   └── api.md
│
├── README.md
└── LICENSE


MILESTONE 1
Bootstrap standalone WordPress plugin and database migrations.
MILESTONE 2
Implement Sources CRUD and API keys.
MILESTONE 3
Define event schema and create REST event collector.
MILESTONE 4
Create standalone JS tracker.
MILESTONE 5
Implement raw Event Explorer.
MILESTONE 6
Implement sessions and metric aggregation.
MILESTONE 7
Build dashboard v0.1.
MILESTONE 8
Implement configurable conversions.
MILESTONE 9
Implement funnels.
MILESTONE 10
Integrate elliottelford.com.
MILESTONE 11
Integrate Parker's Physics.
MILESTONE 12
Create Infinity-theme integration hooks.
