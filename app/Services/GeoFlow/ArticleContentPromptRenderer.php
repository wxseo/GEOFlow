<?php

namespace App\Services\GeoFlow;

final class ArticleContentPromptRenderer
{
    /**
     * 单篇编辑器使用自然来源表述，避免把内部证据编号写入成稿。
     */
    public function renderForEditor(string $title, string $keyword, ?string $promptContent, string $knowledgeContext = ''): string
    {
        return $this->render($title, $keyword, $promptContent, $knowledgeContext);
    }

    /**
     * Worker 保留内部证据上下文，但不把证据编号写入公开成稿。
     */
    public function renderForWorker(
        string $title,
        string $keyword,
        ?string $promptContent,
        string $knowledgeContext = '',
        string $brandContext = '',
    ): string
    {
        return $this->render($title, $keyword, $promptContent, $knowledgeContext, $brandContext);
    }

    /**
     * 构造正文提示词：优先精确替换变量；无变量的自定义提示词自动补齐文章上下文。
     */
    private function render(
        string $title,
        string $keyword,
        ?string $promptContent,
        string $knowledgeContext,
        string $brandContext = '',
    ): string {
        $prompt = trim((string) $promptContent);
        $isFallbackPrompt = false;
        if ($prompt === '') {
            $prompt = "请围绕标题“{$title}”和关键词“{$keyword}”生成一篇结构清晰、语言自然的中文文章。";
            $isFallbackPrompt = true;
        }

        $hasExplicitContextVariables = $isFallbackPrompt || $this->promptHasKnownContextVariables($prompt);
        $hasExplicitKnowledgeVariable = $this->promptHasContextVariable($prompt, 'knowledge');
        $renderedPrompt = $this->renderPromptTemplate($prompt, [
            'title' => $title,
            'keyword' => $keyword,
            'knowledge' => $knowledgeContext,
        ]);

        if (! $hasExplicitContextVariables) {
            $renderedPrompt = $this->appendSmartPromptContext($renderedPrompt, $title, $keyword, $knowledgeContext);
        } elseif (! $hasExplicitKnowledgeVariable) {
            $renderedPrompt = $this->appendKnowledgeContext($renderedPrompt, $knowledgeContext);
        }

        $renderedPrompt = $this->appendBrandContext($renderedPrompt, $brandContext);

        $finalInstructions = array_values(array_filter([
            $this->knowledgeAttributionInstruction($renderedPrompt, $knowledgeContext),
            $this->brandUsageInstruction($renderedPrompt, $brandContext),
            $this->finalPromptInstruction($renderedPrompt),
        ], static fn (string $instruction): bool => trim($instruction) !== ''));

        return trim($renderedPrompt)."\n\n".implode("\n", $finalInstructions);
    }

    private function promptHasKnownContextVariables(string $prompt): bool
    {
        return preg_match('/\{\{\s*(title|keyword|knowledge)\s*\}\}/iu', $prompt) === 1
            || preg_match('/\{\{#if\s+(title|keyword|knowledge)\s*\}\}/iu', $prompt) === 1;
    }

    private function promptHasContextVariable(string $prompt, string $name): bool
    {
        $variable = preg_quote($name, '/');

        return preg_match('/\{\{\s*'.$variable.'\s*\}\}/iu', $prompt) === 1
            || preg_match('/\{\{#if\s+'.$variable.'\s*\}\}/iu', $prompt) === 1;
    }

    /**
     * @param  array{title:string, keyword:string, knowledge:string}  $context
     */
    private function renderPromptTemplate(string $prompt, array $context): string
    {
        $renderedPrompt = preg_replace_callback('/\{\{#if\s+([A-Za-z_][A-Za-z0-9_]*)\s*\}\}(.*?)\{\{\/if\}\}/su', function (array $matches) use ($context): string {
            $name = (string) ($matches[1] ?? '');
            if (! $this->isKnownPromptContextName($name)) {
                return (string) ($matches[0] ?? '');
            }

            $value = $this->promptContextValue($name, $context);

            return trim($value) !== '' ? (string) ($matches[2] ?? '') : '';
        }, $prompt) ?? $prompt;

        return preg_replace_callback('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/u', function (array $matches) use ($context): string {
            $name = (string) ($matches[1] ?? '');
            $value = $this->promptContextValue($name, $context);

            return $value !== '' || $this->isKnownPromptContextName($name) ? $value : (string) ($matches[0] ?? '');
        }, $renderedPrompt) ?? $renderedPrompt;
    }

    /**
     * @param  array{title:string, keyword:string, knowledge:string}  $context
     */
    private function promptContextValue(string $name, array $context): string
    {
        return match (mb_strtolower($name, 'UTF-8')) {
            'title' => $context['title'],
            'keyword' => $context['keyword'],
            'knowledge' => $context['knowledge'],
            default => '',
        };
    }

    private function isKnownPromptContextName(string $name): bool
    {
        return in_array(mb_strtolower($name, 'UTF-8'), ['title', 'keyword', 'knowledge'], true);
    }

    private function appendSmartPromptContext(string $prompt, string $title, string $keyword, string $knowledgeContext): string
    {
        if ($this->isLikelyEnglishPrompt($prompt)) {
            $lines = [
                'Task context:',
                '- Article title: '.$title,
            ];
            if (trim($keyword) !== '') {
                $lines[] = '- Core keyword: '.$keyword;
            }
            if (trim($knowledgeContext) !== '') {
                $lines[] = '- Reference knowledge:';
                $lines[] = $knowledgeContext;
            }

            return trim($prompt)."\n\n".implode("\n", $lines);
        }

        $lines = [
            '【任务上下文】',
            '- 文章标题：'.$title,
        ];
        if (trim($keyword) !== '') {
            $lines[] = '- 核心关键词：'.$keyword;
        }
        if (trim($knowledgeContext) !== '') {
            $lines[] = '- 参考知识：';
            $lines[] = $knowledgeContext;
        }

        return trim($prompt)."\n\n".implode("\n", $lines);
    }

    private function appendKnowledgeContext(string $prompt, string $knowledgeContext): string
    {
        if (trim($knowledgeContext) === '') {
            return trim($prompt);
        }

        if ($this->isLikelyEnglishPrompt($prompt)) {
            return trim($prompt)."\n\nReference knowledge:\n".$knowledgeContext;
        }

        return trim($prompt)."\n\n【参考知识】\n".$knowledgeContext;
    }

    private function appendBrandContext(string $prompt, string $brandContext): string
    {
        if (trim($brandContext) === '') {
            return trim($prompt);
        }

        if ($this->isLikelyEnglishPrompt($prompt)) {
            return trim($prompt)."\n\nReviewed brand and product assets:\n".$brandContext;
        }

        return trim($prompt)."\n\n【已审核的品牌与产品资产】\n".$brandContext;
    }

    private function finalPromptInstruction(string $prompt): string
    {
        if ($this->isLikelyEnglishPrompt($prompt)) {
            return 'Please output only the final article body in Markdown. Do not repeat the prompt or output placeholders.';
        }

        return '请直接输出最终文章正文（Markdown），不要重复提示词、不要输出占位符。';
    }

    private function knowledgeAttributionInstruction(string $prompt, string $knowledgeContext): string
    {
        if (trim($knowledgeContext) === '') {
            return '';
        }

        if ($this->isLikelyEnglishPrompt($prompt)) {
            return 'Knowledge attribution rule: never output citation placeholders, including but not limited to [K1], [K2], [K3], 【K1】, or （K1）. When attribution is needed, write natural phrases such as “the materials show,” “the client confirmed,” or “according to the store materials,” without numbered citations. If the evidence is insufficient, use cautious wording and do not invent sources or conclusions.';
        }

        return '知识库依据表达要求：禁止输出任何引用占位符，包括但不限于 [K1]、[K2]、[K3]、【K1】、（K1）等。文章中如需表达依据，直接写“资料显示”“客户确认”“根据门店资料”，不要添加编号引用。证据不足时不要编造来源或结论。';
    }

    private function brandUsageInstruction(string $prompt, string $brandContext): string
    {
        if (trim($brandContext) === '') {
            return '';
        }

        if ($this->isLikelyEnglishPrompt($prompt)) {
            return 'Brand asset usage rules: this task includes reviewed brand assets. Naturally mention the company or brand 1-2 times and connect only the most relevant product capability. If approved official links are listed, include at least one relevant link and use only URLs from that list. Never invent names, links, capabilities, cases, data, or claims, and do not dump the asset text as an advertisement.';
        }

        return '品牌资产使用要求：当前任务已选择审核过的品牌资产，请自然提及企业或品牌 1—2 次，只关联最相关的产品能力。如资产中列有“批准使用的官方链接”，正文至少使用其中一个与主题最相关的链接，严禁编造或改写 URL。不得编造品牌、产品、案例、数据或效果承诺，也不要把资产原文整段堆成广告。';
    }

    private function isLikelyEnglishPrompt(string $prompt): bool
    {
        preg_match_all('/\p{Han}/u', $prompt, $cjkMatches);
        preg_match_all('/[A-Za-z]/', $prompt, $latinMatches);

        return count($latinMatches[0] ?? []) > 20 && count($cjkMatches[0] ?? []) <= 3;
    }
}
