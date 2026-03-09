# AVIDMOCK SAT — Claude Code Project Setup Instructions
## Give this file + the two zip files + header.php + index.php to Claude Code

---

## WHAT THIS PROJECT IS

Avidmock is an AI-powered SAT Math exam prep platform. It has:
- **sat.avidmock.com** — Public marketing site (header.php + index.php)
- **my.sat.avidmock.com** — Student dashboard (the `my_sat_avidmock_com.zip`)
- **admin.sat.avidmock.com** — Admin dashboard (the `admin_sat_avidmock_com.zip`)

All hosted on SiteGround (PHP 8.2 + MySQL). The SAT section is entirely in English.
The student + admin panels share the same MySQL database: `dbw37mqkzieajt`

---

## FILES TO UPLOAD TO CLAUDE CODE

1. **This file** (CLAUDE_CODE_INSTRUCTIONS.md)
2. **my_sat_avidmock_com.zip** — Student panel codebase (68 PHP files, 27 lib classes)
3. **admin_sat_avidmock_com.zip** — Admin panel codebase (54 PHP files)
4. **header.php** — Public site header (uploaded as `header__12_.php`)
5. **index.php** — Public site homepage (uploaded as `index__38_.php`)

---

## WHAT TO DO

### Step 1: Create VS Code Project Structure
```
avidmock-sat/
├── public-site/              → sat.avidmock.com
│   ├── index.php             → Homepage (3,946 lines)
│   └── header.php            → Floating pill header (675 lines)
│
├── student-panel/            → my.sat.avidmock.com
│   ├── config/config.php     → DB + API keys (use .env in dev)
│   ├── lib/                  → 27 PHP classes:
│   │   ├── Auth.php          → Authentication (v5.3, SEO-friendly)
│   │   ├── Database.php      → PDO wrapper (singleton)
│   │   ├── AITutor.php       → Claude SSE streaming tutor
│   │   ├── AITutorHistory.php
│   │   ├── ScorePredictor.php → Weighted SAT score prediction
│   │   ├── Achievement.php   → 22 badges with XP rewards
│   │   ├── StudyStreak.php   → Daily streak with freeze mechanic
│   │   ├── Leaderboard.php   → Weekly + all-time XP rankings
│   │   ├── CategoryPerformance.php → 8 SAT domain tracking
│   │   ├── Schedule.php      → Auto-generated study plans
│   │   ├── Onboarding.php    → 4-step onboarding flow
│   │   ├── Quiz.php          → Quiz loader from sat_quizzes table
│   │   ├── Question.php      → Question loader, runtime column detection
│   │   ├── User.php, Lesson.php, Session.php, Mailer.php
│   │   ├── QuizAttempt.php, QuizProgress.php, QuestionStats.php
│   │   ├── StudentPerformance.php, WeeklyPerformance.php
│   │   ├── RateLimit.php, SEO.php, VideoProgress.php
│   │   ├── AnswerChecker.php, ExplanationFeedback.php
│   │   └── [missing: XPSystem.php - referenced but not provided]
│   │
│   ├── api/                  → REST endpoints
│   │   ├── check-answer.php  → POST: Validate quiz answer
│   │   ├── submit-quiz.php   → POST: Submit completed quiz
│   │   ├── get-dashboard.php → GET: Dashboard stats JSON
│   │   ├── get-leaderboard.php
│   │   ├── get-spaced-review.php
│   │   ├── save-progress.php, save-quiz-progress.php
│   │   ├── mark-complete.php, submit-review.php
│   │   ├── explanation-feedback.php
│   │   └── ai-tutor.php      → EMPTY (proxy to /ai-tutor/api.php)
│   │
│   ├── ai-tutor/             → AI Tutor (Claude integration)
│   │   ├── index.php         → Full chat UI (983 lines, KaTeX, SSE)
│   │   ├── api.php           → Claude SSE streaming endpoint
│   │   ├── history.php       → Conversation history
│   │   ├── strategy-advisor.php
│   │   ├── review-coach.php
│   │   └── widget.php        → Embeddable mini tutor
│   │
│   ├── learn/                → Lesson + quiz pages
│   │   ├── quiz.php          → EMPTY (needs implementation)
│   │   ├── results.php       → EMPTY (needs implementation)
│   │   ├── review.php        → EMPTY (needs implementation)
│   │   └── math/algebra/foundations/
│   │       ├── function-notation-and-interpretation.php
│   │       ├── graphing-linear-equations.php
│   │       └── identifying-linear-relationships.php
│   │
│   ├── practice-tests/       → Full SAT practice test system
│   │   ├── index.php, start.php, exam.php
│   │   ├── results.php, review.php, history.php
│   │
│   ├── schedule/             → Study schedule
│   │   ├── index.php, setup.php, edit.php
│   │   ├── generate.php, spaced-review.php
│   │
│   ├── sessions/             → Live tutoring sessions
│   │   ├── index.php, session.php, past.php
│   │
│   ├── achievements/index.php
│   │
│   ├── profile/              → BUG: files have .php.php extension
│   │   ├── index.php.php     → RENAME to index.php
│   │   └── settings.php.php  → RENAME to settings.php
│   │
│   └── includes/             → Reusable components
│       └── (empty - need score-badge.php)
│
├── admin-panel/              → admin.sat.avidmock.com
│   ├── config/config.php     → FILENAME: "config (6).php" → rename
│   ├── lib/                  → Shared classes (some have (N) suffixes)
│   ├── api/
│   │   ├── save-quiz.php     → Working
│   │   ├── upload-explanation-image.php → Working
│   │   ├── get-stats.php     → EMPTY
│   │   ├── get-activity.php  → EMPTY
│   │   ├── publish-quiz.php  → EMPTY
│   │   ├── delete-quiz.php   → EMPTY
│   │   └── student-search.php → EMPTY
│   │
│   ├── auth/                 → "login (12).php" etc → rename
│   ├── analytics/            → 4 pages (index, engagement, retention, scores)
│   ├── quizzes/              → 8 pages (CRUD + builder + preview)
│   ├── practice-tests/       → 4 pages
│   ├── students/             → 3 pages (index, student, export)
│   ├── sessions/             → 5 pages (ALL EMPTY)
│   ├── includes/             → sidebar, head, admin.css
│   └── assets/               → css/admin.css, js/admin-core.js (both empty)
│
├── database/
│   └── schema.sql            → CREATE TABLE statements (extract from code)
│
├── .env.example              → Environment variables
├── .gitignore
├── README.md
└── avidmock.code-workspace   → VS Code multi-root workspace

```

### Step 2: Known Bugs to Fix
1. `profile/index.php.php` → rename to `index.php`
2. `profile/settings.php.php` → rename to `settings.php`
3. `ai-tutor/api.php` line 175: hardcoded `'claude-sonnet-4-5'` → use `AI_TUTOR_MODEL` constant
4. Admin files have `(N)` suffixes: `index (37).php` → `index.php`, etc.
5. `StudentPerformance.php` uses global `$pdo` instead of `Database` class
6. 10 empty API/page files need implementations

### Step 3: Database Schema
The code references these MySQL tables (extract from lib classes):
- `users` (id, first_name, last_name, name, email, password_hash, role, email_verified, is_active, is_suspended, onboarding_complete, test_date, target_score, avatar_url, subscription_plan, current_streak, longest_streak, total_xp, current_level, league, last_login_at, created_at, updated_at)
- `student_profiles` (user_id, target_score, weekly_study_hours, test_date, onboarding_step, onboarding_complete)
- `sat_quizzes` (id, title, lesson_slug, status, time_limit, published_at, created_at, updated_at)
- `sat_quiz_questions` (id, quiz_id, position, type, stem, option_a..d, correct_answer, explanation, explanation_html, explanation_image, difficulty, points)
- `sat_quiz_attempts` (id, user_id, quiz_id, status, score, correct, incorrect, total, started_at, completed_at, time_spent)
- `quiz_attempt_answers` (id, attempt_id, question_id, user_answer, is_correct, time_spent)
- `practice_test_attempts` (id, user_id, test_id, status, total_score, math_score, rw_score, percentile, predicted_sat, time_taken, started_at, submitted_at)
- `study_streaks` (user_id, current_streak, longest_streak, last_active)
- `streak_freezes` (id, user_id, is_used, used_at)
- `user_xp` (user_id, xp, level)
- `xp_events` (id, user_id, xp_earned, event_type, created_at)
- `achievements` (id, slug, name, description, icon_url, badge_color, xp_reward, criteria_json, is_active, created_at)
- `user_achievements` (id, user_id, achievement_id, unlocked_at)
- `category_performance` (id, user_id, category, total_quizzes, correct_answers, avg_score, mastery_level)
- `ai_tutor_conversations` (id, user_id, title, subject, created_at, updated_at)
- `ai_tutor_messages` (id, conversation_id, user_id, role, content, created_at)
- `lessons` (id, title, slug, module_slug, order_index, is_published, duration_min)
- `video_progress` (id, user_id, lesson_id, is_complete, progress_pct)
- `schedule_tasks` (id, user_id, schedule_id, task_date, task_type, lesson_id, title, duration_min, sort_order, is_completed)
- `diagnostic_attempts` (user_id, ...)
- `rate_limits` (key, count, window_start)

### Step 4: Local Development
```bash
# Start student panel
cd student-panel
php -S localhost:8080

# Start admin panel (separate terminal)
cd admin-panel
php -S localhost:8081
```

For DB: either install MySQL locally and import schema.sql,
or enable SiteGround Remote MySQL (Site Tools → MySQL → Remote Access)
and point your local config to the live DB.

### Step 5: Design System
```
Student Panel Colors:
  --dk: #143230       (dark teal)
  --ac: #1fe290       (green accent)
  --ac2: #17c87a
  --tx: #1a1a2e       (text)
  --bg: #f7faf9       (background)
  Fonts: DM Sans + DM Mono

Admin Panel Colors:
  --ink: #0c1f1d      (dark bg)
  --ac: #1fe290       (green accent)
  --tx: #e8f3f1       (light text on dark)
  Fonts: DM Sans + Fraunces (serif) + DM Mono

Public Site Colors:
  --pr: #5b28a4       (purple primary)
  --tx: #18182a
  --bg: #ffffff
  Fonts: Instrument Sans + DM Sans
```

### Step 6: Key Technical Details
- PHP 8.2, MySQL 8.0, SiteGround shared hosting
- AI: Anthropic Claude API (claude-sonnet-4-5) with SSE streaming
- Auth: Session-based, Google OAuth, cookie on `.avidmock.com`
- Payments: Stripe (3 tiers: free, pro $14.99/mo, family $24.99/mo)
- Email: Mailgun SMTP
- Gamification: XP system, 4 leagues, 22 achievements, streaks with freeze
- Score prediction: Weighted average from practice test attempts
- Spaced repetition: SM-2 algorithm parameters in config

### Step 7: SiteGround Deployment
Upload `student-panel/` contents → my.sat.avidmock.com document root
Upload `admin-panel/` contents → admin.sat.avidmock.com document root
The config.php files have hardcoded DB credentials for production.
For local dev, override with .env variables.

---

## PRIORITY BUILD ORDER

1. Fix all bugs (filename issues, model mismatch)
2. Generate database/schema.sql from code analysis
3. Implement empty files (learn/quiz.php, learn/results.php, learn/review.php, 5 admin APIs, 5 admin session pages)
4. Build Score Badge reusable component
5. Create .env-based config for local dev
6. Set up VS Code workspace with both panels
