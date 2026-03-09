# AVIDMOCK — Local Development Setup (VS Code + PHP)

## Prerequisites
- PHP 8.1+ installed (`php -v` to check)
- MySQL/MariaDB (or use the live SiteGround DB for testing)
- VS Code with PHP extensions

## Quick Start

### 1. Clone/Unzip this project
```bash
unzip avidmock-full-project.zip
cd avidmock-project
```

### 2. Configure local environment
Copy the environment template and edit:
```bash
cp .env.example .env
# Edit .env with your database credentials
```

### 3. Start the student panel
```bash
cd student-panel
php -S localhost:8080 -t .
# Open http://localhost:8080
```

### 4. Start the admin panel (separate terminal)
```bash
cd admin-panel
php -S localhost:8081 -t .
# Open http://localhost:8081
```

### 5. VS Code Workspace
Open the `.code-workspace` file in VS Code:
```bash
code avidmock.code-workspace
```

## Project Structure

```
avidmock-project/
├── student-panel/          → my.sat.avidmock.com
│   ├── config/config.php   → Database + API keys
│   ├── lib/                → 27 PHP classes (core logic)
│   ├── api/                → REST endpoints
│   ├── ai-tutor/           → Claude AI integration (SSE streaming)
│   ├── learn/              → Lessons, quiz, results, review
│   ├── practice-tests/     → Full SAT practice test system
│   ├── schedule/           → Study schedule generator
│   ├── sessions/           → Live tutoring sessions
│   ├── achievements/       → Badge/achievement pages
│   ├── profile/            → Student profile + settings
│   └── includes/           → Reusable components (score-badge)
│
├── admin-panel/            → admin.sat.avidmock.com
│   ├── config/config.php   → Database config
│   ├── lib/                → Shared PHP classes
│   ├── api/                → Admin REST endpoints
│   ├── auth/               → Login/logout/guard
│   ├── analytics/          → Analytics dashboard pages
│   ├── quizzes/            → Quiz CRUD + builder
│   ├── practice-tests/     → Practice test management
│   ├── students/           → Student management
│   ├── sessions/           → Session management
│   └── includes/           → Sidebar, head, CSS
│
├── .env.example            → Environment variables template
├── avidmock.code-workspace → VS Code workspace file
├── LOCAL_DEV_SETUP.md      → This file
└── DEPLOYMENT.md           → SiteGround deployment guide
```

## Connecting to SiteGround Database Remotely

For local testing with live data:
1. Go to SiteGround → Site Tools → MySQL → Remote Access
2. Add your IP address
3. Update .env with remote host (your SiteGround server IP)
4. Works exactly like production

## Key URLs (Local)
- Student Dashboard: http://localhost:8080/
- AI Tutor: http://localhost:8080/ai-tutor/
- Practice Tests: http://localhost:8080/practice-tests/
- Learn/Quiz: http://localhost:8080/learn/quiz.php?lesson=function-notation-and-interpretation
- Admin Dashboard: http://localhost:8081/
- Admin Quizzes: http://localhost:8081/quizzes/

## Environment Variables
The .env file controls:
- DB_HOST, DB_NAME, DB_USER, DB_PASS (database)
- ANTHROPIC_API_KEY (AI tutor - Claude)
- GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET (OAuth)
- STRIPE keys (payments)
- SMTP credentials (email)
