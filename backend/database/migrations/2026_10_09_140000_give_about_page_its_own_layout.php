<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The imported About page flattened its numbers, values and reasons into loose paragraphs.
 * Split them into the "About us" template's fields so they render as proper sections and stay editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('path', 'about')->where('template', 'default')->first();
        if (! $page || ! str_contains((string) $page->content, 'Our Impact in Numbers')) {
            return;
        }

        $story = '<p>Instacertify was born in 2015 with a simple yet powerful vision—to make product certification and regulatory compliance easier for businesses. Our journey began when our founder started consulting small and medium enterprises, helping them navigate complex regulatory requirements so they could focus on building better, safer, and compliant products.</p>'
            .'<p>As trust grew and client needs expanded, Instacertify evolved beyond individual consulting. In 2022, we transitioned into a private limited company, marking a more structured and professional phase of our journey as a dedicated certification and compliance consulting firm.</p>'
            .'<p>Along the way, we built a strong multidisciplinary team comprising chemical specialists, electronics and IT experts, mechanical engineers, and regulatory framework professionals. To further strengthen our capabilities, we partnered with global testing laboratories and certification bodies, enabling us to support both Indian and international certification requirements.</p>'
            .'<p>Today, Instacertify stands as a trusted compliance partner, combining technical expertise, regulatory insight, and global collaboration to help businesses achieve certification efficiently, confidently, and sustainably.</p>';

        $data = [
            'milestones' => [
                ['year' => '2015', 'text' => 'Started helping small and medium manufacturers through certification and compliance'],
                ['year' => '2022', 'text' => 'Became a private limited company dedicated to certification and compliance consulting'],
                ['year' => 'Today', 'text' => 'A multidisciplinary team working with global testing labs and certification bodies'],
            ],
            'stats_heading' => 'Our Impact in Numbers',
            'stats' => [
                ['value' => '3000+', 'label' => 'Unique Customer'],
                ['value' => '100%', 'label' => 'Success Rate'],
                ['value' => '15+', 'label' => 'Years Experience'],
                ['value' => '40+', 'label' => 'Certification Solutions'],
                ['value' => '2500+', 'label' => 'Standards Covered'],
                ['value' => '600+', 'label' => 'Testing Facilities Partnered'],
                ['value' => '3', 'label' => 'Offices'],
                ['value' => '20+', 'label' => 'Global Partners'],
            ],
            'values_heading' => 'Our Mission & Values',
            'values_intro' => 'The principles that shape our commitment to compliance excellence.',
            'values' => [
                ['icon' => 'workspace_premium', 'title' => 'Quality-First Excellence', 'text' => 'We are committed to delivering accurate, reliable, and regulation-ready outcomes, ensuring the highest standards of quality and compliance in every engagement.'],
                ['icon' => 'handshake', 'title' => 'Client-Centric Partnership', 'text' => 'We collaborate closely with our clients as long-term partners, aligning with their business goals and simplifying complex regulatory journeys.'],
                ['icon' => 'rocket_launch', 'title' => 'Continuous Improvement & Innovation', 'text' => 'We constantly refine our processes, adopt global best practices, and leverage innovation to enable faster, smarter, and more effective certification solutions.'],
            ],
            'reasons_heading' => 'Why Businesses Trust Instacertify',
            'reasons_intro' => 'Expert-led certification, accredited partnerships, and global compliance support.',
            'reasons' => [
                ['icon' => 'verified', 'title' => 'Established Compliance Experts', 'text' => 'Over a decade of experience guiding manufacturers through complex certification and regulatory pathways across multiple industries.'],
                ['icon' => 'science', 'title' => 'Accredited Testing Network', 'text' => 'Access to NABL and ISO/IEC 17025 accredited laboratories in India and abroad for fast, defensible, globally accepted test results.'],
                ['icon' => 'groups', 'title' => 'Multidisciplinary Specialists', 'text' => 'Chemical, electronics, IT, mechanical and regulatory experts on a single team—covering the full spectrum of product compliance.'],
                ['icon' => 'task_alt', 'title' => 'End-to-End Certification Coverage', 'text' => 'From applicability study and testing to audit support and certificate issuance, we manage every stage so you can stay focused on your product.'],
            ],
        ];

        DB::table('pages')->where('id', $page->id)->update([
            'template' => 'about',
            'title' => 'About Instacertify',
            'excerpt' => $page->excerpt ?: 'Instacertify was founded in 2015 to simplify product certification and regulatory compliance for businesses. What began as consulting support for SMEs evolved into a private limited company in 2022. With a multidisciplinary expert team and global certification partners, Instacertify delivers reliable, efficient, and market-ready compliance solutions across India and international markets.',
            'content' => $story,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        // Content restructure; the admin can edit or switch the template back.
    }
};
