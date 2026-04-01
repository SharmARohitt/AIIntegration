# AI Integration for Zabbix

Zabbix frontend module that adds AI-powered analysis and quick actions across monitoring workflows. Developed by Rohit Sharma.

<img width="633" height="405" alt="image" src="https://github.com/user-attachments/assets/7316f3d9-d092-40cd-a819-0f06245148b4" />
<img width="611" height="383" alt="image" src="https://github.com/user-attachments/assets/0451a658-6ac2-4c1a-acf1-9332a194ab91" />
<img width="787" height="561" alt="image" src="https://github.com/user-attachments/assets/d072079f-e0b1-4a63-90dd-ce1443648372" />


**Base platform:** Zabbix 7.0.x (official frontend module structure).

## Features

- **Problems** – Sparkle action on each row to run AI analysis with enriched context (event, host, trigger, recurrence, acknowledges, metrics). Context (JSON) visible only to Super Admin.
- **Latest data** – Per-item sparkle to detect deviations and anomalies from history/trends (statistics, z-score, patterns). Super Admin can view/edit context.
- **Triggers** – Quick-fill form from a natural-language request (e.g. “CPU &gt; 80% for 5 min in business hours”). Host name resolved via API; time-based conditions supported.
- **Hosts** – Sparkle in the host form header to open a host health view: active problems (count by severity, CScreenProblem-style), top incidents (30d), metrics summary, anomalies, triggers status, health score. Analyze with AI from that context.

Provider (OpenAI, Anthropic, Gemini, Custom) is selected in the modal header. Configuration is stored in a JSON file under the module (no DB schema changes). Menu and settings are restricted to Super Admin (CWebUser); quick actions are driven by checkboxes in Administration → AI Integration.

## Requirements

- Zabbix 7.0.x
- PHP with JSON and cURL
- Writable directory: `modules/AIIntegration/config/` (for `aiintegration_config.json`)
- At least one configured AI provider with a valid API key

## Installation

1. Clone or copy the `AIIntegration` folder into the Zabbix frontend modules directory:
   ```bash
   cp -r AIIntegration /usr/share/zabbix/modules/
   ```
2. Ensure the config directory exists and is writable by the web server:
   ```bash
   mkdir -p /usr/share/zabbix/modules/AIIntegration/config
   chown www-data:www-data /usr/share/zabbix/modules/AIIntegration/config
   chmod 755 /usr/share/zabbix/modules/AIIntegration/config
   ```
3. In the Zabbix UI: **Administration → General → Modules**, enable **AI Integration**.
4. Go to **Administration → AI Integration**, enable at least one provider, set API endpoint and key, then enable the desired quick actions (Problems, Triggers, Latest data, Hosts) and save.

## Configuration

- **Default provider** – Used when no provider is chosen in the modal.
- **Quick actions** – Enable/disable the IA column and sparkle in Problems, Latest data, and the extra actions in Trigger and Host forms.
- **Providers** – OpenAI, Anthropic, Gemini, Custom. For each: enabled, endpoint, API key (masked in UI), model, temperature, max tokens. Custom allows optional JSON headers.

Credentials and options are stored in `modules/AIIntegration/config/aiintegration_config.json`. Restrict read/write access to the web server user only.

## Permissions

- **AI Integration** menu and settings page: Super Admin only (CWebUser).
- **Context (JSON)** in modals: shown only when the current user is Super Admin (via backend flag).
- Quick action visibility follows the same “quick actions” toggles and is available to users who can access the corresponding Zabbix screens (Problems, Latest data, host/trigger forms).

## File layout

```
AIIntegration/
├── manifest.json
├── Module.php
├── actions/
│   ├── AIIntegrationProviders.php
│   ├── AIIntegrationQuery.php
│   ├── AIIntegrationSettings.php
│   └── AIIntegrationSettingsSave.php
├── views/
│   └── aiintegration.settings.php
├── assets/
│   ├── css/
│   │   └── aiintegration.css
│   └── js/
│       ├── aiintegration-core.js
│       ├── aiintegration-init.js
│       ├── aiintegration-forms.js
│       ├── aiintegration-problems.js
│       └── aiintegration-latestdata.js
└── config/                  # created at runtime, must be writable
    └── aiintegration_config.json
```

## License

MIT. See [LICENSE](LICENSE).

## Author

Rohit Sharma
