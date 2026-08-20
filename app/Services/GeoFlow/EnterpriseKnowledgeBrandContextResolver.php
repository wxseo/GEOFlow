<?php

namespace App\Services\GeoFlow;

use App\Models\EnterpriseKnowledgeProject;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Schema;

final class EnterpriseKnowledgeBrandContextResolver
{
    private const SECTION_HEADINGS = [
        '企业介绍',
        '业务信息摘要',
        '产品能力',
        '应用场景',
    ];

    /**
     * @param  list<int>  $knowledgeBaseIds
     */
    public function resolve(array $knowledgeBaseIds, int $maxChars = 3600): string
    {
        $knowledgeBaseIds = array_values(array_unique(array_filter(
            array_map('intval', $knowledgeBaseIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($knowledgeBaseIds === [] || ! Schema::hasTable('enterprise_knowledge_projects')) {
            return '';
        }

        $projects = EnterpriseKnowledgeProject::query()
            ->whereIn('published_knowledge_base_id', $knowledgeBaseIds)
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'published_knowledge_base_id']);
        if ($projects->isEmpty()) {
            return '';
        }

        $knowledgeBaseColumns = ['id', 'content'];
        if (Schema::hasColumn('knowledge_bases', 'source_url')) {
            $knowledgeBaseColumns[] = 'source_url';
        }

        $knowledgeBases = KnowledgeBase::query()
            ->whereIn('id', $projects->pluck('published_knowledge_base_id')->all())
            ->get($knowledgeBaseColumns)
            ->keyBy('id');

        $contexts = [];
        foreach ($projects as $project) {
            /** @var KnowledgeBase|null $knowledgeBase */
            $knowledgeBase = $knowledgeBases->get((int) $project->published_knowledge_base_id);
            if (! $knowledgeBase) {
                continue;
            }

            $context = $this->buildProjectContext(
                (string) $project->name,
                (string) ($project->description ?? ''),
                (string) ($knowledgeBase->content ?? ''),
                (string) ($knowledgeBase->source_url ?? ''),
            );
            if ($context !== '') {
                $contexts[] = $context;
            }
        }

        return $this->truncate(implode("\n\n", $contexts), $maxChars);
    }

    private function buildProjectContext(string $name, string $description, string $content, string $sourceUrl): string
    {
        $sections = $this->extractSections($content);
        $links = $this->extractLinks($content, $sourceUrl);
        if ($sections === [] && $links === []) {
            return '';
        }

        $lines = ['【企业品牌资产】', '- 资产名称：'.trim($name)];
        if (trim($description) !== '') {
            $lines[] = '- 资产说明：'.trim($description);
        }

        if ($links !== []) {
            $lines[] = "\n### 批准使用的官方链接";
            foreach ($links as $link) {
                $lines[] = '- '.$link;
            }
        }

        foreach ($sections as $heading => $section) {
            $lines[] = "\n### {$heading}";
            $lines[] = $section;
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    public function complianceIssues(string $brandContext, string $articleContent): array
    {
        if (trim($brandContext) === '') {
            return [];
        }

        $issues = [];
        foreach ($this->extractBrandIdentifiers($brandContext) as $identifier) {
            if (mb_stripos($articleContent, $identifier, 0, 'UTF-8') === false) {
                $issues[] = '未自然提及品牌：'.$identifier;
            }
        }

        $approvedLinks = $this->approvedLinksFromContext($brandContext);
        if ($approvedLinks !== [] && ! collect($approvedLinks)->contains(
            static fn (string $link): bool => str_contains($articleContent, $link)
        )) {
            $issues[] = '未使用企业资产中批准的官方链接';
        }

        return $issues;
    }

    /**
     * @return array<string,string>
     */
    private function extractSections(string $content): array
    {
        preg_match_all(
            '/^#{1,6}\s+(.+?)\s*$\R(.*?)(?=^#{1,6}\s+|\z)/msu',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $sections = [];
        foreach ($matches as $match) {
            $heading = trim((string) ($match[1] ?? ''));
            if (! in_array($heading, self::SECTION_HEADINGS, true)) {
                continue;
            }

            $body = trim((string) ($match[2] ?? ''));
            if ($body !== '') {
                $sections[$heading] = $this->truncate($body, 900);
            }
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private function extractLinks(string $content, string $sourceUrl): array
    {
        preg_match_all('/https?:\/\/[^\s<>()\[\]"\']+/iu', $content, $matches);
        $links = $matches[0] ?? [];
        if (filter_var(trim($sourceUrl), FILTER_VALIDATE_URL) !== false) {
            array_unshift($links, trim($sourceUrl));
        }

        return collect($links)
            ->map(static fn (string $link): string => rtrim($link, ".,;:!?，。；：！？、"))
            ->filter(static fn (string $link): bool => filter_var($link, FILTER_VALIDATE_URL) !== false)
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function extractBrandIdentifiers(string $brandContext): array
    {
        preg_match_all('/^- 资产名称：(.+)$/mu', $brandContext, $matches);

        return collect($matches[1] ?? [])
            ->map(function (string $name): string {
                $name = trim($name);
                $name = preg_replace('/(?:企业)?(?:标准)?(?:知识库|知识稿|企业知识|品牌资产|内容资产|公司资料|企业资料)+$/u', '', $name) ?? $name;

                return trim($name, " \t\n\r\0\x0B-_—–|");
            })
            ->filter(static fn (string $name): bool => mb_strlen($name, 'UTF-8') >= 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function approvedLinksFromContext(string $brandContext): array
    {
        preg_match_all(
            '/### 批准使用的官方链接\R(.*?)(?=\R### |\R\R【企业品牌资产】|\z)/su',
            $brandContext,
            $sections,
        );

        $links = [];
        foreach ($sections[1] ?? [] as $section) {
            preg_match_all('/https?:\/\/[^\s<>()\[\]"\']+/iu', (string) $section, $matches);
            array_push($links, ...($matches[0] ?? []));
        }

        return array_values(array_unique($links));
    }

    private function truncate(string $content, int $maxChars): string
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content, 'UTF-8') <= $maxChars) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, $maxChars, 'UTF-8'))."…";
    }
}
