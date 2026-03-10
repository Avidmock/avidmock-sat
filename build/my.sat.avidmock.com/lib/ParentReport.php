<?php
/**
 * ===================================================================
 *  AVIDMOCK . ParentReport
 *  Weekly parent progress reports — the Family tier differentiator.
 *
 *  Database tables (define via migration, not auto-created):
 *
 *  CREATE TABLE parent_report_settings (
 *      id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      user_id         INT UNSIGNED NOT NULL,
 *      parent_email_1  VARCHAR(255) DEFAULT NULL,
 *      parent_email_2  VARCHAR(255) DEFAULT NULL,
 *      frequency       ENUM('weekly','biweekly','monthly') DEFAULT 'weekly',
 *      include_scores       TINYINT(1) DEFAULT 1,
 *      include_streaks      TINYINT(1) DEFAULT 1,
 *      include_ai_usage     TINYINT(1) DEFAULT 1,
 *      include_achievements TINYINT(1) DEFAULT 1,
 *      last_sent_at    DATETIME DEFAULT NULL,
 *      created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY (user_id)
 *  );
 *
 *  CREATE TABLE parent_report_tokens (
 *      id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      user_id    INT UNSIGNED NOT NULL,
 *      token      VARCHAR(128) NOT NULL,
 *      expires_at DATETIME NOT NULL,
 *      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY (token),
 *      KEY (user_id)
 *  );
 *
 *  CREATE TABLE parent_report_log (
 *      id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      user_id    INT UNSIGNED NOT NULL,
 *      sent_to    VARCHAR(255) NOT NULL,
 *      status     ENUM('sent','failed') DEFAULT 'sent',
 *      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      KEY (user_id),
 *      KEY (created_at)
 *  );
 * ===================================================================
 */

class ParentReport
{
    // -----------------------------------------------------------------
    //  Generate all weekly metrics for a student
    // -----------------------------------------------------------------
    public static function generateWeeklyReport(int $userId): array
    {
        $weekStart     = date('Y-m-d', strtotime('monday this week'));
        $priorStart    = date('Y-m-d', strtotime('monday last week'));
        $priorEnd      = date('Y-m-d', strtotime('sunday last week'));
        $now           = date('Y-m-d H:i:s');

        $user = Database::fetch("SELECT id, name, first_name, last_name FROM users WHERE id = ?", [$userId]);
        if (!$user) return [];

        $studentName = trim($user['name'] ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'Student';

        // -- Load include preferences (default: all on) --
        $settings = self::getSettings($userId);
        $inc = [
            'scores'       => (bool) ($settings['include_scores']       ?? 1),
            'streaks'      => (bool) ($settings['include_streaks']      ?? 1),
            'ai_usage'     => (bool) ($settings['include_ai_usage']     ?? 1),
            'achievements' => (bool) ($settings['include_achievements'] ?? 1),
        ];

        $data = [
            'user_id'      => $userId,
            'student_name' => $studentName,
            'week_start'   => $weekStart,
            'generated_at' => $now,
            'includes'     => $inc,
        ];

        // ── Quizzes this week ──
        if ($inc['scores']) {
            $quizStats = Database::fetch(
                "SELECT COUNT(*) AS total,
                        ROUND(AVG(score), 1) AS avg_score,
                        MAX(score)           AS best_score,
                        SUM(time_spent)      AS total_time
                 FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed'
                   AND completed_at >= ?",
                [$userId, $weekStart]
            );

            $priorStats = Database::fetch(
                "SELECT ROUND(AVG(score), 1) AS avg_score
                 FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed'
                   AND completed_at >= ? AND completed_at < ?",
                [$userId, $priorStart, $weekStart]
            );

            $currentAvg = (float) ($quizStats['avg_score'] ?? 0);
            $priorAvg   = (float) ($priorStats['avg_score'] ?? 0);
            $scoreDelta = $priorAvg > 0 ? round($currentAvg - $priorAvg, 1) : null;

            $data['quizzes'] = [
                'completed'  => (int) ($quizStats['total'] ?? 0),
                'avg_score'  => $currentAvg,
                'best_score' => (float) ($quizStats['best_score'] ?? 0),
                'total_time' => (int) ($quizStats['total_time'] ?? 0),
                'prior_avg'  => $priorAvg,
                'score_delta'=> $scoreDelta,
            ];

            // -- Weekly score trend (last 6 weeks) --
            $data['score_trend'] = Database::fetchAll(
                "SELECT DATE_FORMAT(MIN(completed_at), '%b %d') AS week_label,
                        ROUND(AVG(score), 1) AS avg_score,
                        COUNT(*) AS quiz_count
                 FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed'
                   AND completed_at >= DATE_SUB(?, INTERVAL 6 WEEK)
                 GROUP BY YEARWEEK(completed_at, 1)
                 ORDER BY MIN(completed_at) ASC",
                [$userId, $weekStart]
            );
        }

        // ── Study streak ──
        if ($inc['streaks']) {
            $streak = Database::fetch(
                "SELECT current_streak, longest_streak, last_active
                 FROM study_streaks WHERE user_id = ?",
                [$userId]
            );

            $activityDays = Database::fetchAll(
                "SELECT DATE(completed_at) AS day, COUNT(*) AS sessions
                 FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed'
                   AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 GROUP BY DATE(completed_at)
                 ORDER BY day ASC",
                [$userId]
            );

            $data['streak'] = [
                'current'        => (int) ($streak['current_streak'] ?? 0),
                'longest'        => (int) ($streak['longest_streak'] ?? 0),
                'last_active'    => $streak['last_active'] ?? null,
                'activity_7days' => $activityDays,
            ];

            $totalStudyTime = (int) Database::fetchColumn(
                "SELECT COALESCE(SUM(time_spent), 0) FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed' AND completed_at >= ?",
                [$userId, $weekStart]
            );
            $data['streak']['total_study_time'] = $totalStudyTime;
        }

        // ── Skills — improved and needing attention ──
        if ($inc['scores']) {
            $data['skills_strong'] = CategoryPerformance::getStrongest($userId, 3);
            $data['skills_weak']   = CategoryPerformance::getWeakest($userId, 3);
        }

        // ── Practice test scores & SAT prediction ──
        if ($inc['scores']) {
            $practiceTests = Database::fetchAll(
                "SELECT total_score, math_score, rw_score, submitted_at
                 FROM practice_test_attempts
                 WHERE user_id = ? AND status = 'submitted'
                   AND submitted_at >= ?
                 ORDER BY submitted_at DESC LIMIT 5",
                [$userId, $weekStart]
            );
            $data['practice_tests'] = $practiceTests;

            $prediction = ScorePredictor::getLatest($userId);
            $data['sat_prediction'] = $prediction;
        }

        // ── AI tutor usage ──
        if ($inc['ai_usage']) {
            $aiStats = Database::fetch(
                "SELECT COUNT(DISTINCT id) AS sessions,
                        COUNT(DISTINCT topic) AS topics
                 FROM ai_tutor_conversations
                 WHERE user_id = ? AND created_at >= ?",
                [$userId, $weekStart]
            );

            $topTopics = Database::fetchAll(
                "SELECT topic, COUNT(*) AS cnt
                 FROM ai_tutor_conversations
                 WHERE user_id = ? AND created_at >= ? AND topic IS NOT NULL
                 GROUP BY topic ORDER BY cnt DESC LIMIT 3",
                [$userId, $weekStart]
            );

            $data['ai_tutor'] = [
                'sessions'   => (int) ($aiStats['sessions'] ?? 0),
                'topics'     => (int) ($aiStats['topics'] ?? 0),
                'top_topics' => $topTopics,
            ];
        }

        // ── Achievements unlocked this week ──
        if ($inc['achievements']) {
            try {
                $newBadges = Database::fetchAll(
                    "SELECT a.slug, a.name, a.badge_color, a.xp_reward, a.description
                     FROM user_achievements ua
                     JOIN achievements a ON a.id = ua.achievement_id
                     WHERE ua.user_id = ? AND ua.unlocked_at >= ?
                     ORDER BY ua.unlocked_at DESC",
                    [$userId, $weekStart]
                );
            } catch (\Throwable $e) {
                $newBadges = [];
            }
            $data['achievements'] = $newBadges;
        }

        // ── "Proud Parent Moment" — single best achievement this week ──
        $data['proud_moment'] = self::determineBestMoment($data);

        return $data;
    }

    // -----------------------------------------------------------------
    //  Determine the single best highlight of the week
    // -----------------------------------------------------------------
    private static function determineBestMoment(array $data): array
    {
        $candidates = [];

        // New achievement (highest XP reward wins)
        if (!empty($data['achievements'])) {
            $best = $data['achievements'][0];
            foreach ($data['achievements'] as $a) {
                if ((int) ($a['xp_reward'] ?? 0) > (int) ($best['xp_reward'] ?? 0)) $best = $a;
            }
            $candidates[] = [
                'type'  => 'achievement',
                'emoji' => "\xF0\x9F\x8F\x86", // trophy
                'title' => 'Unlocked: ' . ($best['name'] ?? 'Badge'),
                'detail'=> $best['description'] ?? '',
                'weight'=> 90,
            ];
        }

        // High quiz score
        if (!empty($data['quizzes']) && $data['quizzes']['best_score'] >= 90) {
            $candidates[] = [
                'type'   => 'score',
                'emoji'  => "\xE2\xAD\x90", // star
                'title'  => 'Scored ' . $data['quizzes']['best_score'] . '% on a quiz!',
                'detail' => 'Best quiz score this week.',
                'weight' => 80,
            ];
        }

        // Score improvement
        if (isset($data['quizzes']['score_delta']) && $data['quizzes']['score_delta'] > 0) {
            $candidates[] = [
                'type'   => 'improvement',
                'emoji'  => "\xF0\x9F\x93\x88", // chart up
                'title'  => 'Score up +' . $data['quizzes']['score_delta'] . '% vs last week',
                'detail' => 'Consistent improvement shows dedication.',
                'weight' => 70,
            ];
        }

        // Strong streak
        if (!empty($data['streak']) && $data['streak']['current'] >= 5) {
            $candidates[] = [
                'type'   => 'streak',
                'emoji'  => "\xF0\x9F\x94\xA5", // fire
                'title'  => $data['streak']['current'] . '-day study streak!',
                'detail' => 'Building powerful study habits.',
                'weight' => 60,
            ];
        }

        // Quizzes completed
        if (!empty($data['quizzes']) && $data['quizzes']['completed'] >= 5) {
            $candidates[] = [
                'type'   => 'volume',
                'emoji'  => "\xF0\x9F\x92\xAA", // muscle
                'title'  => $data['quizzes']['completed'] . ' quizzes completed this week',
                'detail' => 'Putting in the work!',
                'weight' => 50,
            ];
        }

        if (empty($candidates)) {
            return [
                'emoji'  => "\xF0\x9F\x8C\x9F", // glowing star
                'title'  => 'Keeping at it!',
                'detail' => 'Every study session brings your child closer to their goal.',
            ];
        }

        usort($candidates, fn($a, $b) => $b['weight'] - $a['weight']);
        return $candidates[0];
    }

    // -----------------------------------------------------------------
    //  Render the email HTML (inline CSS, email-safe)
    // -----------------------------------------------------------------
    public static function renderEmailHtml(array $data): string
    {
        $name       = e($data['student_name'] ?? 'Student');
        $weekStart  = date('M j', strtotime($data['week_start'] ?? 'now'));
        $weekEnd    = date('M j');
        $inc        = $data['includes'] ?? [];

        // Shared token link
        $dashLink = STUDENT_URL . '/parent-report/?token=' . ($data['share_token'] ?? '');

        ob_start();
        ?>
        <!-- Header bar -->
        <div style="background: #143230; border-radius: 12px 12px 0 0; padding: 28px 32px; text-align: center;">
            <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px;">
                <span style="color: #1FE290;">&#9679;</span> avidmock
            </div>
            <div style="color: #9BBAB7; font-size: 13px; margin-top: 6px;">
                Weekly Progress Report &middot; <?= $weekStart ?> &ndash; <?= $weekEnd ?>
            </div>
        </div>

        <!-- Body -->
        <div style="padding: 32px;">
            <h2 style="color: #143230; margin: 0 0 4px; font-size: 20px;">
                <?= $name ?>&rsquo;s Week in Review
            </h2>
            <p style="color: #6B7280; font-size: 14px; margin: 0 0 28px;">
                Here&rsquo;s how your child progressed this week on Avidmock SAT Prep.
            </p>

            <?php /* ── Proud Parent Moment ── */ ?>
            <?php if (!empty($data['proud_moment'])): ?>
            <div style="background: linear-gradient(135deg, #143230, #1a4440); border-radius: 12px; padding: 24px; margin-bottom: 28px;">
                <div style="color: #1FE290; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                    <?= $data['proud_moment']['emoji'] ?? '' ?> Proud Parent Moment
                </div>
                <div style="color: #FFFFFF; font-size: 18px; font-weight: 700; line-height: 1.3;">
                    <?= e($data['proud_moment']['title'] ?? '') ?>
                </div>
                <div style="color: #9BBAB7; font-size: 13px; margin-top: 6px;">
                    <?= e($data['proud_moment']['detail'] ?? '') ?>
                </div>
            </div>
            <?php endif; ?>

            <?php /* ── Scores Overview ── */ ?>
            <?php if (!empty($inc['scores']) && !empty($data['quizzes'])): ?>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                <tr>
                    <td width="33%" style="padding: 0 6px 0 0; vertical-align: top;">
                        <div style="background: #F0FAF4; border-radius: 10px; padding: 18px 16px; text-align: center;">
                            <div style="font-size: 28px; font-weight: 800; color: #143230;"><?= $data['quizzes']['completed'] ?></div>
                            <div style="font-size: 11px; color: #6B7280; margin-top: 2px;">Quizzes</div>
                        </div>
                    </td>
                    <td width="33%" style="padding: 0 3px; vertical-align: top;">
                        <div style="background: #F0FAF4; border-radius: 10px; padding: 18px 16px; text-align: center;">
                            <div style="font-size: 28px; font-weight: 800; color: #143230;"><?= $data['quizzes']['avg_score'] ?>%</div>
                            <div style="font-size: 11px; color: #6B7280; margin-top: 2px;">Avg Score</div>
                        </div>
                    </td>
                    <td width="33%" style="padding: 0 0 0 6px; vertical-align: top;">
                        <div style="background: #F0FAF4; border-radius: 10px; padding: 18px 16px; text-align: center;">
                            <?php
                                $delta = $data['quizzes']['score_delta'];
                                $deltaColor = $delta > 0 ? '#1FE290' : ($delta < 0 ? '#FF6B6B' : '#6B7280');
                                $deltaSign  = $delta > 0 ? '+' : '';
                                $deltaText  = $delta !== null ? $deltaSign . $delta . '%' : '--';
                            ?>
                            <div style="font-size: 28px; font-weight: 800; color: <?= $deltaColor ?>;"><?= $deltaText ?></div>
                            <div style="font-size: 11px; color: #6B7280; margin-top: 2px;">vs Last Week</div>
                        </div>
                    </td>
                </tr>
            </table>
            <?php endif; ?>

            <?php /* ── Score Trend (HTML table bars) ── */ ?>
            <?php if (!empty($data['score_trend']) && count($data['score_trend']) >= 2): ?>
            <div style="margin-bottom: 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #143230; margin-bottom: 12px;">
                    &#128200; Score Trend
                </div>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <?php
                    $maxScore = 100;
                    foreach ($data['score_trend'] as $week):
                        $pct   = min(100, max(0, round((float) $week['avg_score'])));
                        $barW  = max(4, $pct);
                        $color = $pct >= 80 ? '#1FE290' : ($pct >= 60 ? '#17c87a' : '#FFB347');
                    ?>
                    <tr>
                        <td style="width: 60px; padding: 4px 8px 4px 0; font-size: 11px; color: #6B7280; text-align: right; white-space: nowrap;">
                            <?= e($week['week_label']) ?>
                        </td>
                        <td style="padding: 4px 0;">
                            <div style="background: #E8F3F1; border-radius: 6px; height: 22px; width: 100%; position: relative;">
                                <div style="background: <?= $color ?>; border-radius: 6px; height: 22px; width: <?= $barW ?>%; min-width: 4px;"></div>
                            </div>
                        </td>
                        <td style="width: 50px; padding: 4px 0 4px 8px; font-size: 12px; font-weight: 700; color: #143230;">
                            <?= $pct ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>

            <?php /* ── Study Streak ── */ ?>
            <?php if (!empty($inc['streaks']) && !empty($data['streak'])): ?>
            <div style="margin-bottom: 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #143230; margin-bottom: 12px;">
                    &#128293; Study Streak
                </div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="padding: 0 6px 0 0;">
                            <div style="background: #F0FAF4; border-radius: 10px; padding: 16px; text-align: center;">
                                <div style="font-size: 24px; font-weight: 800; color: #143230;">
                                    <?= $data['streak']['current'] ?> days
                                </div>
                                <div style="font-size: 11px; color: #6B7280;">Current Streak</div>
                            </div>
                        </td>
                        <td width="50%" style="padding: 0 0 0 6px;">
                            <div style="background: #F0FAF4; border-radius: 10px; padding: 16px; text-align: center;">
                                <?php
                                    $mins = (int) ($data['streak']['total_study_time'] ?? 0);
                                    $hrs  = floor($mins / 60);
                                    $rm   = $mins % 60;
                                    $timeStr = $hrs > 0 ? $hrs . 'h ' . $rm . 'm' : $mins . ' min';
                                ?>
                                <div style="font-size: 24px; font-weight: 800; color: #143230;"><?= $timeStr ?></div>
                                <div style="font-size: 11px; color: #6B7280;">Study Time This Week</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <?php /* ── 7-day activity grid ── */ ?>
                <?php if (!empty($data['streak']['activity_7days'])): ?>
                <table cellpadding="0" cellspacing="0" style="margin-top: 12px; width: 100%;">
                    <tr>
                        <?php
                        $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                        $activityMap = [];
                        foreach ($data['streak']['activity_7days'] as $a) {
                            $activityMap[$a['day']] = (int) $a['sessions'];
                        }
                        for ($i = 0; $i < 7; $i++):
                            $d    = date('Y-m-d', strtotime("monday this week +{$i} days"));
                            $cnt  = $activityMap[$d] ?? 0;
                            $bg   = $cnt > 0 ? '#1FE290' : '#E8F3F1';
                            $txtC = $cnt > 0 ? '#143230' : '#9CA3AF';
                        ?>
                        <td style="width: 14.28%; text-align: center; padding: 4px 2px;">
                            <div style="font-size: 10px; color: <?= $txtC ?>; margin-bottom: 4px;"><?= $days[$i] ?></div>
                            <div style="width: 28px; height: 28px; border-radius: 6px; background: <?= $bg ?>; margin: 0 auto; line-height: 28px; font-size: 12px; font-weight: 700; color: #143230;">
                                <?= $cnt > 0 ? $cnt : '&middot;' ?>
                            </div>
                        </td>
                        <?php endfor; ?>
                    </tr>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php /* ── Skills Breakdown ── */ ?>
            <?php if (!empty($inc['scores']) && (!empty($data['skills_strong']) || !empty($data['skills_weak']))): ?>
            <div style="margin-bottom: 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #143230; margin-bottom: 12px;">
                    &#128218; Skills Snapshot
                </div>

                <?php if (!empty($data['skills_strong'])): ?>
                <div style="font-size: 12px; font-weight: 600; color: #1FE290; margin-bottom: 6px;">&#9650; Strongest Skills</div>
                <?php foreach ($data['skills_strong'] as $skill): ?>
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #143230; margin-bottom: 3px;">
                        <span><?= e(ucwords(str_replace('_', ' ', $skill['category'] ?? ''))) ?></span>
                        <span style="font-weight: 700;"><?= round((float) ($skill['avg_score'] ?? 0)) ?>%</span>
                    </div>
                    <div style="background: #E8F3F1; border-radius: 4px; height: 8px;">
                        <div style="background: #1FE290; border-radius: 4px; height: 8px; width: <?= min(100, max(2, round((float) ($skill['avg_score'] ?? 0)))) ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($data['skills_weak'])): ?>
                <div style="font-size: 12px; font-weight: 600; color: #FFB347; margin: 16px 0 6px;">&#9660; Needs Attention</div>
                <?php foreach ($data['skills_weak'] as $skill): ?>
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #143230; margin-bottom: 3px;">
                        <span><?= e(ucwords(str_replace('_', ' ', $skill['category'] ?? ''))) ?></span>
                        <span style="font-weight: 700;"><?= round((float) ($skill['avg_score'] ?? 0)) ?>%</span>
                    </div>
                    <div style="background: #E8F3F1; border-radius: 4px; height: 8px;">
                        <div style="background: #FFB347; border-radius: 4px; height: 8px; width: <?= min(100, max(2, round((float) ($skill['avg_score'] ?? 0)))) ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php /* ── SAT Score Prediction ── */ ?>
            <?php if (!empty($data['sat_prediction'])): ?>
            <div style="background: #143230; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px; text-align: center;">
                <div style="color: #1FE290; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
                    Predicted SAT Score
                </div>
                <div style="color: #FFFFFF; font-size: 32px; font-weight: 800;">
                    <?= $data['sat_prediction']['predicted_low'] ?>&ndash;<?= $data['sat_prediction']['predicted_high'] ?>
                </div>
                <?php if (!empty($data['sat_prediction']['confidence'])): ?>
                <div style="color: #9BBAB7; font-size: 12px; margin-top: 4px;">
                    <?= round($data['sat_prediction']['confidence'] * 100) ?>% confidence &middot;
                    Based on <?= $data['sat_prediction']['based_on'] ?? 0 ?> practice test<?= ($data['sat_prediction']['based_on'] ?? 0) !== 1 ? 's' : '' ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php /* ── AI Tutor Usage ── */ ?>
            <?php if (!empty($inc['ai_usage']) && !empty($data['ai_tutor']) && $data['ai_tutor']['sessions'] > 0): ?>
            <div style="margin-bottom: 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #143230; margin-bottom: 8px;">
                    &#129302; AI Tutor Activity
                </div>
                <div style="font-size: 13px; color: #6B7280; line-height: 1.6;">
                    <?= $data['ai_tutor']['sessions'] ?> session<?= $data['ai_tutor']['sessions'] !== 1 ? 's' : '' ?> this week
                    covering <?= $data['ai_tutor']['topics'] ?> topic<?= $data['ai_tutor']['topics'] !== 1 ? 's' : '' ?>.
                    <?php if (!empty($data['ai_tutor']['top_topics'])): ?>
                    <br>Top topics: <?= e(implode(', ', array_map(fn($t) => ucwords(str_replace('_', ' ', $t['topic'] ?? '')), $data['ai_tutor']['top_topics']))) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php /* ── Achievements ── */ ?>
            <?php if (!empty($inc['achievements']) && !empty($data['achievements'])): ?>
            <div style="margin-bottom: 28px;">
                <div style="font-size: 14px; font-weight: 700; color: #143230; margin-bottom: 12px;">
                    &#127942; Achievements Unlocked
                </div>
                <?php foreach ($data['achievements'] as $badge): ?>
                <div style="display: inline-block; background: <?= e($badge['badge_color'] ?? '#1FE290') ?>22; border: 1px solid <?= e($badge['badge_color'] ?? '#1FE290') ?>44; border-radius: 8px; padding: 8px 14px; margin: 0 6px 6px 0; font-size: 13px; font-weight: 600; color: #143230;">
                    <?= e($badge['name'] ?? '') ?>
                    <span style="color: #6B7280; font-weight: 400; font-size: 11px; margin-left: 4px;">+<?= (int) ($badge['xp_reward'] ?? 0) ?> XP</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php /* ── CTA ── */ ?>
            <div style="text-align: center; margin-top: 32px;">
                <a href="<?= $dashLink ?>" style="display: inline-block; padding: 14px 40px; background: #1FE290; color: #143230; font-weight: 700; font-size: 15px; text-decoration: none; border-radius: 8px;">
                    See Full Dashboard &rarr;
                </a>
                <div style="font-size: 12px; color: #9CA3AF; margin-top: 10px;">
                    This link expires in 7 days. No login required.
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -----------------------------------------------------------------
    //  Send report to parent email(s) for a student
    // -----------------------------------------------------------------
    public static function sendReport(int $userId): array
    {
        $settings = self::getSettings($userId);
        if (!$settings) {
            return ['success' => false, 'error' => 'No parent report settings found.'];
        }

        $emails = array_filter([
            $settings['parent_email_1'] ?? null,
            $settings['parent_email_2'] ?? null,
        ]);

        if (empty($emails)) {
            return ['success' => false, 'error' => 'No parent email configured.'];
        }

        // Generate report data with a share token
        $data = self::generateWeeklyReport($userId);
        if (empty($data)) {
            return ['success' => false, 'error' => 'Could not generate report.'];
        }

        $token = self::generateShareToken($userId);
        $data['share_token'] = $token;

        $html    = self::renderEmailHtml($data);
        $subject = ($data['student_name'] ?? 'Your child') . "'s SAT Prep Report — " . date('M j');

        $results = [];
        foreach ($emails as $email) {
            $sent = Mailer::send($email, $subject, $html);
            $status = $sent ? 'sent' : 'failed';

            try {
                Database::insert('parent_report_log', [
                    'user_id'    => $userId,
                    'sent_to'    => $email,
                    'status'     => $status,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                error_log('[ParentReport::sendReport] log insert: ' . $e->getMessage());
            }

            $results[] = ['email' => $email, 'status' => $status];
        }

        // Update last_sent_at
        try {
            Database::execute(
                "UPDATE parent_report_settings SET last_sent_at = NOW() WHERE user_id = ?",
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[ParentReport::sendReport] last_sent update: ' . $e->getMessage());
        }

        return ['success' => true, 'results' => $results];
    }

    // -----------------------------------------------------------------
    //  Generate a secure, expiring share token (7-day expiry)
    // -----------------------------------------------------------------
    public static function generateShareToken(int $userId): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Clean up old tokens for this user
        try {
            Database::execute(
                "DELETE FROM parent_report_tokens WHERE user_id = ? OR expires_at < NOW()",
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[ParentReport::generateShareToken] cleanup: ' . $e->getMessage());
        }

        Database::insert('parent_report_tokens', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    // -----------------------------------------------------------------
    //  Validate a share token — returns user_id or null
    // -----------------------------------------------------------------
    public static function validateToken(string $token): ?int
    {
        if (strlen($token) < 32) return null;

        $row = Database::fetch(
            "SELECT user_id FROM parent_report_tokens
             WHERE token = ? AND expires_at > NOW()
             LIMIT 1",
            [$token]
        );

        return $row ? (int) $row['user_id'] : null;
    }

    // -----------------------------------------------------------------
    //  Get / save settings
    // -----------------------------------------------------------------
    public static function getSettings(int $userId): ?array
    {
        return Database::fetch(
            "SELECT * FROM parent_report_settings WHERE user_id = ?",
            [$userId]
        );
    }

    public static function saveSettings(int $userId, array $data): void
    {
        $existing = self::getSettings($userId);

        $fields = [
            'parent_email_1'     => $data['parent_email_1']     ?? null,
            'parent_email_2'     => $data['parent_email_2']     ?? null,
            'frequency'          => $data['frequency']          ?? 'weekly',
            'include_scores'     => (int) ($data['include_scores']       ?? 1),
            'include_streaks'    => (int) ($data['include_streaks']      ?? 1),
            'include_ai_usage'   => (int) ($data['include_ai_usage']     ?? 1),
            'include_achievements' => (int) ($data['include_achievements'] ?? 1),
        ];

        if ($existing) {
            Database::update('parent_report_settings', $fields, ['user_id' => $userId]);
        } else {
            Database::insert('parent_report_settings', array_merge($fields, [
                'user_id'    => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    // -----------------------------------------------------------------
    //  Get students due for a report (used by cron)
    // -----------------------------------------------------------------
    public static function getStudentsDueForReport(int $limit = 50, int $offset = 0): array
    {
        return Database::fetchAll(
            "SELECT prs.user_id, prs.parent_email_1, prs.parent_email_2,
                    prs.frequency, prs.last_sent_at
             FROM parent_report_settings prs
             JOIN users u ON u.id = prs.user_id AND u.is_active = 1
             WHERE (prs.parent_email_1 IS NOT NULL OR prs.parent_email_2 IS NOT NULL)
               AND (
                   (prs.frequency = 'weekly'   AND (prs.last_sent_at IS NULL OR prs.last_sent_at < DATE_SUB(NOW(), INTERVAL 6 DAY)))
                OR (prs.frequency = 'biweekly' AND (prs.last_sent_at IS NULL OR prs.last_sent_at < DATE_SUB(NOW(), INTERVAL 13 DAY)))
                OR (prs.frequency = 'monthly'  AND (prs.last_sent_at IS NULL OR prs.last_sent_at < DATE_SUB(NOW(), INTERVAL 27 DAY)))
               )
             ORDER BY prs.last_sent_at ASC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
}
