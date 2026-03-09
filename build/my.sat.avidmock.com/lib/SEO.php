<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · SEO
 *  Meta tags, Open Graph, JSON-LD schema for lesson/quiz pages.
 * ═══════════════════════════════════════════════════════════════════
 */

class SEO
{
    public static function buildMeta(array $page): string
    {
        $title = e($page['title'] ?? 'Avidmock SAT') . ' — Avidmock SAT Prep';
        $desc  = e($page['description'] ?? 'Personalized AI-powered SAT preparation.');
        $url   = e($page['url'] ?? STUDENT_URL);
        $image = $page['image'] ?? ASSETS_URL . '/images/og-default.jpg';

        return "
        <title>{$title}</title>
        <meta name=\"description\" content=\"{$desc}\">
        <link rel=\"canonical\" href=\"{$url}\">
        <meta property=\"og:title\" content=\"{$title}\">
        <meta property=\"og:description\" content=\"{$desc}\">
        <meta property=\"og:url\" content=\"{$url}\">
        <meta property=\"og:image\" content=\"{$image}\">
        <meta property=\"og:type\" content=\"website\">
        <meta property=\"og:site_name\" content=\"Avidmock\">
        <meta name=\"twitter:card\" content=\"summary_large_image\">
        <meta name=\"twitter:title\" content=\"{$title}\">
        <meta name=\"twitter:description\" content=\"{$desc}\">
        ";
    }

    public static function buildLessonSchema(array $lesson): string
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            'name'        => $lesson['title'],
            'description' => $lesson['description'] ?? '',
            'provider'    => [
                '@type' => 'Organization',
                'name'  => 'Avidmock',
                'url'   => 'https://avidmock.com',
            ],
            'educationalLevel' => 'High School',
            'about'            => 'SAT Preparation',
            'isAccessibleForFree' => true,
        ];

        if (!empty($lesson['video_path'])) {
            $schema['video'] = [
                '@type'       => 'VideoObject',
                'name'        => $lesson['title'],
                'description' => $lesson['description'] ?? '',
                'duration'    => 'PT' . (int)($lesson['duration_secs'] / 60) . 'M',
                'uploadDate'  => $lesson['created_at'] ?? date('Y-m-d'),
            ];
        }

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public static function buildQuizSchema(array $quiz): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Quiz',
            'name'     => $quiz['title'],
            'about'    => 'SAT Practice',
            'educationalLevel' => 'High School',
            'numberOfQuestions' => $quiz['question_count'] ?? 0,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public static function buildFAQSchema(array $faqs): string
    {
        $items = [];
        foreach ($faqs as $faq) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['answer'],
                ],
            ];
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>';
    }
}