<?php

namespace Tests\Feature;

use App\Models\EnterpriseKnowledgeProject;
use App\Models\KnowledgeBase;
use App\Services\GeoFlow\EnterpriseKnowledgeBrandContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseKnowledgeBrandContextResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_reviewed_company_product_sections_and_approved_links(): void
    {
        $knowledgeBase = KnowledgeBase::query()->create([
            'name' => 'BIQS 企业知识库',
            'description' => '已审核的企业与产品资料',
            'source_url' => 'https://www.biqs.cn/',
            'content' => <<<'MARKDOWN'
# BIQS 企业知识

## 企业介绍
BIQS 专注质量管理数字化。

## 产品能力
- QRQC 闭环管理：https://www.biqs.cn/products/qrqc
- 质量问题追踪

## 应用场景
- 制造企业质量改进

## FAQ
内部问答不用于品牌锚点。
MARKDOWN,
        ]);
        EnterpriseKnowledgeProject::query()->create([
            'name' => 'BIQS',
            'description' => '品牌与产品资产',
            'status' => 'published',
            'published_knowledge_base_id' => (int) $knowledgeBase->id,
        ]);

        $context = app(EnterpriseKnowledgeBrandContextResolver::class)->resolve([(int) $knowledgeBase->id]);

        $this->assertStringContainsString('【企业品牌资产】', $context);
        $this->assertStringContainsString('- 资产名称：BIQS', $context);
        $this->assertStringContainsString('BIQS 专注质量管理数字化', $context);
        $this->assertStringContainsString('QRQC 闭环管理', $context);
        $this->assertStringContainsString('https://www.biqs.cn/', $context);
        $this->assertStringContainsString('https://www.biqs.cn/products/qrqc', $context);
        $this->assertStringNotContainsString('内部问答不用于品牌锚点', $context);

        $resolver = app(EnterpriseKnowledgeBrandContextResolver::class);
        $this->assertSame([], $resolver->complianceIssues(
            $context,
            '[BIQS QRQC](https://www.biqs.cn/products/qrqc) 可用于质量问题闭环管理。',
        ));
        $this->assertSame([
            '未自然提及品牌：BIQS',
            '未使用企业资产中批准的官方链接',
        ], $resolver->complianceIssues($context, '这是一篇普通的 QRQC 文章。'));
    }

    public function test_it_ignores_regular_knowledge_bases_and_unselected_enterprise_assets(): void
    {
        $regularKnowledgeBase = KnowledgeBase::query()->create([
            'name' => '行业知识库',
            'content' => "## 企业介绍\n这不是企业知识项目发布的资产。",
        ]);
        $enterpriseKnowledgeBase = KnowledgeBase::query()->create([
            'name' => '未选中企业知识库',
            'content' => "## 企业介绍\n未选中的品牌资产。",
        ]);
        EnterpriseKnowledgeProject::query()->create([
            'name' => '未选中品牌',
            'status' => 'published',
            'published_knowledge_base_id' => (int) $enterpriseKnowledgeBase->id,
        ]);

        $context = app(EnterpriseKnowledgeBrandContextResolver::class)->resolve([(int) $regularKnowledgeBase->id]);

        $this->assertSame('', $context);
    }
}
