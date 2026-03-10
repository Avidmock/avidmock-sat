-- ═══════════════════════════════════════════════════════════════════
--  AVIDMOCK SAT · Database Schema v2
--  All new tables for billion-dollar platform features
--  Run after the base schema (dbw37mqkzieajt.sql)
-- ═══════════════════════════════════════════════════════════════════

-- ── ADAPTIVE LEARNING ENGINE ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS adaptive_student_state (
    user_id            INT          NOT NULL,
    domain             VARCHAR(30)  NOT NULL DEFAULT 'overall',
    ability_estimate   FLOAT        NOT NULL DEFAULT 0.0,
    questions_answered INT          NOT NULL DEFAULT 0,
    last_updated       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, domain),
    INDEX idx_ability (domain, ability_estimate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adaptive_question_params (
    question_id         INT    NOT NULL PRIMARY KEY,
    difficulty_estimate FLOAT  NOT NULL DEFAULT 0.0,
    discrimination      FLOAT  NOT NULL DEFAULT 1.0,
    times_shown         INT    NOT NULL DEFAULT 0,
    times_correct       INT    NOT NULL DEFAULT 0,
    last_calibrated     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_diff (difficulty_estimate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── WEAKNESS ANALYZER ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS weakness_analyses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    analysis_json   LONGTEXT     NOT NULL,
    ai_analysis     TEXT         DEFAULT NULL,
    weak_skills     JSON         DEFAULT NULL,
    strong_skills   JSON         DEFAULT NULL,
    predicted_impact INT         DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PARENT PROGRESS REPORTS ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS parent_report_settings (
    user_id             INT          NOT NULL PRIMARY KEY,
    parent_email_1      VARCHAR(255) DEFAULT NULL,
    parent_email_2      VARCHAR(255) DEFAULT NULL,
    frequency           ENUM('weekly','biweekly','monthly') DEFAULT 'weekly',
    include_scores      TINYINT(1)   DEFAULT 1,
    include_streaks     TINYINT(1)   DEFAULT 1,
    include_ai_usage    TINYINT(1)   DEFAULT 1,
    include_achievements TINYINT(1)  DEFAULT 1,
    last_sent_at        TIMESTAMP    NULL DEFAULT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parent_report_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    expires_at  TIMESTAMP    NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parent_report_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    sent_to     VARCHAR(255) NOT NULL,
    status      ENUM('sent','failed','bounced') DEFAULT 'sent',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SMART NOTEBOOK ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notebook_entries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    type            ENUM('scan','text','bookmark') DEFAULT 'text',
    source_image    LONGTEXT     DEFAULT NULL,
    recognized_text TEXT         DEFAULT NULL,
    solution_json   TEXT         DEFAULT NULL,
    sat_domain      VARCHAR(50)  DEFAULT '',
    sat_skill       VARCHAR(80)  DEFAULT '',
    difficulty      VARCHAR(20)  DEFAULT 'medium',
    tags            JSON         DEFAULT NULL,
    is_starred      TINYINT(1)   DEFAULT 0,
    folder          VARCHAR(50)  DEFAULT 'General',
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_folder (user_id, folder),
    INDEX idx_user_domain (user_id, sat_domain),
    INDEX idx_user_date (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MATH VISION (Chrome Extension + Notebook) ─────────────────────

CREATE TABLE IF NOT EXISTS math_scans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    source          ENUM('extension','notebook','web') DEFAULT 'web',
    input_type      ENUM('image','text') DEFAULT 'text',
    problem_text    TEXT         DEFAULT NULL,
    solution_json   TEXT         DEFAULT NULL,
    sat_domain      VARCHAR(50)  DEFAULT '',
    sat_skill       VARCHAR(80)  DEFAULT '',
    difficulty      VARCHAR(20)  DEFAULT '',
    xp_earned       INT          DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at DESC),
    INDEX idx_domain (sat_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STUDY ROOMS (Multiplayer Quiz Battles) ────────────────────────

CREATE TABLE IF NOT EXISTS study_rooms (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(6)   NOT NULL UNIQUE,
    host_id             INT          NOT NULL,
    topic               VARCHAR(100) DEFAULT 'SAT Math',
    question_count      INT          DEFAULT 10,
    time_per_question   INT          DEFAULT 30,
    max_players         INT          DEFAULT 10,
    status              ENUM('waiting','playing','finished','expired') DEFAULT 'waiting',
    current_question    INT          DEFAULT 0,
    question_ids        JSON         DEFAULT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    started_at          TIMESTAMP    NULL DEFAULT NULL,
    ended_at            TIMESTAMP    NULL DEFAULT NULL,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_host (host_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS study_room_players (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    room_id     INT          NOT NULL,
    user_id     INT          NOT NULL,
    score       INT          DEFAULT 0,
    correct     INT          DEFAULT 0,
    rank        INT          DEFAULT NULL,
    joined_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_room_user (room_id, user_id),
    INDEX idx_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS study_room_answers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    room_id         INT          NOT NULL,
    user_id         INT          NOT NULL,
    question_id     INT          NOT NULL,
    answer          VARCHAR(5)   DEFAULT NULL,
    is_correct      TINYINT(1)   DEFAULT 0,
    time_ms         INT          DEFAULT 0,
    points_earned   INT          DEFAULT 0,
    answered_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_q (room_id, question_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SOCIAL SHARING & REFERRALS ────────────────────────────────────

CREATE TABLE IF NOT EXISTS share_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    type        VARCHAR(30)  NOT NULL DEFAULT 'scorecard',
    token       VARCHAR(64)  NOT NULL UNIQUE,
    data_json   TEXT         DEFAULT NULL,
    views       INT          DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP    NULL DEFAULT NULL,
    INDEX idx_token (token),
    INDEX idx_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_codes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL UNIQUE,
    code        VARCHAR(10)  NOT NULL UNIQUE,
    uses        INT          DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id     INT          NOT NULL,
    referred_id     INT          NOT NULL,
    reward_type     VARCHAR(30)  DEFAULT 'xp',
    reward_given    TINYINT(1)   DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── REVENUE & SUBSCRIPTIONS ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS subscription_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    event_type      ENUM('subscribe','upgrade','downgrade','cancel','reactivate','payment','refund') NOT NULL,
    plan            VARCHAR(20)  DEFAULT NULL,
    amount          INT          DEFAULT 0 COMMENT 'in cents',
    stripe_event_id VARCHAR(100) DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at),
    INDEX idx_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS signup_attribution (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL UNIQUE,
    channel         VARCHAR(30)  DEFAULT 'direct',
    referrer_url    TEXT         DEFAULT NULL,
    utm_source      VARCHAR(100) DEFAULT NULL,
    utm_medium      VARCHAR(100) DEFAULT NULL,
    utm_campaign    VARCHAR(100) DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel),
    INDEX idx_utm (utm_source, utm_medium)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── AI QUESTION GENERATION LOG ────────────────────────────────────

CREATE TABLE IF NOT EXISTS ai_generation_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT          DEFAULT NULL,
    domain          VARCHAR(50)  DEFAULT NULL,
    skill           VARCHAR(80)  DEFAULT NULL,
    difficulty      VARCHAR(20)  DEFAULT NULL,
    question_count  INT          DEFAULT 0,
    quiz_id         INT          DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_date (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── R&W SUBMISSIONS ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS rw_submissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    prompt_id       INT          DEFAULT NULL,
    response_text   TEXT         DEFAULT NULL,
    score_json      TEXT         DEFAULT NULL,
    overall_score   TINYINT      DEFAULT 0,
    feedback_json   TEXT         DEFAULT NULL,
    word_count      INT          DEFAULT 0,
    time_spent      INT          DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SCORE PREDICTIONS HISTORY ─────────────────────────────────────

CREATE TABLE IF NOT EXISTS score_predictions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT          NOT NULL,
    predicted_score     INT          NOT NULL,
    confidence_low      INT          DEFAULT NULL,
    confidence_high     INT          DEFAULT NULL,
    math_score          INT          DEFAULT NULL,
    rw_score            INT          DEFAULT NULL,
    probability_target  FLOAT        DEFAULT NULL,
    model_version       VARCHAR(10)  DEFAULT 'v2',
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════
-- Done. 19 new tables for the full Avidmock platform.
-- ═══════════════════════════════════════════════════════════════════
